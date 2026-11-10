<?php

declare(strict_types=1);

/**
 * Configuration de l'application.
 *
 * Copier ce fichier en « config/config.php » puis adapter les valeurs.
 * config.php est ignoré par Git : les identifiants ne doivent jamais être versionnés.
 */
return [
    'app' => [
        'name'     => 'Agent Manager',
        // true en développement : affiche le détail des erreurs. TOUJOURS false en production.
        'debug'    => true,
        'timezone' => 'Europe/Paris',
    ],
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'agent_manager_v3',
        'user'     => 'root',
        'password' => '',
    ],
];
