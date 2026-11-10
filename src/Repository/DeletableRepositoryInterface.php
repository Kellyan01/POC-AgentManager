<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repository dont les lignes peuvent être supprimées physiquement
 * (référentiels, menaces, opérations, médias...).
 */
interface DeletableRepositoryInterface
{
    public function delete(int $id): void;
}
