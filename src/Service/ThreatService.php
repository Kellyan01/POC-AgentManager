<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Threat;
use App\Repository\MysqlError;
use App\Repository\ThreatRepository;
use PDOException;

/**
 * Règles métier des menaces : création avec numéro de série automatique.
 */
final class ThreatService
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly ThreatRepository $threats,
        private readonly SerialGenerator $serials,
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
}
