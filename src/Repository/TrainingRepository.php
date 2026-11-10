<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Training;

/**
 * @extends AbstractRepository<Training>
 */
final class TrainingRepository extends AbstractRepository implements DeletableRepositoryInterface
{
    protected const TABLE = 'Trainings';
    protected const PRIMARY_KEY = 'Id_Training';

    private const SELECT = <<<'SQL'
        SELECT t.*, f.formation, a.name AS agent_name
        FROM Trainings t
        JOIN Formations f ON f.Id_Formation = t.Id_Formation
        JOIN Agents     a ON a.Id_Agent     = t.Id_Agent
        SQL;

    protected function hydrate(array $row): Training
    {
        return Training::fromRow($row);
    }

    public function findById(int $id): ?Training
    {
        $row = $this->fetchOne(self::SELECT . ' WHERE t.Id_Training = :id', ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<Training> */
    public function findAll(): array
    {
        return array_map($this->hydrate(...), $this->fetchAll(self::SELECT . ' ORDER BY t.date_beg DESC'));
    }

    /**
     * Historique des formations d'un agent, échecs compris (requête 5).
     *
     * @return list<Training> de la plus récente à la plus ancienne
     */
    public function findForAgent(int $agentId): array
    {
        $rows = $this->fetchAll(self::SELECT . ' WHERE t.Id_Agent = :agent ORDER BY t.date_beg DESC', ['agent' => $agentId]);

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * Dernières sessions de formation (tableau de bord).
     *
     * @return list<Training>
     */
    public function findLatest(int $limit): array
    {
        // LIMIT n'accepte qu'un entier : on le lie en paramètre typé plutôt que de le concaténer.
        $statement = $this->pdo->prepare(self::SELECT . ' ORDER BY t.date_beg DESC, t.Id_Training DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function insert(object $entity): int
    {
        $training = $this->expect($entity, Training::class);
        $this->execute(
            'INSERT INTO Trainings (date_beg, date_end, is_passed, Id_Formation, Id_Agent)
             VALUES (:beg, :end, :passed, :formation, :agent)',
            $this->params($training) + ['agent' => $training->agentId],
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function update(object $entity): void
    {
        $training = $this->expect($entity, Training::class);
        $this->execute(
            'UPDATE Trainings SET date_beg = :beg, date_end = :end, is_passed = :passed, Id_Formation = :formation
             WHERE Id_Training = :id',
            $this->params($training) + ['id' => $training->id],
        );
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM Trainings WHERE Id_Training = :id', ['id' => $id]);
    }

    /** @return array<string, mixed> */
    private function params(Training $training): array
    {
        return [
            'beg'       => $training->dateBeg->format('Y-m-d H:i:s'),
            'end'       => $training->dateEnd?->format('Y-m-d H:i:s'),
            'passed'    => $training->isPassed === null ? null : (int) $training->isPassed,
            'formation' => $training->formationId,
        ];
    }
}
