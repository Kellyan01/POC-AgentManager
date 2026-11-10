<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Agent;
use App\Model\Status;

/**
 * @extends AbstractRepository<Agent>
 */
final class AgentRepository extends AbstractRepository implements ArchivableRepositoryInterface
{
    protected const TABLE = 'Agents';
    protected const PRIMARY_KEY = 'Id_Agent';

    /**
     * Agent + archétype + tier + équipe actuelle (vue current_memberships, requête 1)
     * + portrait principal = média le plus récent (requête 8).
     */
    private const SELECT = <<<'SQL'
        SELECT a.*, ar.archetype, t.tier, t.level AS tier_level,
               cm.Id_Team AS team_id, cm.team_name,
               md.url AS portrait
        FROM Agents a
        JOIN Archetypes ar ON ar.Id_Archetype = a.Id_Archetype
        JOIN Tiers      t  ON t.Id_Tier       = a.Id_Tier
        LEFT JOIN current_memberships cm ON cm.Id_Agent = a.Id_Agent
        LEFT JOIN Medias md ON md.Id_Media = (
            SELECT MAX(m2.Id_Media) FROM Medias m2 WHERE m2.Id_Agent = a.Id_Agent
        )
        SQL;

    /** Valeur spéciale du filtre « équipe » : agents sans équipe. */
    public const FILTER_NO_TEAM = 'none';

    protected function hydrate(array $row): Agent
    {
        return Agent::fromRow($row);
    }

    /** Fiche d'un agent, archivé ou non (les archivés restent consultables depuis les historiques). */
    public function findById(int $id): ?Agent
    {
        $row = $this->fetchOne(self::SELECT . ' WHERE a.Id_Agent = :id', ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<Agent> agents en service, par ordre alphabétique */
    public function findAll(): array
    {
        return $this->search([]);
    }

    /**
     * Agents en service, filtrés. Chaque filtre est facultatif.
     *
     * @param array{team?: string, tier?: int, archetype?: int, ability?: int} $filters
     *        team : identifiant d'équipe, ou FILTER_NO_TEAM pour les agents sans équipe
     * @return list<Agent>
     */
    public function search(array $filters): array
    {
        $conditions = ['a.archived_at IS NULL'];
        $params = [];

        if (isset($filters['team'])) {
            if ($filters['team'] === self::FILTER_NO_TEAM) {
                $conditions[] = 'cm.Id_Team IS NULL';
            } else {
                $conditions[] = 'cm.Id_Team = :team';
                $params['team'] = (int) $filters['team'];
            }
        }
        if (isset($filters['tier'])) {
            $conditions[] = 'a.Id_Tier = :tier';
            $params['tier'] = $filters['tier'];
        }
        if (isset($filters['archetype'])) {
            $conditions[] = 'a.Id_Archetype = :archetype';
            $params['archetype'] = $filters['archetype'];
        }
        if (isset($filters['ability'])) {
            // Toutes sources confondues : on passe par la vue agent_abilities.
            $conditions[] = 'EXISTS (SELECT 1 FROM agent_abilities aa WHERE aa.Id_Agent = a.Id_Agent AND aa.Id_Ability = :ability)';
            $params['ability'] = $filters['ability'];
        }

        $sql = self::SELECT . ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY a.name';

        return array_map($this->hydrate(...), $this->fetchAll($sql, $params));
    }

    public function insert(object $entity): int
    {
        $agent = $this->expect($entity, Agent::class);
        $this->execute(
            'INSERT INTO Agents (name, dob, Id_Archetype, Id_Tier) VALUES (:name, :dob, :archetype, :tier)',
            [
                'name'      => $agent->name,
                'dob'       => $agent->dob?->format('Y-m-d'),
                'archetype' => $agent->archetypeId,
                'tier'      => $agent->tierId,
            ],
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function update(object $entity): void
    {
        $agent = $this->expect($entity, Agent::class);
        $this->execute(
            'UPDATE Agents SET name = :name, dob = :dob, Id_Archetype = :archetype, Id_Tier = :tier
             WHERE Id_Agent = :id',
            [
                'name'      => $agent->name,
                'dob'       => $agent->dob?->format('Y-m-d'),
                'archetype' => $agent->archetypeId,
                'tier'      => $agent->tierId,
                'id'        => $agent->id,
            ],
        );
    }

    public function archive(int $id): void
    {
        $this->execute('UPDATE Agents SET archived_at = NOW() WHERE Id_Agent = :id AND archived_at IS NULL', ['id' => $id]);
    }

    /**
     * Noms de code des opérations planifiées ou en cours auxquelles l'agent participe.
     *
     * @return list<string>
     */
    public function activeOperationCodeNames(int $agentId): array
    {
        $rows = $this->fetchAll(
            'SELECT o.code_name
             FROM Operations_Agents oa
             JOIN Operations o ON o.Id_Operation = oa.Id_Operation
             JOIN Status s     ON s.Id_Status    = o.Id_Status
             WHERE oa.Id_Agent = :agent AND s.status IN (:planned, :in_progress)
             ORDER BY o.date_beg',
            ['agent' => $agentId, 'planned' => Status::PLANNED, 'in_progress' => Status::IN_PROGRESS],
        );

        return array_map(static fn (array $row): string => (string) $row['code_name'], $rows);
    }
}
