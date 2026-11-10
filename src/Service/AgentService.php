<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Exception\BusinessRuleException;
use App\Repository\AgentRepository;
use App\Repository\MembershipRepository;
use PDO;
use Throwable;

/**
 * Règles métier propres aux agents.
 */
final class AgentService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AgentRepository $agents,
        private readonly MembershipRepository $memberships,
    ) {
    }

    /**
     * Archive un agent (suppression logique) et clôt son appartenance en cours,
     * dans une même transaction : un agent archivé ne fait plus partie d'aucune équipe.
     * Ses participations aux opérations sont conservées (historique).
     *
     * @return list<string> noms de code des opérations planifiées ou en cours où il figure encore
     *                      (l'appelant affiche un avertissement)
     */
    public function archive(int $agentId): array
    {
        $agent = $this->agents->findById($agentId);
        if ($agent === null || $agent->isArchived()) {
            throw new BusinessRuleException('Cet agent est déjà archivé.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->memberships->closeCurrentForAgent($agentId);
            $this->agents->archive($agentId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->agents->activeOperationCodeNames($agentId);
    }
}
