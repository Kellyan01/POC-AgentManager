<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Repository à suppression logique : la ligne reste en base, on renseigne
 * archived_at. Les éléments archivés disparaissent des listes et des
 * formulaires mais restent visibles dans les historiques.
 */
interface ArchivableRepositoryInterface
{
    public function archive(int $id): void;
}
