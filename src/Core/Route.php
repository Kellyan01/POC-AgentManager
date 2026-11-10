<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Une route : méthode HTTP + motif d'URL → méthode d'un contrôleur.
 *
 * Les segments variables s'écrivent {nom} et n'acceptent que des chiffres :
 * « /agents/{id} » correspond à « /agents/12 » mais pas à « /agents/abc ».
 */
final class Route
{
    private readonly string $regex;

    /**
     * @param class-string         $controller
     * @param array<string, mixed> $defaults paramètres fixes transmis au contrôleur
     */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly string $controller,
        public readonly string $action,
        public readonly Access $access,
        public readonly array $defaults = [],
    ) {
        $this->regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
    }

    /**
     * Paramètres extraits de l'URL si elle correspond au motif, null sinon.
     *
     * @return array<string, mixed>|null
     */
    public function match(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $name => $value) {
            if (is_string($name)) {
                $params[$name] = (int) $value;
            }
        }

        return $params + $this->defaults;
    }
}
