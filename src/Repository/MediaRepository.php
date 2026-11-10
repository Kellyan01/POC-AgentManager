<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Media;
use App\Model\MediaOwner;

/**
 * Lignes de la table Medias. Les fichiers sur le disque sont gérés par MediaService.
 *
 * @extends AbstractRepository<Media>
 */
final class MediaRepository extends AbstractRepository implements DeletableRepositoryInterface
{
    protected const TABLE = 'Medias';
    protected const PRIMARY_KEY = 'Id_Media';

    protected function hydrate(array $row): Media
    {
        return Media::fromRow($row);
    }

    public function findById(int $id): ?Media
    {
        return parent::findById($id);
    }

    /** @return list<Media> */
    public function findAll(): array
    {
        return parent::findAll();
    }

    /**
     * Images d'une entité, la plus récente d'abord : la première est l'image
     * principale (Id_Media le plus élevé, requête 8).
     *
     * @return list<Media>
     */
    public function findForOwner(MediaOwner $owner, int $ownerId): array
    {
        $rows = $this->fetchAll(
            sprintf('SELECT * FROM Medias WHERE %s = :owner ORDER BY Id_Media DESC', $owner->column()),
            ['owner' => $ownerId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function insert(object $entity): int
    {
        $media = $this->expect($entity, Media::class);
        $this->execute(
            sprintf('INSERT INTO Medias (url, %s) VALUES (:url, :owner)', $media->owner->column()),
            ['url' => $media->url, 'owner' => $media->ownerId],
        );

        return (int) $this->pdo->lastInsertId();
    }

    /** Un média ne change ni de fichier ni de propriétaire : seule l'URL serait modifiable. */
    public function update(object $entity): void
    {
        $media = $this->expect($entity, Media::class);
        $this->execute('UPDATE Medias SET url = :url WHERE Id_Media = :id', ['url' => $media->url, 'id' => $media->id]);
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM Medias WHERE Id_Media = :id', ['id' => $id]);
    }

    public function deleteForOwner(MediaOwner $owner, int $ownerId): void
    {
        $this->execute(sprintf('DELETE FROM Medias WHERE %s = :owner', $owner->column()), ['owner' => $ownerId]);
    }
}
