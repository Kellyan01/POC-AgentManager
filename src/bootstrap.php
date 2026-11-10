<?php

declare(strict_types=1);

/*
 * Racine de composition : le SEUL endroit de l'application qui fait des « new ».
 *
 * On y lit la configuration, on crée la connexion PDO une seule fois, puis on
 * construit les objets en leur passant leurs dépendances par le constructeur
 * (injection de dépendances). En lisant ce fichier, on voit qui dépend de quoi.
 *
 * Les contrôleurs sont déclarés sous forme de fabriques (closures) : seul celui
 * de la route demandée est réellement instancié.
 */

use App\Controller\Admin\AdminController;
use App\Controller\Admin\ReferenceController;
use App\Controller\Admin\TierController;
use App\Controller\Admin\UserController;
use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Repository\ReferenceDefinition;
use App\Repository\ReferenceRepository;
use App\Repository\TierRepository;
use App\Repository\UserRepository;

$root = dirname(__DIR__);

$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    exit('Configuration absente : copiez config/config.example.php en config/config.php.');
}
$config = require $configFile;

date_default_timezone_set($config['app']['timezone']);

// --- Infrastructure ------------------------------------------------------------
$pdo     = Database::create($config['db']);
$session = new Session();
$csrf    = new Csrf($session);
$view    = new View($root . '/views', $session, $csrf);

// --- Repositories (chacun reçoit le même PDO) ----------------------------------
$userRepository = new UserRepository($pdo);
$tierRepository = new TierRepository($pdo);

// Un ReferenceRepository par référentiel à libellé unique (compétences, lieux...)
$references = array_map(
    static fn (ReferenceDefinition $definition) => new ReferenceRepository($pdo, $definition),
    ReferenceDefinition::catalog(),
);

// --- Services ------------------------------------------------------------------
$auth = new Auth($session, $csrf, $userRepository);

// --- Contrôleurs -----------------------------------------------------------------
$controllers = [
    AuthController::class      => static fn () => new AuthController($view, $session, $auth),
    DashboardController::class => static fn () => new DashboardController($view, $session, $auth),

    AdminController::class     => static fn () => new AdminController($view, $session, $auth),
    UserController::class      => static fn () => new UserController($view, $session, $auth, $userRepository),
    TierController::class      => static fn () => new TierController($view, $session, $auth, $tierRepository),
    ReferenceController::class => static fn () => new ReferenceController($view, $session, $auth, $references),
];

// --- Routes ----------------------------------------------------------------------
$router = new Router();
(require $root . '/config/routes.php')($router);

return new App($router, $controllers, $session, $csrf, $auth, $view, (bool) $config['app']['debug']);
