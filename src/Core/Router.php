<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\HttpException;

/**
 * Routeur : associe une requête (méthode + chemin) à une Route.
 *
 * La table des routes est déclarée dans config/routes.php.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string} $handler  [Contrôleur::class, 'méthode']
     * @param array<string, mixed>               $defaults
     */
    public function get(string $pattern, array $handler, Access $access = Access::Authenticated, array $defaults = []): void
    {
        $this->routes[] = new Route('GET', $pattern, $handler[0], $handler[1], $access, $defaults);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array<string, mixed>               $defaults
     */
    public function post(string $pattern, array $handler, Access $access = Access::Authenticated, array $defaults = []): void
    {
        $this->routes[] = new Route('POST', $pattern, $handler[0], $handler[1], $access, $defaults);
    }

    /**
     * @return array{0: Route, 1: array<string, mixed>} la route et ses paramètres
     *
     * @throws HttpException 404 si aucun motif ne correspond, 405 si seule la méthode diffère
     */
    public function match(string $method, string $path): array
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $params = $route->match($path);
            if ($params === null) {
                continue;
            }
            if ($route->method === $method) {
                return [$route, $params];
            }
            $allowedMethods[] = $route->method;
        }

        if ($allowedMethods !== []) {
            throw new HttpException(405, 'Méthode non autorisée pour cette adresse.');
        }

        throw HttpException::notFound();
    }
}
