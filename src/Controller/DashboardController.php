<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;

/**
 * Tableau de bord (page d'accueil après connexion).
 */
final class DashboardController extends AbstractController
{
    public function index(Request $request): Response
    {
        return $this->render('dashboard/index', ['title' => 'Tableau de bord']);
    }
}
