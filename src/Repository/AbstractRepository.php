<?php

declare(strict_types=1);

namespace App\Repository;

use InvalidArgumentException;
use PDO;

/**
 * Code commun à tous les repositories.
 *
 * Le PDO est reçu par le constructeur (injection de dépendances) : un repository
 * ne crée jamais sa propre connexion.
 *
 * Les noms de table et de colonne viennent de constantes de classe, jamais d'une
 * saisie utilisateur : un identifiant SQL ne peut pas être passé en paramètre
 * d'une requête préparée, c'est donc la seule manière sûre de le concaténer.
 *
 * @template T of object
 * @implements RepositoryInterface<T>
 */
abstract class AbstractRepository implements RepositoryInterface
{
    protected const TABLE = '';
    protected const PRIMARY_KEY = '';

    public function __construct(protected readonly PDO $pdo)
    {
    }

    /**
     * Transforme une ligne de résultat en entité.
     *
     * @param array<string, mixed> $row
     * @return T
     */
    abstract protected function hydrate(array $row): object;

    /** @return T|null */
    public function findById(int $id): ?object
    {
        $row = $this->fetchOne(
            sprintf('SELECT * FROM %s WHERE %s = :id', $this->table(), $this->primaryKey()),
            ['id' => $id],
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<T> */
    public function findAll(): array
    {
        $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY %s', $this->table(), $this->primaryKey()));

        return array_map(fn (array $row): object => $this->hydrate($row), $rows);
    }

    protected function table(): string
    {
        return static::TABLE;
    }

    protected function primaryKey(): string
    {
        return static::PRIMARY_KEY;
    }

    /**
     * Exécute une requête préparée et renvoie la première ligne (ou null).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Exécute une requête préparée et renvoie toutes les lignes.
     *
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * Exécute une requête préparée d'écriture et renvoie le nombre de lignes touchées.
     *
     * @param array<string, mixed> $params
     */
    protected function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /**
     * insert() et update() reçoivent « object » (signature imposée par l'interface :
     * une classe fille ne peut pas restreindre le type d'un paramètre). On vérifie
     * donc ici que l'entité reçue est bien du type attendu.
     *
     * @template E of object
     * @param class-string<E> $class
     * @return E
     */
    protected function expect(object $entity, string $class): object
    {
        if (!$entity instanceof $class) {
            throw new InvalidArgumentException(sprintf(
                '%s attend une entité %s, %s reçue.',
                static::class,
                $class,
                $entity::class,
            ));
        }

        return $entity;
    }
}
