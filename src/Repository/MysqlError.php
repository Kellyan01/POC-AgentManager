<?php

declare(strict_types=1);

namespace App\Repository;

use PDOException;

/**
 * Codes d'erreur MySQL utiles pour traduire une violation de contrainte en
 * message compréhensible (au lieu d'afficher l'erreur SQL brute).
 */
final class MysqlError
{
    /** Doublon sur un index UNIQUE (ex. deux appartenances en cours pour un agent). */
    public const DUPLICATE_ENTRY = 1062;

    /** Suppression impossible : la ligne est référencée par une clé étrangère (RESTRICT). */
    public const ROW_IS_REFERENCED = 1451;

    public static function is(PDOException $exception, int $code): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === $code;
    }
}
