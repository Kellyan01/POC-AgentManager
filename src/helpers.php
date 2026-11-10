<?php

declare(strict_types=1);

/*
 * Fonctions utilitaires d'affichage, chargées par Composer (section "files").
 * Ce sont des fonctions pures : elles ne lisent ni la session ni la base.
 */

/**
 * Échappe une valeur pour l'afficher dans du HTML (contenu ou attribut).
 * À utiliser sur TOUTE donnée affichée dans une vue.
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Date au format français : « 14/03/1992 » ou « 14/03/1992 09:00 ». « — » si absente. */
function format_date(?DateTimeInterface $date, bool $withTime = false): string
{
    if ($date === null) {
        return '—';
    }

    return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
}

/** Date au format attendu par l'attribut datetime de <time> (ISO 8601). */
function iso_date(?DateTimeInterface $date): string
{
    return $date?->format('Y-m-d\TH:i') ?? '';
}

/** Valeur d'un champ <input type="datetime-local"> à partir d'une date. */
function input_datetime(?DateTimeInterface $date): string
{
    return $date?->format('Y-m-d\TH:i') ?? '';
}

/** Valeur d'un champ <input type="date"> à partir d'une date. */
function input_date(?DateTimeInterface $date): string
{
    return $date?->format('Y-m-d') ?? '';
}

/**
 * Convertit une colonne DATE / DATETIME lue en base en objet date (null si vide).
 */
function to_date(?string $value): ?DateTimeImmutable
{
    return $value === null || $value === '' ? null : new DateTimeImmutable($value);
}

/**
 * Convertit la saisie d'un champ datetime-local (« 2026-12-01T08:00 ») au format SQL.
 * Renvoie null si la saisie est vide ou invalide.
 */
function datetime_to_sql(?string $input): ?string
{
    if ($input === null || $input === '') {
        return null;
    }

    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $input);
        if ($date !== false && $date->format($format) === $input) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    return null;
}

/** Âge en années révolues à partir de la date de naissance. */
function age(?DateTimeInterface $dob): ?int
{
    return $dob === null ? null : $dob->diff(new DateTimeImmutable('today'))->y;
}

/** Construit une URL avec une chaîne de requête, en ignorant les valeurs vides. */
function url_with_query(string $path, array $query): string
{
    $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');

    return $query === [] ? $path : $path . '?' . http_build_query($query);
}
