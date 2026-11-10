<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Niveau de puissance (God, A, B... F). Plus « level » est élevé, plus le tier est fort.
 */
final class Tier
{
    /** Tier le plus élevé, référencé par son libellé (tableau de bord). Verrouillé dans les référentiels. */
    public const GOD = 'God';

    public function __construct(
        public readonly ?int $id,
        public readonly string $tier,
        public readonly int $level,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self((int) $row['Id_Tier'], (string) $row['tier'], (int) $row['level']);
    }

    /** Modificateur CSS du badge (God en rouge, A et B en ambre). */
    public static function cssModifier(string $tier): string
    {
        return match ($tier) {
            self::GOD => 'god',
            'A', 'B'  => 'high',
            default   => 'standard',
        };
    }
}
