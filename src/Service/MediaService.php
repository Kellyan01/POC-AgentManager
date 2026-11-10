<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Exception\BusinessRuleException;
use App\Model\Media;
use App\Model\MediaOwner;
use App\Repository\MediaRepository;
use finfo;
use Throwable;

/**
 * Upload et suppression des images (portraits, illustrations).
 *
 * Sécurité de l'upload :
 *  1. le type réel du fichier est lu par finfo (le contenu), JAMAIS déduit de
 *     l'extension ou du type MIME envoyés par le navigateur (falsifiables) ;
 *  2. getimagesize() confirme que le fichier est une image décodable ;
 *  3. le fichier est renommé avec un nom aléatoire, et son extension vient de
 *     la liste blanche ci-dessous : aucun « photo.php.jpg » ne peut être servi.
 */
final class MediaService
{
    public const MAX_SIZE = 2 * 1024 * 1024; // 2 Mo

    /** Types acceptés : type MIME réel => extension du fichier enregistré. */
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Forme exacte des chemins produits par upload() : seuls ceux-là sont supprimés du disque. */
    private const URL_PATTERN = '#^uploads/(agents|threats|operations)/[a-f0-9]{32}\.(jpg|png|webp)$#';

    /** @param string $publicDir chemin absolu du dossier public/ */
    public function __construct(
        private readonly MediaRepository $medias,
        private readonly string $publicDir,
    ) {
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $file entrée de $_FILES
     * @throws BusinessRuleException fichier absent, trop lourd ou d'un type non autorisé
     */
    public function upload(?array $file, MediaOwner $owner, int $ownerId): Media
    {
        $extension = $this->validate($file);

        $relativePath = $owner->directory() . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $absolutePath = $this->publicDir . '/' . $relativePath;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            throw new BusinessRuleException('Le fichier n\'a pas pu être enregistré. Réessayez.');
        }

        try {
            $media = new Media(null, $relativePath, $owner, $ownerId);
            $id = $this->medias->insert($media);
        } catch (Throwable $e) {
            // Pas de ligne en base : on ne laisse pas de fichier orphelin sur le disque.
            $this->removeFiles([$relativePath]);
            throw $e;
        }

        return new Media($id, $relativePath, $owner, $ownerId);
    }

    /** Supprime la ligne PUIS le fichier (si la base refuse, le fichier est conservé). */
    public function delete(Media $media): void
    {
        $this->medias->delete((int) $media->id);
        $this->removeFiles([$media->url]);
    }

    /**
     * Supprime les lignes des médias d'une entité et renvoie les chemins des fichiers.
     * À appeler DANS la transaction qui supprime l'entité (les clés étrangères de
     * Medias sont en RESTRICT) ; les fichiers sont effacés APRÈS le commit avec
     * removeFiles() : en cas d'annulation, aucune image n'est perdue.
     *
     * @return list<string> chemins relatifs des fichiers à supprimer
     */
    public function deleteRowsForOwner(MediaOwner $owner, int $ownerId): array
    {
        $urls = array_map(static fn (Media $media): string => $media->url, $this->medias->findForOwner($owner, $ownerId));
        $this->medias->deleteForOwner($owner, $ownerId);

        return $urls;
    }

    /** @param list<string> $relativePaths */
    public function removeFiles(array $relativePaths): void
    {
        foreach ($relativePaths as $relativePath) {
            // On ne supprime que des chemins produits par upload() : jamais un chemin arbitraire.
            if (preg_match(self::URL_PATTERN, $relativePath) !== 1) {
                error_log('Suppression refusée, chemin de média inattendu : ' . $relativePath);
                continue;
            }

            $absolutePath = $this->publicDir . '/' . $relativePath;
            if (is_file($absolutePath) && !unlink($absolutePath)) {
                error_log('Impossible de supprimer le fichier ' . $absolutePath);
            }
        }
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $file
     * @return string extension à utiliser
     */
    private function validate(?array $file): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        $message = match ($error) {
            UPLOAD_ERR_OK                           => null,
            UPLOAD_ERR_NO_FILE                      => 'Choisissez une image à envoyer.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image trop volumineuse : 2 Mo maximum.',
            default                                 => 'L\'envoi du fichier a échoué. Réessayez.',
        };
        if ($message !== null) {
            throw new BusinessRuleException($message);
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new BusinessRuleException('L\'envoi du fichier a échoué. Réessayez.');
        }
        if ($file['size'] > self::MAX_SIZE) {
            throw new BusinessRuleException('Image trop volumineuse : 2 Mo maximum.');
        }

        // 1. Type réel, lu dans le contenu du fichier.
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!is_string($mime) || !isset(self::ALLOWED_TYPES[$mime])) {
            throw new BusinessRuleException('Format non autorisé : seules les images JPEG, PNG et WebP sont acceptées.');
        }

        // 2. Le fichier doit être une image décodable, du même type.
        //    Sur un fichier corrompu, getimagesize() émet un avertissement en plus de
        //    renvoyer false : le @ le masque, car ce cas est traité juste en dessous.
        $info = @getimagesize($file['tmp_name']);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info['mime'] !== $mime) {
            throw new BusinessRuleException('Ce fichier n\'est pas une image valide.');
        }

        return self::ALLOWED_TYPES[$mime];
    }
}
