<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;

/**
 * Données validées du formulaire d'opération, transmises au service.
 * Regrouper les champs dans un objet évite des méthodes à dix paramètres.
 */
final class OperationData
{
    /**
     * @param list<int> $missions  au moins un
     * @param list<int> $locations au moins un
     * @param list<int> $threats
     * @param list<int> $abilities compétences requises
     */
    public function __construct(
        public readonly string $codeName,
        public readonly DateTimeImmutable $dateBeg,
        public readonly ?DateTimeImmutable $dateEnd,
        public readonly ?string $brief,
        public readonly int $statusId,
        public readonly array $missions,
        public readonly array $locations,
        public readonly array $threats,
        public readonly array $abilities,
    ) {
    }
}
