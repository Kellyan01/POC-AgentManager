<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Équipe d'agents, placée sous la responsabilité d'un manager (Teams.Id_User).
 */
final class Team
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly int $managerId,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $archivedAt = null,
        public readonly ?string $managerName = null,
        public readonly ?int $memberCount = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['Id_Team'],
            (string) $row['team_name'],
            (int) $row['Id_User'],
            to_date($row['created_at'] ?? null),
            to_date($row['archived_at'] ?? null),
            isset($row['manager_name']) ? (string) $row['manager_name'] : null,
            isset($row['member_count']) ? (int) $row['member_count'] : null,
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}
