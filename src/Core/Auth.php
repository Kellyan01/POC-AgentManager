<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\HttpException;
use App\Model\Operation;
use App\Model\Team;
use App\Model\User;
use App\Repository\UserRepository;

/**
 * Authentification (qui est connecté ?) et autorisations (a-t-il le droit ?).
 *
 * Seul l'identifiant de l'utilisateur est gardé en session. L'utilisateur est
 * rechargé depuis la base à chaque requête : un compte archivé pendant qu'il est
 * connecté perd donc immédiatement l'accès.
 */
final class Auth
{
    private const SESSION_KEY = 'user_id';

    /** Hachage bcrypt sans compte associé, utilisé quand l'e-mail est inconnu (voir attempt()). */
    private const DUMMY_HASH = '$2y$10$akSToRav/rioSEvUp3SIiegakldnkR1YFH5GGkmtorgaijhTVPskm';

    private ?User $user = null;
    private bool $loaded = false;

    public function __construct(
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly UserRepository $users,
    ) {
    }

    /** Utilisateur connecté, ou null. */
    public function user(): ?User
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $id = $this->session->get(self::SESSION_KEY);
            $user = is_int($id) ? $this->users->findById($id) : null;

            if ($user !== null && !$user->isArchived()) {
                $this->user = $user;
            } elseif ($id !== null) {
                $this->session->remove(self::SESSION_KEY);
            }
        }

        return $this->user;
    }

    /**
     * Vérifie les identifiants. Renvoie l'utilisateur si tout est correct, null sinon.
     * Un compte archivé est refusé comme un mauvais mot de passe : on ne révèle
     * pas à un inconnu quels comptes existent.
     */
    public function attempt(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            // On calcule quand même un hachage pour que la durée de réponse ne
            // trahisse pas l'existence (ou non) de l'adresse e-mail.
            password_verify($password, self::DUMMY_HASH);

            return null;
        }

        if (!password_verify($password, $user->passwordHash) || $user->isArchived()) {
            return null;
        }

        // Si l'algorithme par défaut de PHP a évolué, on en profite pour re-hacher.
        if (password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT)) {
            $this->users->updatePassword((int) $user->id, password_hash($password, PASSWORD_DEFAULT));
        }

        return $user;
    }

    public function login(User $user): void
    {
        // Nouvel identifiant de session : empêche la fixation de session.
        $this->session->regenerate();
        $this->csrf->regenerate();
        $this->session->set(self::SESSION_KEY, $user->id);
        $this->user = $user;
        $this->loaded = true;
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
    }

    /** Utilisateur connecté ; erreur si personne ne l'est (ne doit pas arriver derrière une route protégée). */
    public function requireUser(): User
    {
        return $this->user() ?? throw HttpException::forbidden();
    }

    public function isAdmin(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** Administrateur, ou Manager responsable de cette équipe (Teams.Id_User). */
    public function canManageTeam(Team $team): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $team->managerId === $user->id);
    }

    /** Administrateur, ou Manager qui a déclaré cette opération (Operations.Id_User). */
    public function canEditOperation(Operation $operation): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $operation->declarantId === $user->id);
    }

    /** @throws HttpException 403 si l'utilisateur n'est pas administrateur */
    public function denyUnlessAdmin(): void
    {
        if (!$this->isAdmin()) {
            throw HttpException::forbidden();
        }
    }
}
