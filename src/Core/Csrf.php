<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protection contre la falsification de requête inter-sites (CSRF).
 *
 * Chaque formulaire POST embarque un jeton secret stocké en session. Un site
 * malveillant peut faire envoyer un formulaire par le navigateur de la victime,
 * mais il ne connaît pas ce jeton : la requête est alors refusée.
 */
final class Csrf
{
    public const FIELD = '_csrf';
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        if (!$this->session->has(self::SESSION_KEY)) {
            $this->session->set(self::SESSION_KEY, bin2hex(random_bytes(32)));
        }

        return (string) $this->session->get(self::SESSION_KEY);
    }

    public function isValid(?string $token): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);

        // hash_equals compare en temps constant : pas de fuite d'information par la durée.
        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }

    /** Nouveau jeton (à la connexion, en même temps que le nouvel identifiant de session). */
    public function regenerate(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }
}
