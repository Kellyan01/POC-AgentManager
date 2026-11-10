<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Niveau d'accès exigé par une route. Vérifié par App avant d'appeler le contrôleur.
 *
 * Les droits plus fins (« manager de CETTE équipe », « déclarant de CETTE opération »)
 * dépendent des données : ils sont vérifiés dans les contrôleurs, via Auth.
 */
enum Access
{
    /** Réservé aux visiteurs non connectés (page de connexion). */
    case Guest;
    /** Tout utilisateur connecté, quel que soit son rôle. */
    case Authenticated;
    /** Administrateurs uniquement. */
    case Admin;
}
