<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Utilisateur de l'application (Administrateur ou Manager).
 */
final class User
{
    /** Libellés des rôles tels qu'ils existent dans la table Roles. */
    public const ROLE_ADMIN = 'Administrateur';
    public const ROLE_MANAGER = 'Manager';

    public function __construct(
        public readonly ?int $id,
        public readonly string $firstname,
        public readonly string $lastname,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly int $roleId,
        public readonly ?string $role = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $archivedAt = null,
    ) {
    }

    /** @param array<string, mixed> $row ligne de Users, éventuellement jointe à Roles */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['Id_User'],
            (string) $row['firstname'],
            (string) $row['lastname'],
            (string) $row['email'],
            (string) $row['password'],
            (int) $row['Id_Role'],
            isset($row['role']) ? (string) $row['role'] : null,
            to_date($row['created_at'] ?? null),
            to_date($row['archived_at'] ?? null),
        );
    }

    public function fullName(): string
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}
