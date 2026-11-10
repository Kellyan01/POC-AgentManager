<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Valeur d'un référentiel à libellé unique : compétence, archétype, formation,
 * statut, type de mission, lieu, rôle.
 */
final class ReferenceItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $label,
    ) {
    }
}
