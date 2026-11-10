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

use App\Controller\Admin\AdminController;
use App\Controller\Admin\ReferenceController;
use App\Controller\Admin\TierController;
use App\Controller\Admin\UserController;
use App\Controller\AgentController;
use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\TeamController;
use App\Core\Access;
use App\Core\Router;
use App\Repository\ReferenceDefinition;

return static function (Router $router): void {
    // --- Authentification ---------------------------------------------------
    $router->get('/login', [AuthController::class, 'showLogin'], Access::Guest);
    $router->post('/login', [AuthController::class, 'login'], Access::Guest);
    $router->post('/logout', [AuthController::class, 'logout']);

    // --- Tableau de bord ----------------------------------------------------
    $router->get('/', [DashboardController::class, 'index']);

    // --- Agents (création / modification : Administrateur et Manager) ---------
    $router->get('/agents', [AgentController::class, 'index']);
    $router->get('/agents/new', [AgentController::class, 'new']);
    $router->post('/agents', [AgentController::class, 'create']);
    $router->get('/agents/{id}', [AgentController::class, 'show']);
    $router->get('/agents/{id}/edit', [AgentController::class, 'edit']);
    $router->post('/agents/{id}', [AgentController::class, 'update']);
    $router->post('/agents/{id}/archive', [AgentController::class, 'archive'], Access::Admin);
    // Changement d'équipe : droits vérifiés dans le contrôleur et MembershipService (décision Q2)
    $router->post('/agents/{id}/team', [AgentController::class, 'assignTeam']);

    // --- Équipes (création / modification / archivage : Administrateur) -------
    $router->get('/teams', [TeamController::class, 'index']);
    $router->get('/teams/new', [TeamController::class, 'new'], Access::Admin);
    $router->post('/teams', [TeamController::class, 'create'], Access::Admin);
    $router->get('/teams/{id}', [TeamController::class, 'show']);
    $router->get('/teams/{id}/edit', [TeamController::class, 'edit'], Access::Admin);
    $router->post('/teams/{id}', [TeamController::class, 'update'], Access::Admin);
    $router->post('/teams/{id}/archive', [TeamController::class, 'archive'], Access::Admin);
    // Membres : Administrateur ou manager de l'équipe (vérifié dans le contrôleur)
    $router->post('/teams/{id}/members', [TeamController::class, 'addMember']);
    $router->post('/teams/{id}/members/{agentId}/remove', [TeamController::class, 'removeMember']);

    // --- Administration (administrateurs uniquement) --------------------------
    $router->get('/admin', [AdminController::class, 'index'], Access::Admin);

    $router->get('/admin/users', [UserController::class, 'index'], Access::Admin);
    $router->get('/admin/users/new', [UserController::class, 'new'], Access::Admin);
    $router->post('/admin/users', [UserController::class, 'create'], Access::Admin);
    $router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], Access::Admin);
    $router->post('/admin/users/{id}', [UserController::class, 'update'], Access::Admin);
    $router->post('/admin/users/{id}/archive', [UserController::class, 'archive'], Access::Admin);

    $router->get('/admin/tiers', [TierController::class, 'index'], Access::Admin);
    $router->post('/admin/tiers', [TierController::class, 'create'], Access::Admin);
    $router->get('/admin/tiers/{id}/edit', [TierController::class, 'edit'], Access::Admin);
    $router->post('/admin/tiers/{id}', [TierController::class, 'update'], Access::Admin);
    $router->post('/admin/tiers/{id}/delete', [TierController::class, 'delete'], Access::Admin);

    // Référentiels à libellé unique : mêmes routes pour chacun, le slug est passé
    // au contrôleur comme paramètre fixe « ref ».
    foreach (array_keys(ReferenceDefinition::catalog()) as $ref) {
        $router->get("/admin/$ref", [ReferenceController::class, 'index'], Access::Admin, ['ref' => $ref]);
        $router->post("/admin/$ref", [ReferenceController::class, 'create'], Access::Admin, ['ref' => $ref]);
        $router->get("/admin/$ref/{id}/edit", [ReferenceController::class, 'edit'], Access::Admin, ['ref' => $ref]);
        $router->post("/admin/$ref/{id}", [ReferenceController::class, 'update'], Access::Admin, ['ref' => $ref]);
        $router->post("/admin/$ref/{id}/delete", [ReferenceController::class, 'delete'], Access::Admin, ['ref' => $ref]);
    }
};
