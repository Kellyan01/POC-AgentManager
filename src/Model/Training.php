<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Session de formation suivie par un agent. Un agent peut repasser une formation :
 * chaque tentative est une ligne distincte.
 *
 * isPassed : null = en cours, true = réussie, false = échouée.
 */
final class Training
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $agentId,
        public readonly int $formationId,
        public readonly DateTimeImmutable $dateBeg,
        public readonly ?DateTimeImmutable $dateEnd = null,
        public readonly ?bool $isPassed = null,
        public readonly ?string $formation = null,
        public readonly ?string $agentName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['Id_Training'],
            (int) $row['Id_Agent'],
            (int) $row['Id_Formation'],
            new DateTimeImmutable((string) $row['date_beg']),
            to_date($row['date_end'] ?? null),
            $row['is_passed'] === null ? null : (bool) $row['is_passed'],
            isset($row['formation']) ? (string) $row['formation'] : null,
            isset($row['agent_name']) ? (string) $row['agent_name'] : null,
        );
    }

    /** Libellé du résultat, comme dans la requête 5 du fichier de requêtes. */
    public function resultLabel(): string
    {
        return match ($this->isPassed) {
            null  => 'En cours',
            true  => 'Réussie',
            false => 'Échouée',
        };
    }

    /** Modificateur CSS du voyant (le libellé est toujours affiché à côté). */
    public function resultModifier(): string
    {
        return match ($this->isPassed) {
            null  => 'planned',
            true  => 'success',
            false => 'failed',
        };
    }
}
