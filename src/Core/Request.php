<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Requête HTTP entrante.
 *
 * Encapsule les superglobales ($_GET, $_POST, $_FILES, $_SERVER) pour que le reste
 * du code n'y accède jamais directement et reçoive toujours des types sûrs.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query  équivalent de $_GET
     * @param array<string, mixed> $body   équivalent de $_POST
     * @param array<string, mixed> $files  équivalent de $_FILES
     * @param array<string, mixed> $server équivalent de $_SERVER
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $files = [],
        private readonly array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        // « /agents/ » et « /agents » désignent la même page.
        $path = $path === '/' ? '/' : rtrim($path, '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path === '' ? '/' : $path,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** Paramètre de l'URL (?cle=valeur), ou null s'il est absent ou n'est pas une chaîne. */
    public function query(string $key): ?string
    {
        $value = $this->query[$key] ?? null;

        return is_string($value) ? trim($value) : null;
    }

    /** Champ de formulaire (POST), ou null s'il est absent ou n'est pas une chaîne. */
    public function input(string $key): ?string
    {
        $value = $this->body[$key] ?? null;

        return is_string($value) ? trim($value) : null;
    }

    /**
     * Liste d'identifiants envoyée par des cases à cocher (name="missions[]").
     * Les valeurs non numériques sont ignorées.
     *
     * @return list<int>
     */
    public function inputIds(string $key): array
    {
        $values = $this->body[$key] ?? [];
        if (!is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            if (is_string($value) && ctype_digit($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Fichier uploadé (une entrée de $_FILES), ou null si le champ est absent.
     *
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) && isset($file['error']) && !is_array($file['error']) ? $file : null;
    }

    public function server(string $key): ?string
    {
        $value = $this->server[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Vrai quand le navigateur a envoyé un corps de requête plus gros que post_max_size :
     * PHP vide alors $_POST et $_FILES sans lever d'erreur.
     */
    public function exceedsPostMaxSize(): bool
    {
        $length = (int) ($this->server('CONTENT_LENGTH') ?? 0);

        return $this->isPost() && $length > 0 && $this->body === [] && $this->files === [];
    }
}
