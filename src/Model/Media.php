<?php

declare(strict_types=1);

namespace App\Model;

use LogicException;

/**
 * Image rattachée à EXACTEMENT une entité : agent (portrait), menace (portrait)
 * ou opération (illustration). La base l'impose par la contrainte CHECK
 * chk_medias_one_owner.
 *
 * url : chemin relatif à public/ (ex. uploads/agents/3f9a....webp).
 */
final class Media
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $url,
        public readonly MediaOwner $owner,
        public readonly int $ownerId,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        foreach (MediaOwner::cases() as $owner) {
            if ($row[$owner->column()] !== null) {
                return new self((int) $row['Id_Media'], (string) $row['url'], $owner, (int) $row[$owner->column()]);
            }
        }

        throw new LogicException('Média sans propriétaire : la contrainte CHECK aurait dû l\'empêcher.');
    }

    /** Page de l'entité propriétaire (pour revenir après un upload ou une suppression). */
    public function ownerUrl(): string
    {
        return '/' . $this->owner->value . '/' . $this->ownerId;
    }
}
