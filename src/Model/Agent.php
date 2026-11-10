<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Agent doté de super-pouvoirs.
 *
 * Les propriétés après $archivedAt ne sont pas des colonnes de la table Agents :
 * elles sont renseignées quand la requête fait les jointures (archétype, tier,
 * équipe actuelle, portrait principal), et restent null sinon.
 */
final class Agent
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?DateTimeImmutable $dob,
        public readonly int $archetypeId,
        public readonly int $tierId,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $archivedAt = null,
        public readonly ?string $archetype = null,
        public readonly ?string $tier = null,
        public readonly ?int $tierLevel = null,
        public readonly ?int $teamId = null,
        public readonly ?string $teamName = null,
        public readonly ?string $portrait = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['Id_Agent'],
            (string) $row['name'],
            to_date($row['dob'] ?? null),
            (int) $row['Id_Archetype'],
            (int) $row['Id_Tier'],
            to_date($row['created_at'] ?? null),
            to_date($row['archived_at'] ?? null),
            isset($row['archetype']) ? (string) $row['archetype'] : null,
            isset($row['tier']) ? (string) $row['tier'] : null,
            isset($row['tier_level']) ? (int) $row['tier_level'] : null,
            isset($row['team_id']) ? (int) $row['team_id'] : null,
            isset($row['team_name']) ? (string) $row['team_name'] : null,
            isset($row['portrait']) ? (string) $row['portrait'] : null,
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /** Numéro de dossier affiché sur la fiche (ex. AG-0005). */
    public function fileNumber(): string
    {
        return sprintf('AG-%04d', (int) $this->id);
    }
}
