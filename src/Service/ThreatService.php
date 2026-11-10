<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Exception\BusinessRuleException;
use App\Model\MediaOwner;
use App\Model\Threat;
use App\Repository\MysqlError;
use App\Repository\ThreatRepository;
use PDO;
use PDOException;
use Throwable;

/**
 * Règles métier des menaces : création avec numéro de série automatique, suppression.
 */
final class ThreatService
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ThreatRepository $threats,
        private readonly SerialGenerator $serials,
        private readonly MediaService $media,
    ) {
    }

    /**
     * Crée la menace avec le prochain numéro THR-NNNN.
     *
     * @return int identifiant de la menace créée
     */
    public function create(string $name, ?string $informations, int $tierId): int
    {
        for ($attempt = 1; ; $attempt++) {
            $threat = new Threat(null, $name, $this->serials->nextThreatSerial(), $informations, $tierId);
            try {
                return $this->threats->insert($threat);
            } catch (PDOException $e) {
                // Numéro pris entre-temps par une création simultanée : on en calcule un autre.
                if (!MysqlError::is($e, MysqlError::DUPLICATE_ENTRY) || $attempt === self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Supprime une menace qui n'est visée par aucune opération (décision Q7).
     * Ses médias (RESTRICT) sont supprimés d'abord dans la même transaction,
     * leurs fichiers après le commit.
     *
     * @throws BusinessRuleException si une opération vise encore cette menace
     */
    public function delete(Threat $threat): void
    {
        if ($this->threats->findOperations((int) $threat->id) !== []) {
            throw new BusinessRuleException(sprintf(
                '%s est visée par au moins une opération : la fiche doit être conservée pour l\'historique.',
                $threat->name,
            ));
        }

        $this->pdo->beginTransaction();
        try {
            $files = $this->media->deleteRowsForOwner(MediaOwner::Threat, (int) $threat->id);
            $this->threats->delete((int) $threat->id);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->media->removeFiles($files);
    }
}
