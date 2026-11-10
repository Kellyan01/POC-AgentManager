<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Fabrique de connexion PDO.
 *
 * Ce n'est PAS un Singleton : la classe ne garde aucune instance en mémoire.
 * create() est appelée une seule fois, dans src/bootstrap.php, et la connexion
 * obtenue est ensuite injectée dans chaque repository par son constructeur.
 * Avantage : les dépendances sont visibles, et on peut fournir une autre
 * connexion (base de test, par exemple) sans toucher aux repositories.
 */
final class Database
{
    /**
     * @param array{host: string, port: int, name: string, user: string, password: string} $config
     */
    public static function create(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['name'],
        );

        return new PDO($dsn, $config['user'], $config['password'], [
            // Toute erreur SQL lève une PDOException : impossible de l'ignorer par oubli.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Les lignes sont rendues sous forme de tableaux associatifs.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Vraies requêtes préparées côté serveur (pas d'émulation par PDO).
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
