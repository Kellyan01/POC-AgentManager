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

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
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

// --- Services ------------------------------------------------------------------
$auth = new Auth($session, $csrf, $userRepository);

// --- Contrôleurs -----------------------------------------------------------------
$controllers = [
    AuthController::class      => static fn () => new AuthController($view, $session, $auth),
    DashboardController::class => static fn () => new DashboardController($view, $session, $auth),
];

// --- Routes ----------------------------------------------------------------------
$router = new Router();
(require $root . '/config/routes.php')($router);

return new App($router, $controllers, $session, $csrf, $auth, $view, (bool) $config['app']['debug']);
