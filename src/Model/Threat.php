<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Menace visée par les opérations. Les propriétés après $createdAt sont
 * renseignées par les jointures (tier, portrait principal, nombre d'opérations).
 */
final class Threat
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $serial,
        public readonly ?string $informations,
        public readonly int $tierId,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?string $tier = null,
        public readonly ?int $tierLevel = null,
        public readonly ?string $portrait = null,
        public readonly ?int $operationCount = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['Id_Threat'],
            (string) $row['name'],
            (string) $row['serial'],
            $row['informations'] !== null ? (string) $row['informations'] : null,
            (int) $row['Id_Tier'],
            to_date($row['created_at'] ?? null),
            isset($row['tier']) ? (string) $row['tier'] : null,
            isset($row['tier_level']) ? (int) $row['tier_level'] : null,
            isset($row['portrait']) ? (string) $row['portrait'] : null,
            isset($row['operation_count']) ? (int) $row['operation_count'] : null,
        );
    }
}
