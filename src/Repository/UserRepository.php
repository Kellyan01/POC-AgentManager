<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ReferenceItem;
use App\Model\User;

/**
 * @extends AbstractRepository<User>
 */
final class UserRepository extends AbstractRepository implements ArchivableRepositoryInterface
{
    protected const TABLE = 'Users';
    protected const PRIMARY_KEY = 'Id_User';

    private const SELECT = 'SELECT u.*, r.role FROM Users u JOIN Roles r ON r.Id_Role = u.Id_Role';

    protected function hydrate(array $row): User
    {
        return User::fromRow($row);
    }

    public function findById(int $id): ?User
    {
        $row = $this->fetchOne(self::SELECT . ' WHERE u.Id_User = :id', ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->fetchOne(self::SELECT . ' WHERE u.email = :email', ['email' => $email]);

        return $row === null ? null : $this->hydrate($row);
    }

    /** Tous les utilisateurs, archivés compris (écran d'administration). @return list<User> */
    public function findAll(): array
    {
        $rows = $this->fetchAll(self::SELECT . ' ORDER BY u.archived_at IS NOT NULL, u.lastname, u.firstname');

        return array_map($this->hydrate(...), $rows);
    }

    /** Utilisateurs actifs (listes de sélection, ex. manager d'une équipe). @return list<User> */
    public function findActive(): array
    {
        $rows = $this->fetchAll(self::SELECT . ' WHERE u.archived_at IS NULL ORDER BY u.lastname, u.firstname');

        return array_map($this->hydrate(...), $rows);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM Users WHERE email = :email AND Id_User <> :except',
            ['email' => $email, 'except' => $exceptId ?? 0],
        ) !== null;
    }

    public function insert(object $entity): int
    {
        $user = $this->expect($entity, User::class);

        $this->execute(
            'INSERT INTO Users (firstname, lastname, email, password, Id_Role)
             VALUES (:firstname, :lastname, :email, :password, :role)',
            [
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'email'     => $user->email,
                'password'  => $user->passwordHash,
                'role'      => $user->roleId,
            ],
        );

        return (int) $this->pdo->lastInsertId();
    }

    /** Met à jour l'identité et le rôle. Le mot de passe a sa propre méthode. */
    public function update(object $entity): void
    {
        $user = $this->expect($entity, User::class);

        $this->execute(
            'UPDATE Users SET firstname = :firstname, lastname = :lastname, email = :email, Id_Role = :role
             WHERE Id_User = :id',
            [
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'email'     => $user->email,
                'role'      => $user->roleId,
                'id'        => $user->id,
            ],
        );
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->execute('UPDATE Users SET password = :password WHERE Id_User = :id', [
            'password' => $passwordHash,
            'id'       => $id,
        ]);
    }

    public function archive(int $id): void
    {
        $this->execute('UPDATE Users SET archived_at = NOW() WHERE Id_User = :id AND archived_at IS NULL', ['id' => $id]);
    }

    /** Vrai si l'utilisateur est manager d'au moins une équipe non archivée. */
    public function managesActiveTeams(int $id): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM Teams WHERE Id_User = :id AND archived_at IS NULL LIMIT 1',
            ['id' => $id],
        ) !== null;
    }

    /** @return list<ReferenceItem> */
    public function findRoles(): array
    {
        $rows = $this->fetchAll('SELECT Id_Role, role FROM Roles ORDER BY Id_Role');

        return array_map(
            static fn (array $row): ReferenceItem => new ReferenceItem((int) $row['Id_Role'], (string) $row['role']),
            $rows,
        );
    }
}
