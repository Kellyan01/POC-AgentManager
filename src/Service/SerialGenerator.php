<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Génère les numéros de série (décision Q8) :
 *   - menaces    : THR-0005
 *   - opérations : OP-2026-010 (année de début + numéro d'ordre dans l'année)
 *
 * Le numéro est calculé à partir du plus grand numéro existant. Deux créations
 * simultanées pourraient obtenir le même : l'index UNIQUE de la colonne serial
 * refuse alors la seconde, et le service appelant recommence (voir les boucles
 * de nouvelle tentative dans ThreatService et OperationService).
 */
final class SerialGenerator
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function nextThreatSerial(): string
    {
        $statement = $this->pdo->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(serial, 5) AS UNSIGNED)), 0) FROM Threats WHERE serial LIKE 'THR-%'"
        );

        return sprintf('THR-%04d', (int) $statement->fetchColumn() + 1);
    }

    public function nextOperationSerial(int $year): string
    {
        $prefix = sprintf('OP-%d-', $year);
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(serial, :start) AS UNSIGNED)), 0) FROM Operations WHERE serial LIKE :pattern'
        );
        $statement->execute(['start' => strlen($prefix) + 1, 'pattern' => $prefix . '%']);

        return sprintf('%s%03d', $prefix, (int) $statement->fetchColumn() + 1);
    }
}
