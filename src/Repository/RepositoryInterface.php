<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Contrat commun des repositories : lire, créer, modifier.
 *
 * La suppression n'en fait pas partie : agents, équipes et utilisateurs ne sont
 * jamais supprimés mais archivés. Plutôt que d'imposer un delete() qui lèverait
 * une exception (ce qui violerait le principe de substitution de Liskov), on
 * sépare les capacités en interfaces distinctes (principe de ségrégation des
 * interfaces) : DeletableRepositoryInterface et ArchivableRepositoryInterface.
 *
 * @template T of object
 */
interface RepositoryInterface
{
    /** @return T|null */
    public function findById(int $id): ?object;

    /** @return list<T> */
    public function findAll(): array;

    /**
     * Insère l'entité et renvoie l'identifiant généré.
     *
     * @param T $entity
     */
    public function insert(object $entity): int;

    /** @param T $entity */
    public function update(object $entity): void;
}
