<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OperationRepository;

/**
 * Aide à la constitution des équipes d'intervention.
 *
 *  - suggestions (règle 9) : agents en service, disponibles, possédant TOUTES
 *    les compétences requises ;
 *  - avertissement de tier (règle 10) : participant dont le tier est inférieur
 *    au tier le plus élevé des menaces visées. Non bloquant.
 */
final class StaffingService
{
    public function __construct(private readonly OperationRepository $operations)
    {
    }

    /**
     * @return list<array{agent_id: int, name: string, tier: string, tier_level: int, team_name: ?string, abilities: string}>
     *         vide si l'opération n'a aucune compétence requise
     */
    public function suggest(int $operationId): array
    {
        return $this->operations->findStaffingCandidates($operationId);
    }

    /**
     * Menace la plus dangereuse visée par l'opération, ou null si aucune.
     *
     * @param list<array{tier: string, tier_level: int}> $threats triées du plus fort au plus faible
     * @return array{tier: string, tier_level: int}|null
     */
    public function strongestThreat(array $threats): ?array
    {
        return $threats[0] ?? null;
    }

    /**
     * Identifiants des participants sous-dimensionnés face à la menace la plus forte.
     *
     * @param list<array{agent_id: int, tier_level: int}> $participants
     * @param array{tier_level: int}|null                 $strongestThreat
     * @return list<int>
     */
    public function underTiered(array $participants, ?array $strongestThreat): array
    {
        if ($strongestThreat === null) {
            return [];
        }

        $ids = [];
        foreach ($participants as $participant) {
            if ($participant['tier_level'] < $strongestThreat['tier_level']) {
                $ids[] = $participant['agent_id'];
            }
        }

        return $ids;
    }
}
