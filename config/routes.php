<?php

declare(strict_types=1);

/*
 * Table des routes : méthode + URL → [Contrôleur, méthode], avec le niveau d'accès.
 *
 * Niveau par défaut : Access::Authenticated (tout utilisateur connecté).
 * Les droits qui dépendent des données (manager de l'équipe, déclarant de
 * l'opération) sont vérifiés dans le contrôleur.
 *
 * Aucune action qui modifie des données n'est accessible en GET.
 */

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Core\Access;
use App\Core\Router;

return static function (Router $router): void {
    // --- Authentification ---------------------------------------------------
    $router->get('/login', [AuthController::class, 'showLogin'], Access::Guest);
    $router->post('/login', [AuthController::class, 'login'], Access::Guest);
    $router->post('/logout', [AuthController::class, 'logout']);

    // --- Tableau de bord ----------------------------------------------------
    $router->get('/', [DashboardController::class, 'index']);
};
