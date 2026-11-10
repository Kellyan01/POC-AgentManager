<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Core\Request;
use App\Core\Response;
use App\Repository\ReferenceDefinition;

/**
 * Accueil de l'administration (route réservée aux administrateurs).
 */
final class AdminController extends AbstractController
{
    public function index(Request $request): Response
    {
        return $this->render('admin/index', [
            'title'      => 'Administration',
            'references' => ReferenceDefinition::catalog(),
        ]);
    }
}
