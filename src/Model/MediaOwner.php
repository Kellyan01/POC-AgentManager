<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Type d'entité à laquelle une image est rattachée. Chaque cas connaît la
 * colonne de Medias qui le référence et le dossier où ranger ses fichiers.
 * La valeur sert de segment d'URL (/agents/5/medias...).
 */
enum MediaOwner: string
{
    case Agent = 'agents';
    case Threat = 'threats';
    case Operation = 'operations';

    /** Colonne de la table Medias (liste fermée : sûre à concaténer dans du SQL). */
    public function column(): string
    {
        return match ($this) {
            self::Agent     => 'Id_Agent',
            self::Threat    => 'Id_Threat',
            self::Operation => 'Id_Operation',
        };
    }

    /** Dossier de rangement, relatif à public/. */
    public function directory(): string
    {
        return 'uploads/' . $this->value;
    }
}
