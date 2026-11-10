<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Membership;

/**
 * Historique des appartenances aux équipes.
 *
 * Les opérations qui touchent plusieurs lignes ou plusieurs tables (changer un
 * agent d'équipe, archiver une équipe) sont orchestrées par MembershipService,
 * qui gère la transaction. Ce repository ne fait que des requêtes unitaires.
 *
 * @extends AbstractRepository<Membership>
 */
final class MembershipRepository extends AbstractRepository
{
    protected const TABLE = 'Memberships';
    protected const PRIMARY_KEY = 'Id_Membership';

    protected function hydrate(array $row): Membership
    {
        return Membership::fromRow($row);
    }

    public function findById(int $id): ?Membership
    {
        return parent::findById($id);
    }

    /** Appartenance en cours d'un agent, ou null s'il n'a pas d'équipe. */
    public function findCurrentForAgent(int $agentId): ?Membership
    {
        $row = $this->fetchOne(
            'SELECT * FROM Memberships WHERE Id_Agent = :agent AND date_leave IS NULL',
            ['agent' => $agentId],
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Parcours d'un agent dans les équipes (requête 2 du fichier de requêtes).
     *
     * @return list<array{team_id: int, team_name: string, team_archived: bool, date_join: \DateTimeImmutable, date_leave: ?\DateTimeImmutable}>
     */
    public function findHistoryForAgent(int $agentId): array
    {
        $rows = $this->fetchAll(
            'SELECT t.Id_Team, t.team_name, t.archived_at, m.date_join, m.date_leave
             FROM Memberships m
             JOIN Teams t ON t.Id_Team = m.Id_Team
             WHERE m.Id_Agent = :agent
             ORDER BY m.date_join DESC',
            ['agent' => $agentId],
        );

        return array_map(static fn (array $row): array => [
            'team_id'       => (int) $row['Id_Team'],
            'team_name'     => (string) $row['team_name'],
            'team_archived' => $row['archived_at'] !== null,
            'date_join'     => new \DateTimeImmutable((string) $row['date_join']),
            'date_leave'    => to_date($row['date_leave']),
        ], $rows);
    }

    /**
     * Membres d'une équipe : actuels (date_leave NULL) ou anciens.
     *
     * @return list<array{agent_id: int, name: string, archived: bool, tier: string, archetype: string, date_join: \DateTimeImmutable, date_leave: ?\DateTimeImmutable}>
     */
    public function findMembersOfTeam(int $teamId, bool $current): array
    {
        $rows = $this->fetchAll(
            'SELECT a.Id_Agent, a.name, a.archived_at, t.tier, ar.archetype, m.date_join, m.date_leave
             FROM Memberships m
             JOIN Agents a      ON a.Id_Agent      = m.Id_Agent
             JOIN Tiers t       ON t.Id_Tier       = a.Id_Tier
             JOIN Archetypes ar ON ar.Id_Archetype = a.Id_Archetype
             WHERE m.Id_Team = :team AND m.date_leave IS ' . ($current ? 'NULL' : 'NOT NULL') . '
             ORDER BY ' . ($current ? 'a.name' : 'm.date_leave DESC'),
            ['team' => $teamId],
        );

        return array_map(static fn (array $row): array => [
            'agent_id'   => (int) $row['Id_Agent'],
            'name'       => (string) $row['name'],
            'archived'   => $row['archived_at'] !== null,
            'tier'       => (string) $row['tier'],
            'archetype'  => (string) $row['archetype'],
            'date_join'  => new \DateTimeImmutable((string) $row['date_join']),
            'date_leave' => to_date($row['date_leave']),
        ], $rows);
    }

    /** @return list<int> identifiants des membres actuels d'une équipe */
    public function currentMemberIds(int $teamId): array
    {
        $rows = $this->fetchAll(
            'SELECT Id_Agent FROM Memberships WHERE Id_Team = :team AND date_leave IS NULL',
            ['team' => $teamId],
        );

        return array_map(static fn (array $row): int => (int) $row['Id_Agent'], $rows);
    }

    /** @return list<Membership> */
    public function findAll(): array
    {
        return parent::findAll();
    }

    /** Ouvre une appartenance (date_join renseignée par l'entité). */
    public function insert(object $entity): int
    {
        $membership = $this->expect($entity, Membership::class);
        $this->execute(
            'INSERT INTO Memberships (date_join, date_leave, Id_Agent, Id_Team) VALUES (:join, :leave, :agent, :team)',
            [
                'join'  => $membership->dateJoin->format('Y-m-d H:i:s'),
                'leave' => $membership->dateLeave?->format('Y-m-d H:i:s'),
                'agent' => $membership->agentId,
                'team'  => $membership->teamId,
            ],
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function update(object $entity): void
    {
        $membership = $this->expect($entity, Membership::class);
        $this->execute(
            'UPDATE Memberships SET date_join = :join, date_leave = :leave WHERE Id_Membership = :id',
            [
                'join'  => $membership->dateJoin->format('Y-m-d H:i:s'),
                'leave' => $membership->dateLeave?->format('Y-m-d H:i:s'),
                'id'    => $membership->id,
            ],
        );
    }

    /**
     * Ouvre une appartenance qui commence maintenant. NOW() est évalué par MySQL,
     * comme dans closeCurrentForAgent() : les deux dates viennent de la même horloge.
     */
    public function open(int $agentId, int $teamId): int
    {
        $this->execute(
            'INSERT INTO Memberships (date_join, Id_Agent, Id_Team) VALUES (NOW(), :agent, :team)',
            ['agent' => $agentId, 'team' => $teamId],
        );

        return (int) $this->pdo->lastInsertId();
    }

    /** Clôt l'appartenance en cours d'un agent (sans effet s'il n'en a pas). */
    public function closeCurrentForAgent(int $agentId): int
    {
        return $this->execute(
            'UPDATE Memberships SET date_leave = NOW() WHERE Id_Agent = :agent AND date_leave IS NULL',
            ['agent' => $agentId],
        );
    }

    /** Clôt toutes les appartenances en cours d'une équipe. Renvoie le nombre de membres libérés. */
    public function closeAllForTeam(int $teamId): int
    {
        return $this->execute(
            'UPDATE Memberships SET date_leave = NOW() WHERE Id_Team = :team AND date_leave IS NULL',
            ['team' => $teamId],
        );
    }
}
