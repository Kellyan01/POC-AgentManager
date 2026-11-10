<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Appartenance d'un agent à une équipe, sur une période.
 * date_leave NULL = appartenance en cours (une seule par agent, garanti par la base).
 */
final class Membership
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $agentId,
        public readonly int $teamId,
        public readonly DateTimeImmutable $dateJoin,
        public readonly ?DateTimeImmutable $dateLeave = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['Id_Membership'],
            (int) $row['Id_Agent'],
            (int) $row['Id_Team'],
            new DateTimeImmutable((string) $row['date_join']),
            to_date($row['date_leave'] ?? null),
        );
    }

    public function isCurrent(): bool
    {
        return $this->dateLeave === null;
    }
}
