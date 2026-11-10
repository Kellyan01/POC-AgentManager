<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Team;

/**
 * @extends AbstractRepository<Team>
 */
final class TeamRepository extends AbstractRepository implements ArchivableRepositoryInterface
{
    protected const TABLE = 'Teams';
    protected const PRIMARY_KEY = 'Id_Team';

    /** Équipe + nom de son manager + nombre de membres actuels. */
    private const SELECT = <<<'SQL'
        SELECT t.*, CONCAT(u.firstname, ' ', u.lastname) AS manager_name,
               (SELECT COUNT(*) FROM Memberships m
                WHERE m.Id_Team = t.Id_Team AND m.date_leave IS NULL) AS member_count
        FROM Teams t
        JOIN Users u ON u.Id_User = t.Id_User
        SQL;

    protected function hydrate(array $row): Team
    {
        return Team::fromRow($row);
    }

    /** Équipe, archivée ou non (consultable depuis les historiques). */
    public function findById(int $id): ?Team
    {
        $row = $this->fetchOne(self::SELECT . ' WHERE t.Id_Team = :id', ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<Team> équipes actives (les archivées disparaissent des listes) */
    public function findAll(): array
    {
        return array_map($this->hydrate(...), $this->fetchAll(self::SELECT . ' WHERE t.archived_at IS NULL ORDER BY t.team_name'));
    }

    /** @return list<Team> équipes actives dont l'utilisateur est le manager */
    public function findManagedBy(int $userId): array
    {
        $rows = $this->fetchAll(
            self::SELECT . ' WHERE t.archived_at IS NULL AND t.Id_User = :user ORDER BY t.team_name',
            ['user' => $userId],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM Teams WHERE team_name = :name AND Id_Team <> :except',
            ['name' => $name, 'except' => $exceptId ?? 0],
        ) !== null;
    }

    public function insert(object $entity): int
    {
        $team = $this->expect($entity, Team::class);
        $this->execute('INSERT INTO Teams (team_name, Id_User) VALUES (:name, :manager)', [
            'name'    => $team->name,
            'manager' => $team->managerId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(object $entity): void
    {
        $team = $this->expect($entity, Team::class);
        $this->execute('UPDATE Teams SET team_name = :name, Id_User = :manager WHERE Id_Team = :id', [
            'name'    => $team->name,
            'manager' => $team->managerId,
            'id'      => $team->id,
        ]);
    }

    /**
     * Pose uniquement la date d'archivage. La clôture des appartenances, qui doit
     * se faire dans la même transaction, est orchestrée par MembershipService::archiveTeam().
     */
    public function archive(int $id): void
    {
        $this->execute('UPDATE Teams SET archived_at = NOW() WHERE Id_Team = :id AND archived_at IS NULL', ['id' => $id]);
    }
}
