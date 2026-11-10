<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Réponse HTTP renvoyée par un contrôleur.
 *
 * Les contrôleurs ne font jamais « echo » ni « header() » : ils construisent une
 * Response, et c'est le front controller qui l'envoie. Le code reste testable.
 */
final class Response
{
    /**
     * En-têtes de sécurité ajoutés à toutes les réponses.
     * La CSP n'autorise que les ressources du site : aucun script ni style inline.
     */
    private const SECURITY_HEADERS = [
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
        'X-Content-Type-Options'  => 'nosniff',
        'Referrer-Policy'         => 'same-origin',
    ];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    /**
     * Redirection. 303 « See Other » est le code adapté après un POST :
     * le navigateur enchaîne avec un GET (motif Post/Redirect/Get).
     */
    public static function redirect(string $url, int $status = 303): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->status);

        $headers = $this->headers + self::SECURITY_HEADERS + ['Content-Type' => 'text/html; charset=UTF-8'];
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
