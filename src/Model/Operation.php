<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Opération déclarée par un utilisateur (Operations.Id_User = le déclarant).
 * date_end null = opération en cours dont la fin n'est pas connue.
 *
 * Les propriétés après $declarantId sont renseignées par les jointures.
 */
final class Operation
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $serial,
        public readonly string $codeName,
        public readonly DateTimeImmutable $dateBeg,
        public readonly ?DateTimeImmutable $dateEnd,
        public readonly ?string $brief,
        public readonly int $statusId,
        public readonly int $declarantId,
        public readonly ?string $status = null,
        public readonly ?string $declarant = null,
        public readonly ?string $illustration = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['Id_Operation'],
            (string) $row['serial'],
            (string) $row['code_name'],
            new DateTimeImmutable((string) $row['date_beg']),
            to_date($row['date_end'] ?? null),
            $row['brief'] !== null ? (string) $row['brief'] : null,
            (int) $row['Id_Status'],
            (int) $row['Id_User'],
            isset($row['status']) ? (string) $row['status'] : null,
            isset($row['declarant']) ? (string) $row['declarant'] : null,
            isset($row['illustration']) ? (string) $row['illustration'] : null,
        );
    }

    public function isCancelled(): bool
    {
        return $this->status === Status::CANCELLED;
    }
}
