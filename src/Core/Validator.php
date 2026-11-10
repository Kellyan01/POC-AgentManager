<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Validation des données d'un formulaire, côté serveur.
 *
 * La validation HTML (required, maxlength...) améliore le confort, mais elle se
 * contourne en deux clics : seule la validation serveur fait foi.
 *
 * Utilisation :
 *   $v = new Validator($data);
 *   $v->required('name')->maxLength('name', 50)->date('dob');
 *   if ($v->fails()) { ... $v->errors() ... }
 *
 * Une seule erreur est retenue par champ : la première rencontrée.
 */
final class Validator
{
    /** @var array<string, string> champ => message */
    private array $errors = [];

    /** @param array<string, string|null> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, string $message = 'Ce champ est obligatoire.'): self
    {
        if ($this->value($field) === '') {
            $this->addError($field, $message);
        }

        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->addError($field, sprintf('%d caractères maximum.', $max));
        }

        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        $value = $this->value($field);
        if ($value !== '' && mb_strlen($value) < $min) {
            $this->addError($field, sprintf('%d caractères minimum.', $min));
        }

        return $this;
    }

    public function email(string $field): self
    {
        $value = $this->value($field);
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, 'Adresse e-mail invalide (exemple : prenom.nom@agentmanager.test).');
        }

        return $this;
    }

    public function integer(string $field, ?int $min = null, ?int $max = null): self
    {
        $value = $this->value($field);
        if ($value === '') {
            return $this;
        }

        $options = array_filter(['min_range' => $min, 'max_range' => $max], static fn ($v) => $v !== null);
        if (filter_var($value, FILTER_VALIDATE_INT, ['options' => $options]) === false) {
            $message = match (true) {
                $min !== null && $max !== null => sprintf('Nombre entier entre %d et %d attendu.', $min, $max),
                $min !== null                  => sprintf('Nombre entier supérieur ou égal à %d attendu.', $min),
                default                        => 'Nombre entier attendu.',
            };
            $this->addError($field, $message);
        }

        return $this;
    }

    /**
     * La valeur doit figurer dans la liste (ex. identifiants proposés dans un <select>).
     * Protège contre un formulaire modifié à la main.
     *
     * @param list<int|string> $allowed
     */
    public function in(string $field, array $allowed, string $message = 'Choisissez une valeur dans la liste.'): self
    {
        $value = $this->value($field);
        if ($value !== '' && !in_array($value, array_map('strval', $allowed), true)) {
            $this->addError($field, $message);
        }

        return $this;
    }

    /** Date seule (champ type="date", format AAAA-MM-JJ). */
    public function date(string $field): self
    {
        $value = $this->value($field);
        if ($value !== '' && self::parse($value, 'Y-m-d') === null) {
            $this->addError($field, 'Date invalide (format attendu : JJ/MM/AAAA).');
        }

        return $this;
    }

    /** Date et heure (champ type="datetime-local"). */
    public function dateTime(string $field): self
    {
        $value = $this->value($field);
        if ($value !== '' && datetime_to_sql($value) === null) {
            $this->addError($field, 'Date et heure invalides.');
        }

        return $this;
    }

    /** La date du champ $field doit être postérieure (strictement) à celle du champ $before. */
    public function after(string $field, string $before, string $message): self
    {
        $end = datetime_to_sql($this->value($field));
        $beg = datetime_to_sql($this->value($before));

        if ($end !== null && $beg !== null && $end <= $beg) {
            $this->addError($field, $message);
        }

        return $this;
    }

    /** Date (type="date") qui ne doit pas être dans le futur. */
    public function notInFuture(string $field, string $message): self
    {
        $date = self::parse($this->value($field), 'Y-m-d');
        if ($date !== null && $date > new DateTimeImmutable('today')) {
            $this->addError($field, $message);
        }

        return $this;
    }

    /** Ajoute une erreur calculée ailleurs (ex. « e-mail déjà utilisé »). */
    public function addError(string $field, string $message): self
    {
        // On garde la première erreur : c'est en général la plus utile à corriger.
        $this->errors[$field] ??= $message;

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function value(string $field): string
    {
        return (string) ($this->data[$field] ?? '');
    }

    private static function parse(string $value, string $format): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }
}
