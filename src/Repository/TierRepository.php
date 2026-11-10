<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Exception\BusinessRuleException;
use App\Model\Tier;
use PDOException;

/**
 * @extends AbstractRepository<Tier>
 */
final class TierRepository extends AbstractRepository implements DeletableRepositoryInterface
{
    protected const TABLE = 'Tiers';
    protected const PRIMARY_KEY = 'Id_Tier';

    protected function hydrate(array $row): Tier
    {
        return Tier::fromRow($row);
    }

    public function findById(int $id): ?Tier
    {
        return parent::findById($id);
    }

    /** @return list<Tier> du plus fort au plus faible */
    public function findAll(): array
    {
        return array_map($this->hydrate(...), $this->fetchAll('SELECT * FROM Tiers ORDER BY level DESC'));
    }

    public function tierExists(string $tier, ?int $exceptId = null): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM Tiers WHERE tier = :tier AND Id_Tier <> :except',
            ['tier' => $tier, 'except' => $exceptId ?? 0],
        ) !== null;
    }

    public function levelExists(int $level, ?int $exceptId = null): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM Tiers WHERE level = :level AND Id_Tier <> :except',
            ['level' => $level, 'except' => $exceptId ?? 0],
        ) !== null;
    }

    public function insert(object $entity): int
    {
        $tier = $this->expect($entity, Tier::class);
        $this->execute('INSERT INTO Tiers (tier, level) VALUES (:tier, :level)', [
            'tier'  => $tier->tier,
            'level' => $tier->level,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(object $entity): void
    {
        $tier = $this->expect($entity, Tier::class);
        $this->execute('UPDATE Tiers SET tier = :tier, level = :level WHERE Id_Tier = :id', [
            'tier'  => $tier->tier,
            'level' => $tier->level,
            'id'    => $tier->id,
        ]);
    }

    public function delete(int $id): void
    {
        try {
            $this->execute('DELETE FROM Tiers WHERE Id_Tier = :id', ['id' => $id]);
        } catch (PDOException $e) {
            if (MysqlError::is($e, MysqlError::ROW_IS_REFERENCED)) {
                throw new BusinessRuleException('Ce tier est attribué à des agents ou à des menaces : il ne peut pas être supprimé.');
            }
            throw $e;
        }
    }
}
