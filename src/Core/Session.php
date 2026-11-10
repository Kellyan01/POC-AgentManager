<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Accès à la session ($_SESSION) et aux messages flash.
 *
 * Un message flash est stocké en session puis affiché une seule fois, à la page
 * suivante : c'est ce qui permet d'annoncer « Agent créé » après une redirection.
 */
final class Session
{
    private const FLASH_KEY = '_flash';

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Refuse les identifiants de session inventés par un attaquant (fixation de session).
        ini_set('session.use_strict_mode', '1');
        session_name('AGENTMANAGER');
        session_set_cookie_params([
            'lifetime' => 0,            // cookie de session : supprimé à la fermeture du navigateur
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,         // inaccessible depuis JavaScript
            'samesite' => 'Lax',        // non envoyé par les formulaires d'autres sites
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Nouvel identifiant de session, l'ancien est supprimé (à faire à chaque connexion). */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /** Détruit complètement la session : données, cookie et fichier côté serveur. */
    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }

        session_destroy();
    }

    /**
     * @param 'success'|'error'|'warning' $type
     */
    public function flash(string $type, string $message): void
    {
        $_SESSION[self::FLASH_KEY][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Rend les messages flash en attente et les efface.
     *
     * @return list<array{type: string, message: string}>
     */
    public function takeFlashes(): array
    {
        $flashes = $_SESSION[self::FLASH_KEY] ?? [];
        unset($_SESSION[self::FLASH_KEY]);

        return $flashes;
    }
}
