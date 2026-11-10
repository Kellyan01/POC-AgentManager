<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Operation;
use App\Model\Status;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Opérations et leurs tables de liaison (missions, lieux, menaces, compétences
 * requises, équipes, participants).
 *
 * Les enchaînements de requêtes (création complète, affectation d'une équipe...)
 * sont orchestrés par OperationService, qui gère les transactions.
 *
 * @extends AbstractRepository<Operation>
 */
final class OperationRepository extends AbstractRepository implements DeletableRepositoryInterface
{
    protected const TABLE = 'Operations';
    protected const PRIMARY_KEY = 'Id_Operation';

    /**
     * Tables de liaison « simples » modifiées depuis le formulaire de l'opération.
     * Liste blanche : la clé reçue est traduite ici en nom de table et de colonne.
     */
    private const LINKS = [
        'missions'  => ['Operations_Missions', 'Id_Mission'],
        'locations' => ['Operations_Locations', 'Id_Location'],
        'threats'   => ['Operations_Threats', 'Id_Threat'],
        'abilities' => ['Operations_Abilities', 'Id_Ability'],
    ];

    /** Opération + statut + déclarant + illustration principale (média le plus récent, requête 8). */
    private const SELECT = <<<'SQL'
        SELECT o.*, s.status, CONCAT(u.firstname, ' ', u.lastname) AS declarant, md.url AS illustration
        FROM Operations o
        JOIN Status s ON s.Id_Status = o.Id_Status
        JOIN Users  u ON u.Id_User   = o.Id_User
        LEFT JOIN Medias md ON md.Id_Media = (
            SELECT MAX(m2.Id_Media) FROM Medias m2 WHERE m2.Id_Operation = o.Id_Operation
        )
        SQL;

    /**
     * Condition « l'opération other chevauche l'opération target » (requête 6).
     * Une date de fin absente signifie « en cours, fin inconnue » : on la remplace
     * par une date très lointaine. Les opérations annulées ne comptent pas.
     */
    private const OVERLAP = <<<'SQL'
        other.Id_Operation <> target.Id_Operation
        AND s.status <> :cancelled
        AND other.date_beg < COALESCE(target.date_end, '9999-12-31')
        AND COALESCE(other.date_end, '9999-12-31') > target.date_beg
        SQL;

    protected function hydrate(array $row): Operation
    {
        return Operation::fromRow($row);
    }

    public function findById(int $id): ?Operation
    {
        $row = $this->fetchOne(self::SELECT . ' WHERE o.Id_Operation = :id', ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<Operation> de la plus récente à la plus ancienne */
    public function findAll(): array
    {
        return $this->search(null);
    }

    /** @return list<Operation> filtrées par statut (identifiant), ou toutes */
    public function search(?int $statusId): array
    {
        $sql = self::SELECT . ($statusId !== null ? ' WHERE o.Id_Status = :status' : '') . ' ORDER BY o.date_beg DESC';

        return array_map($this->hydrate(...), $this->fetchAll($sql, $statusId !== null ? ['status' => $statusId] : []));
    }

    /**
     * Opérations ayant l'un des statuts donnés (libellés), par date de début.
     *
     * @param list<string> $statuses
     * @return list<Operation>
     */
    public function findByStatuses(array $statuses): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($statuses) as $i => $status) {
            $placeholders[] = ':status' . $i;
            $params['status' . $i] = $status;
        }

        $sql = self::SELECT . ' WHERE s.status IN (' . implode(', ', $placeholders) . ') ORDER BY o.date_beg';

        return array_map($this->hydrate(...), $this->fetchAll($sql, $params));
    }

    public function codeNameExists(string $codeName, ?int $exceptId = null): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM Operations WHERE code_name = :name AND Id_Operation <> :except',
            ['name' => $codeName, 'except' => $exceptId ?? 0],
        ) !== null;
    }

    /** Le numéro de série est fourni par l'entité (voir SerialGenerator). */
    public function insert(object $entity): int
    {
        $operation = $this->expect($entity, Operation::class);
        $this->execute(
            'INSERT INTO Operations (serial, code_name, date_beg, date_end, brief, Id_Status, Id_User)
             VALUES (:serial, :code_name, :date_beg, :date_end, :brief, :status, :user)',
            $this->params($operation) + ['serial' => $operation->serial, 'user' => $operation->declarantId],
        );

        return (int) $this->pdo->lastInsertId();
    }

    /** Le numéro de série et le déclarant ne changent jamais. */
    public function update(object $entity): void
    {
        $operation = $this->expect($entity, Operation::class);
        $this->execute(
            'UPDATE Operations SET code_name = :code_name, date_beg = :date_beg, date_end = :date_end,
                    brief = :brief, Id_Status = :status
             WHERE Id_Operation = :id',
            $this->params($operation) + ['id' => $operation->id],
        );
    }

    /**
     * Supprime l'opération ; ses liaisons partent en CASCADE.
     * Les médias (RESTRICT) doivent avoir été supprimés avant : voir OperationService::delete().
     */
    public function delete(int $id): void
    {
        $this->execute('DELETE FROM Operations WHERE Id_Operation = :id', ['id' => $id]);
    }

    // --- Liaisons simples (missions, lieux, menaces, compétences requises) -------

    /** @return list<int> */
    public function linkedIds(int $operationId, string $link): array
    {
        [$table, $column] = $this->link($link);
        $rows = $this->fetchAll(sprintf('SELECT %s AS id FROM %s WHERE Id_Operation = :op', $column, $table), ['op' => $operationId]);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** @param list<int> $ids */
    public function replaceLinks(int $operationId, string $link, array $ids): void
    {
        [$table, $column] = $this->link($link);
        $this->execute(sprintf('DELETE FROM %s WHERE Id_Operation = :op', $table), ['op' => $operationId]);

        $statement = $this->pdo->prepare(sprintf('INSERT INTO %s (Id_Operation, %s) VALUES (:op, :id)', $table, $column));
        foreach ($ids as $id) {
            $statement->execute(['op' => $operationId, 'id' => $id]);
        }
    }

    /** @return list<string> libellés des types de mission */
    public function missionLabels(int $operationId): array
    {
        return $this->labels(
            'SELECT m.mission AS label FROM Operations_Missions om JOIN Missions m ON m.Id_Mission = om.Id_Mission
             WHERE om.Id_Operation = :op ORDER BY m.mission',
            $operationId,
        );
    }

    /** @return list<string> libellés des lieux */
    public function locationLabels(int $operationId): array
    {
        return $this->labels(
            'SELECT l.location AS label FROM Operations_Locations ol JOIN Locations l ON l.Id_Location = ol.Id_Location
             WHERE ol.Id_Operation = :op ORDER BY l.location',
            $operationId,
        );
    }

    /** @return list<string> libellés des compétences requises */
    public function requiredAbilityLabels(int $operationId): array
    {
        return $this->labels(
            'SELECT ab.ability AS label FROM Operations_Abilities oa JOIN Abilities ab ON ab.Id_Ability = oa.Id_Ability
             WHERE oa.Id_Operation = :op ORDER BY ab.ability',
            $operationId,
        );
    }

    /**
     * Menaces visées, de la plus dangereuse à la moins dangereuse.
     *
     * @return list<array{id: int, name: string, serial: string, tier: string, tier_level: int}>
     */
    public function findThreats(int $operationId): array
    {
        $rows = $this->fetchAll(
            'SELECT th.Id_Threat, th.name, th.serial, t.tier, t.level
             FROM Operations_Threats ot
             JOIN Threats th ON th.Id_Threat = ot.Id_Threat
             JOIN Tiers t    ON t.Id_Tier    = th.Id_Tier
             WHERE ot.Id_Operation = :op
             ORDER BY t.level DESC, th.name',
            ['op' => $operationId],
        );

        return array_map(static fn (array $row): array => [
            'id'         => (int) $row['Id_Threat'],
            'name'       => (string) $row['name'],
            'serial'     => (string) $row['serial'],
            'tier'       => (string) $row['tier'],
            'tier_level' => (int) $row['level'],
        ], $rows);
    }

    // --- Équipes affectées -------------------------------------------------------

    /** @return list<array{id: int, name: string, archived: bool, manager_id: int}> */
    public function findTeams(int $operationId): array
    {
        $rows = $this->fetchAll(
            'SELECT t.Id_Team, t.team_name, t.archived_at, t.Id_User
             FROM Operations_Teams ot JOIN Teams t ON t.Id_Team = ot.Id_Team
             WHERE ot.Id_Operation = :op ORDER BY t.team_name',
            ['op' => $operationId],
        );

        return array_map(static fn (array $row): array => [
            'id'         => (int) $row['Id_Team'],
            'name'       => (string) $row['team_name'],
            'archived'   => $row['archived_at'] !== null,
            'manager_id' => (int) $row['Id_User'],
        ], $rows);
    }

    public function hasTeam(int $operationId, int $teamId): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM Operations_Teams WHERE Id_Operation = :op AND Id_Team = :team',
            ['op' => $operationId, 'team' => $teamId],
        ) !== null;
    }

    public function addTeam(int $operationId, int $teamId): void
    {
        $this->execute('INSERT INTO Operations_Teams (Id_Team, Id_Operation) VALUES (:team, :op)', ['team' => $teamId, 'op' => $operationId]);
    }

    public function removeTeam(int $operationId, int $teamId): int
    {
        return $this->execute('DELETE FROM Operations_Teams WHERE Id_Operation = :op AND Id_Team = :team', ['op' => $operationId, 'team' => $teamId]);
    }

    // --- Participants --------------------------------------------------------------

    /**
     * Participants, avec l'équipe dans laquelle chacun était au début de
     * l'opération (requête 3 : c'est ce que permet l'historisation des appartenances).
     *
     * @return list<array{agent_id: int, name: string, archived: bool, tier: string, tier_level: int, team_id: ?int, team_name: ?string}>
     */
    public function findParticipants(int $operationId): array
    {
        $rows = $this->fetchAll(
            'SELECT a.Id_Agent, a.name, a.archived_at, t.tier, t.level, tm.Id_Team, tm.team_name
             FROM Operations_Agents oa
             JOIN Operations o ON o.Id_Operation = oa.Id_Operation
             JOIN Agents a     ON a.Id_Agent     = oa.Id_Agent
             JOIN Tiers t      ON t.Id_Tier      = a.Id_Tier
             LEFT JOIN Memberships m
                    ON m.Id_Agent = oa.Id_Agent
                   AND m.date_join <= o.date_beg
                   AND (m.date_leave IS NULL OR m.date_leave >= o.date_beg)
             LEFT JOIN Teams tm ON tm.Id_Team = m.Id_Team
             WHERE oa.Id_Operation = :op
             ORDER BY a.name',
            ['op' => $operationId],
        );

        return array_map(static fn (array $row): array => [
            'agent_id'   => (int) $row['Id_Agent'],
            'name'       => (string) $row['name'],
            'archived'   => $row['archived_at'] !== null,
            'tier'       => (string) $row['tier'],
            'tier_level' => (int) $row['level'],
            'team_id'    => $row['Id_Team'] !== null ? (int) $row['Id_Team'] : null,
            'team_name'  => $row['team_name'] !== null ? (string) $row['team_name'] : null,
        ], $rows);
    }

    /**
     * Opérations auxquelles un agent a participé, avec son équipe à l'époque (requête 3).
     *
     * @return list<array{id: int, serial: string, code_name: string, status: string, date_beg: DateTimeImmutable, date_end: ?DateTimeImmutable, team_id: ?int, team_name: ?string}>
     */
    public function findForAgent(int $agentId): array
    {
        $rows = $this->fetchAll(
            'SELECT o.Id_Operation, o.serial, o.code_name, s.status, o.date_beg, o.date_end, tm.Id_Team, tm.team_name
             FROM Operations_Agents oa
             JOIN Operations o ON o.Id_Operation = oa.Id_Operation
             JOIN Status s     ON s.Id_Status    = o.Id_Status
             LEFT JOIN Memberships m
                    ON m.Id_Agent = oa.Id_Agent
                   AND m.date_join <= o.date_beg
                   AND (m.date_leave IS NULL OR m.date_leave >= o.date_beg)
             LEFT JOIN Teams tm ON tm.Id_Team = m.Id_Team
             WHERE oa.Id_Agent = :agent
             ORDER BY o.date_beg DESC',
            ['agent' => $agentId],
        );

        return array_map(static fn (array $row): array => [
            'id'        => (int) $row['Id_Operation'],
            'serial'    => (string) $row['serial'],
            'code_name' => (string) $row['code_name'],
            'status'    => (string) $row['status'],
            'date_beg'  => new DateTimeImmutable((string) $row['date_beg']),
            'date_end'  => to_date($row['date_end']),
            'team_id'   => $row['Id_Team'] !== null ? (int) $row['Id_Team'] : null,
            'team_name' => $row['team_name'] !== null ? (string) $row['team_name'] : null,
        ], $rows);
    }

    public function isParticipant(int $operationId, int $agentId): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM Operations_Agents WHERE Id_Operation = :op AND Id_Agent = :agent',
            ['op' => $operationId, 'agent' => $agentId],
        ) !== null;
    }

    public function addParticipant(int $operationId, int $agentId): void
    {
        $this->execute('INSERT INTO Operations_Agents (Id_Agent, Id_Operation) VALUES (:agent, :op)', ['agent' => $agentId, 'op' => $operationId]);
    }

    public function removeParticipant(int $operationId, int $agentId): int
    {
        return $this->execute('DELETE FROM Operations_Agents WHERE Id_Operation = :op AND Id_Agent = :agent', ['op' => $operationId, 'agent' => $agentId]);
    }

    // --- Disponibilité et staffing (requête 6) -------------------------------------

    /**
     * Autres opérations non annulées, aux dates qui chevauchent celles de
     * l'opération cible, auxquelles l'agent est déjà engagé (règle 8).
     *
     * @return list<string> noms de code des opérations en conflit
     */
    public function findConflicts(int $agentId, int $operationId): array
    {
        $rows = $this->fetchAll(
            'SELECT other.code_name
             FROM Operations_Agents oag
             JOIN Operations other  ON other.Id_Operation = oag.Id_Operation
             JOIN Operations target ON target.Id_Operation = :target
             JOIN Status s          ON s.Id_Status = other.Id_Status
             WHERE oag.Id_Agent = :agent AND ' . self::OVERLAP . '
             ORDER BY other.date_beg',
            ['target' => $operationId, 'agent' => $agentId, 'cancelled' => Status::CANCELLED],
        );

        return array_map(static fn (array $row): string => (string) $row['code_name'], $rows);
    }

    /**
     * STAFFING : agents en service, disponibles, qui possèdent TOUTES les
     * compétences requises (requête 6, division relationnelle).
     *
     * Adaptations par rapport au fichier de requêtes :
     *   - les agents déjà participants sont exclus (sinon on proposerait un doublon) ;
     *   - avec de vraies requêtes préparées, un marqueur nommé ne peut servir
     *     qu'une fois : @op devient :op_required, :op_target, :op_participant, :op_count.
     *
     * @return list<array{agent_id: int, name: string, tier: string, tier_level: int, team_name: ?string, abilities: string}>
     */
    public function findStaffingCandidates(int $operationId): array
    {
        $rows = $this->fetchAll(
            'SELECT a.Id_Agent, a.name, t.tier, t.level, cm.team_name,
                    GROUP_CONCAT(DISTINCT aa.ability ORDER BY aa.ability SEPARATOR \', \') AS abilities
             FROM Agents a
             JOIN Tiers t                  ON t.Id_Tier     = a.Id_Tier
             LEFT JOIN current_memberships cm ON cm.Id_Agent = a.Id_Agent
             JOIN agent_abilities aa       ON aa.Id_Agent   = a.Id_Agent
             JOIN Operations_Abilities oa  ON oa.Id_Ability = aa.Id_Ability
                                          AND oa.Id_Operation = :op_required
             WHERE a.archived_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM Operations_Agents p
                   WHERE p.Id_Agent = a.Id_Agent AND p.Id_Operation = :op_participant
               )
               AND NOT EXISTS (
                   SELECT 1
                   FROM Operations_Agents oag
                   JOIN Operations other  ON other.Id_Operation = oag.Id_Operation
                   JOIN Operations target ON target.Id_Operation = :op_target
                   JOIN Status s          ON s.Id_Status = other.Id_Status
                   WHERE oag.Id_Agent = a.Id_Agent AND ' . self::OVERLAP . '
               )
             GROUP BY a.Id_Agent, a.name, t.tier, t.level, cm.team_name
             HAVING COUNT(DISTINCT aa.Id_Ability) = (
                 SELECT COUNT(*) FROM Operations_Abilities WHERE Id_Operation = :op_count
             )
             ORDER BY t.level DESC, a.name',
            [
                'op_required'    => $operationId,
                'op_participant' => $operationId,
                'op_target'      => $operationId,
                'op_count'       => $operationId,
                'cancelled'      => Status::CANCELLED,
            ],
        );

        return array_map(static fn (array $row): array => [
            'agent_id'   => (int) $row['Id_Agent'],
            'name'       => (string) $row['name'],
            'tier'       => (string) $row['tier'],
            'tier_level' => (int) $row['level'],
            'team_name'  => $row['team_name'] !== null ? (string) $row['team_name'] : null,
            'abilities'  => (string) $row['abilities'],
        ], $rows);
    }

    /**
     * Agents en service qui ne participent à aucune opération « En cours »
     * (tableau de bord, décision Q12).
     *
     * @return list<array{agent_id: int, name: string, tier: string, team_name: ?string}>
     */
    public function findAvailableAgents(): array
    {
        $rows = $this->fetchAll(
            'SELECT a.Id_Agent, a.name, t.tier, cm.team_name
             FROM Agents a
             JOIN Tiers t ON t.Id_Tier = a.Id_Tier
             LEFT JOIN current_memberships cm ON cm.Id_Agent = a.Id_Agent
             WHERE a.archived_at IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM Operations_Agents oa
                   JOIN Operations o ON o.Id_Operation = oa.Id_Operation
                   JOIN Status s     ON s.Id_Status    = o.Id_Status
                   WHERE oa.Id_Agent = a.Id_Agent AND s.status = :in_progress
               )
             ORDER BY t.level DESC, a.name',
            ['in_progress' => Status::IN_PROGRESS],
        );

        return array_map(static fn (array $row): array => [
            'agent_id'  => (int) $row['Id_Agent'],
            'name'      => (string) $row['name'],
            'tier'      => (string) $row['tier'],
            'team_name' => $row['team_name'] !== null ? (string) $row['team_name'] : null,
        ], $rows);
    }

    /** @return array{0: string, 1: string} [table, colonne] */
    private function link(string $link): array
    {
        return self::LINKS[$link] ?? throw new InvalidArgumentException('Liaison inconnue : ' . $link);
    }

    /** @return list<string> */
    private function labels(string $sql, int $operationId): array
    {
        return array_map(static fn (array $row): string => (string) $row['label'], $this->fetchAll($sql, ['op' => $operationId]));
    }

    /** @return array<string, mixed> */
    private function params(Operation $operation): array
    {
        return [
            'code_name' => $operation->codeName,
            'date_beg'  => $operation->dateBeg->format('Y-m-d H:i:s'),
            'date_end'  => $operation->dateEnd?->format('Y-m-d H:i:s'),
            'brief'     => $operation->brief,
            'status'    => $operation->statusId,
        ];
    }
}
