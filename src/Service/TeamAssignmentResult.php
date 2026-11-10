<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Compte rendu de l'affectation d'une équipe à une opération (décision Q4) :
 * les membres en conflit sont écartés, l'équipe peut donc partir incomplète.
 */
final class TeamAssignmentResult
{
    /**
     * @param list<string>          $added           membres copiés en participants
     * @param list<string>          $alreadyIncluded membres qui participaient déjà
     * @param array<string, string> $skipped         nom de l'agent => opérations en conflit
     */
    public function __construct(
        public readonly array $added,
        public readonly array $alreadyIncluded,
        public readonly array $skipped,
    ) {
    }
}
