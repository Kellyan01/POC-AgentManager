<?php

declare(strict_types=1);

namespace App\Core\Exception;

use RuntimeException;

/**
 * Erreur qui doit aboutir à une page d'erreur HTTP (403, 404, 405...).
 * Interceptée par App, qui affiche la vue views/errors/{code}.php.
 */
final class HttpException extends RuntimeException
{
    public function __construct(private readonly int $statusCode, string $message = '')
    {
        parent::__construct($message, $statusCode);
    }

    public static function notFound(string $message = 'Ce dossier est introuvable.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Votre niveau d\'habilitation ne permet pas cette action.'): self
    {
        return new self(403, $message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
