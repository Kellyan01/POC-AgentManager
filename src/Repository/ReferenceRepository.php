<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Exception\BusinessRuleException;
use App\Model\ReferenceItem;
use PDO;
use PDOException;
use Throwable;

/**
 * Repository générique des référentiels à libellé unique (voir ReferenceDefinition).
 *
 * Une instance par référentiel, créée dans le bootstrap :
 *   new ReferenceRepository($pdo, $catalog['missions'])
 *
 * @extends AbstractRepository<ReferenceItem>
 */
final class ReferenceRepository extends AbstractRepository implements DeletableRepositoryInterface
{
    public function __construct(PDO $pdo, private readonly ReferenceDefinition $definition)
    {
        parent::__construct($pdo);
    }

    public function definition(): ReferenceDefinition
    {
        return $this->definition;
    }

    protected function table(): string
    {
        return $this->definition->table;
    }

    protected function primaryKey(): string
    {
        return $this->definition->primaryKey;
    }

    protected function hydrate(array $row): ReferenceItem
    {
        return new ReferenceItem((int) $row[$this->definition->primaryKey], (string) $row[$this->definition->column]);
    }

    public function findById(int $id): ?ReferenceItem
    {
        return parent::findById($id);
    }

    /** @return list<ReferenceItem> triés par libellé (ou par identifiant pour les statuts) */
    public function findAll(): array
    {
        $order = $this->definition->orderById ? $this->definition->primaryKey : $this->definition->column;
        $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY %s', $this->table(), $order));

        return array_map($this->hydrate(...), $rows);
    }

    public function labelExists(string $label, ?int $exceptId = null): bool
    {
        $sql = sprintf(
            'SELECT 1 FROM %s WHERE %s = :label AND %s <> :except',
            $this->table(),
            $this->definition->column,
            $this->primaryKey(),
        );

        return $this->fetchOne($sql, ['label' => $label, 'except' => $exceptId ?? 0]) !== null;
    }

    public function insert(object $entity): int
    {
        $item = $this->expect($entity, ReferenceItem::class);
        $this->execute(
            sprintf('INSERT INTO %s (%s) VALUES (:label)', $this->table(), $this->definition->column),
            ['label' => $item->label],
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function update(object $entity): void
    {
        $item = $this->expect($entity, ReferenceItem::class);
        $this->execute(
            sprintf('UPDATE %s SET %s = :label WHERE %s = :id', $this->table(), $this->definition->column, $this->primaryKey()),
            ['label' => $item->label, 'id' => $item->id],
        );
    }

    /**
     * Enregistre la valeur ET ses compétences (archétypes, formations) en une transaction :
     * la valeur et sa table de liaison forment un tout, enregistré entièrement ou pas du tout.
     *
     * @param list<int> $abilityIds ignoré si le référentiel n'a pas de compétences
     * @return int identifiant de la valeur
     */
    public function save(ReferenceItem $item, array $abilityIds = []): int
    {
        $this->pdo->beginTransaction();
        try {
            if ($item->id === null) {
                $id = $this->insert($item);
            } else {
                $this->update($item);
                $id = $item->id;
            }

            if ($this->definition->hasAbilities()) {
                $this->replaceAbilities($id, $abilityIds);
            }

            $this->pdo->commit();

            return $id;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @throws BusinessRuleException si la valeur est encore utilisée (clé étrangère RESTRICT) */
    public function delete(int $id): void
    {
        try {
            $this->execute(sprintf('DELETE FROM %s WHERE %s = :id', $this->table(), $this->primaryKey()), ['id' => $id]);
        } catch (PDOException $e) {
            if (MysqlError::is($e, MysqlError::ROW_IS_REFERENCED)) {
                throw new BusinessRuleException('Cette valeur est utilisée ailleurs dans l\'application : elle ne peut pas être supprimée.');
            }
            throw $e;
        }
    }

    /** @return list<int> compétences liées (archétypes, formations) */
    public function abilityIds(int $id): array
    {
        if (!$this->definition->hasAbilities()) {
            return [];
        }

        $rows = $this->fetchAll(
            sprintf('SELECT Id_Ability FROM %s WHERE %s = :id', $this->definition->abilityTable, $this->primaryKey()),
            ['id' => $id],
        );

        return array_map(static fn (array $row): int => (int) $row['Id_Ability'], $rows);
    }

    /**
     * Libellés des compétences liées, pour chaque valeur du référentiel.
     *
     * @return array<int, list<string>> identifiant => libellés
     */
    public function abilityLabelsByItem(): array
    {
        if (!$this->definition->hasAbilities()) {
            return [];
        }

        $rows = $this->fetchAll(sprintf(
            'SELECT l.%1$s AS item_id, ab.ability
             FROM %2$s l JOIN Abilities ab ON ab.Id_Ability = l.Id_Ability
             ORDER BY ab.ability',
            $this->primaryKey(),
            $this->definition->abilityTable,
        ));

        $labels = [];
        foreach ($rows as $row) {
            $labels[(int) $row['item_id']][] = (string) $row['ability'];
        }

        return $labels;
    }

    /** @param list<int> $abilityIds */
    private function replaceAbilities(int $id, array $abilityIds): void
    {
        $table = $this->definition->abilityTable;
        $this->execute(sprintf('DELETE FROM %s WHERE %s = :id', $table, $this->primaryKey()), ['id' => $id]);

        $statement = $this->pdo->prepare(
            sprintf('INSERT INTO %s (%s, Id_Ability) VALUES (:id, :ability)', $table, $this->primaryKey()),
        );
        foreach ($abilityIds as $abilityId) {
            $statement->execute(['id' => $id, 'ability' => $abilityId]);
        }
    }
}
