<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Status;

/**
 * Description d'un référentiel à libellé unique : table, colonne, libellés...
 *
 * Un seul ReferenceRepository et un seul contrôleur servent ainsi six tables
 * de même structure (Id + libellé), au lieu de six copies presque identiques.
 * Les noms de table et de colonne sont écrits ici, dans le code : jamais saisis.
 */
final class ReferenceDefinition
{
    /**
     * @param string       $slug         segment d'URL (/admin/{slug})
     * @param string|null  $abilityTable table de liaison vers Abilities (archétypes, formations)
     * @param list<string> $lockedLabels libellés utilisés par le code : ni renommés ni supprimés
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $table,
        public readonly string $primaryKey,
        public readonly string $column,
        public readonly int $maxLength,
        public readonly string $title,
        public readonly string $fieldLabel,
        public readonly ?string $abilityTable = null,
        public readonly array $lockedLabels = [],
        public readonly bool $orderById = false,
    ) {
    }

    /**
     * Catalogue des référentiels gérés par l'écran générique.
     *
     * @return array<string, self> indexé par slug
     */
    public static function catalog(): array
    {
        $definitions = [
            new self('abilities', 'Abilities', 'Id_Ability', 'ability', 50, 'Compétences', 'Compétence'),
            new self('archetypes', 'Archetypes', 'Id_Archetype', 'archetype', 50, 'Archétypes', 'Archétype', 'Archetypes_Abilities'),
            new self('formations', 'Formations', 'Id_Formation', 'formation', 100, 'Formations', 'Formation', 'Formations_Abilities'),
            new self('statuses', 'Status', 'Id_Status', 'status', 30, 'Statuts d\'opération', 'Statut', null, Status::LOCKED, true),
            new self('missions', 'Missions', 'Id_Mission', 'mission', 50, 'Types de mission', 'Type de mission'),
            new self('locations', 'Locations', 'Id_Location', 'location', 100, 'Lieux', 'Lieu'),
        ];

        $catalog = [];
        foreach ($definitions as $definition) {
            $catalog[$definition->slug] = $definition;
        }

        return $catalog;
    }

    public function hasAbilities(): bool
    {
        return $this->abilityTable !== null;
    }

    public function isLocked(string $label): bool
    {
        return in_array($label, $this->lockedLabels, true);
    }
}
