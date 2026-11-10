<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Libellés des statuts d'opération sur lesquels repose le code.
 *
 * Ils sont référencés par leur libellé (comme dans la requête 6 du fichier de
 * requêtes). Pour éviter qu'un renommage casse le staffing sans erreur visible,
 * ces libellés sont verrouillés dans l'écran des référentiels.
 */
final class Status
{
    public const PLANNED = 'Planifiée';
    public const IN_PROGRESS = 'En cours';
    public const SUCCEEDED = 'Réussie';
    public const FAILED = 'Échouée';
    public const CANCELLED = 'Annulée';

    /** Libellés utilisés par des règles métier : ni renommage ni suppression. */
    public const LOCKED = [self::PLANNED, self::IN_PROGRESS, self::CANCELLED];

    /** Modificateur CSS du voyant de statut (la couleur ne fait que renforcer le libellé). */
    public static function cssModifier(string $status): string
    {
        return match ($status) {
            self::PLANNED     => 'planned',
            self::IN_PROGRESS => 'active',
            self::SUCCEEDED   => 'success',
            self::FAILED      => 'failed',
            self::CANCELLED   => 'cancelled',
            default           => 'planned',
        };
    }
}
