<?php

declare(strict_types=1);

namespace App\Core\Exception;

use DomainException;

/**
 * Violation d'une règle métier (« cet agent est déjà engagé sur une autre opération »).
 *
 * Levée par les services ; le message est destiné à l'utilisateur et sera affiché
 * tel quel. Ne jamais y mettre de détail technique (requête SQL, trace...).
 */
final class BusinessRuleException extends DomainException
{
}
