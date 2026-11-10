<?php

declare(strict_types=1);

/*
 * Front controller : toutes les requêtes passent par ce fichier.
 * Lancement en local : php -S localhost:8000 -t public
 */

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var App\Core\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

$app->handle(App\Core\Request::fromGlobals())->send();
