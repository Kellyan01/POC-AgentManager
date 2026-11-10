<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Threat;
use App\Model\Tier;

/**
 * @extends AbstractRepository<Threat>
 */
final class ThreatRepository extends AbstractRepository implements DeletableRepositoryInterface
{
    protected const TABLE = 'Threats';
    protected const PRIMARY_KEY = 'Id_Threat';

    /** Menace + tier + portrait principal (média le plus récent, requête 8) + nombre d'opérations. */
    private const SELECT = <<<'SQL'
        SELECT th.*, t.tier, t.level AS tier_level, md.url AS portrait,
               (SELECT COUNT(*) FROM Operations_Threats ot WHERE ot.Id_Threat = th.Id_Threat) AS operation_count
        FROM Threats th
        JOIN Tiers t ON t.Id_Tier = th.Id_Tier
        LEFT JOIN Medias md ON md.Id_Media = (
            SELECT MAX(m2.Id_Media) FROM Medias m2 WHERE m2.Id_Threat = th.Id_Threat
        )
        SQL;

    protected function hydrate(array $row): Threat
    {
        return Threat::fromRow($row);
    }

    public function findById(int $id): ?Threat
    {
        $row = $this->fetchOne(self::SELECT . ' WHERE th.Id_Threat = :id', ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<Threat> de la plus dangereuse à la moins dangereuse */
    public function findAll(): array
    {
        return array_map($this->hydrate(...), $this->fetchAll(self::SELECT . ' ORDER BY t.level DESC, th.name'));
    }

    /** @return list<Threat> menaces de tier God (tableau de bord), tier référencé par son libellé */
    public function findGodTier(): array
    {
        $rows = $this->fetchAll(self::SELECT . ' WHERE t.tier = :god ORDER BY th.name', ['god' => Tier::GOD]);

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * Opérations visant cette menace.
     *
     * @return list<array{id: int, serial: string, code_name: string, status: string, date_beg: \DateTimeImmutable, date_end: ?\DateTimeImmutable}>
     */
    public function findOperations(int $threatId): array
    {
        $rows = $this->fetchAll(
            'SELECT o.Id_Operation, o.serial, o.code_name, s.status, o.date_beg, o.date_end
             FROM Operations_Threats ot
             JOIN Operations o ON o.Id_Operation = ot.Id_Operation
             JOIN Status s     ON s.Id_Status    = o.Id_Status
             WHERE ot.Id_Threat = :threat
             ORDER BY o.date_beg DESC',
            ['threat' => $threatId],
        );

        return array_map(static fn (array $row): array => [
            'id'        => (int) $row['Id_Operation'],
            'serial'    => (string) $row['serial'],
            'code_name' => (string) $row['code_name'],
            'status'    => (string) $row['status'],
            'date_beg'  => new \DateTimeImmutable((string) $row['date_beg']),
            'date_end'  => to_date($row['date_end']),
        ], $rows);
    }

    /** Le numéro de série est fourni par l'entité (voir SerialGenerator). */
    public function insert(object $entity): int
    {
        $threat = $this->expect($entity, Threat::class);
        $this->execute(
            'INSERT INTO Threats (name, serial, informations, Id_Tier) VALUES (:name, :serial, :informations, :tier)',
            [
                'name'         => $threat->name,
                'serial'       => $threat->serial,
                'informations' => $threat->informations,
                'tier'         => $threat->tierId,
            ],
        );

        return (int) $this->pdo->lastInsertId();
    }

    /** Le numéro de série n'est jamais modifié. */
    public function update(object $entity): void
    {
        $threat = $this->expect($entity, Threat::class);
        $this->execute(
            'UPDATE Threats SET name = :name, informations = :informations, Id_Tier = :tier WHERE Id_Threat = :id',
            [
                'name'         => $threat->name,
                'informations' => $threat->informations,
                'tier'         => $threat->tierId,
                'id'           => $threat->id,
            ],
        );
    }

    /**
     * Suppression de la ligne seule. Les médias (RESTRICT) doivent avoir été
     * supprimés avant : c'est le rôle de ThreatService::delete().
     */
    public function delete(int $id): void
    {
        $this->execute('DELETE FROM Threats WHERE Id_Threat = :id', ['id' => $id]);
    }
}
