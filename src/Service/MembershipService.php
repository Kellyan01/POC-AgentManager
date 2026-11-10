<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Exception\BusinessRuleException;
use App\Model\Agent;
use App\Model\Team;
use App\Model\User;
use App\Repository\MembershipRepository;
use App\Repository\MysqlError;
use App\Repository\TeamRepository;
use PDO;
use PDOException;
use Throwable;

/**
 * Appartenance des agents aux équipes.
 *
 * Règle « une équipe à la fois » : la base l'impose (colonne générée
 * Memberships.active_agent + index UNIQUE). Changer un agent d'équipe consiste
 * donc à clore l'appartenance en cours PUIS à en ouvrir une nouvelle, dans une
 * transaction : soit les deux réussissent, soit rien ne change.
 */
final class MembershipService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MembershipRepository $memberships,
        private readonly TeamRepository $teams,
    ) {
    }

    /**
     * Intègre un agent dans une équipe, en le retirant de son équipe actuelle s'il en a une.
     *
     * Le droit sur l'équipe de destination est vérifié par le contrôleur. Ici, on
     * vérifie le droit sur l'équipe d'ORIGINE : un Manager ne peut pas « prendre »
     * l'agent d'une équipe qu'il ne gère pas ; le manager de cette équipe doit
     * d'abord le libérer.
     *
     * @throws BusinessRuleException
     */
    public function addMember(Team $team, Agent $agent, User $actor): void
    {
        if ($team->isArchived()) {
            throw new BusinessRuleException(sprintf('L\'équipe %s est archivée : elle ne peut plus recevoir de membres.', $team->name));
        }
        if ($agent->isArchived()) {
            throw new BusinessRuleException(sprintf('%s est archivé : il ne peut pas rejoindre une équipe.', $agent->name));
        }

        $current = $this->memberships->findCurrentForAgent((int) $agent->id);
        if ($current !== null && $current->teamId === $team->id) {
            throw new BusinessRuleException(sprintf('%s fait déjà partie de l\'équipe %s.', $agent->name, $team->name));
        }

        if ($current !== null) {
            $currentTeam = $this->teams->findById($current->teamId);
            if ($currentTeam !== null && !$actor->canManageTeam($currentTeam)) {
                throw new BusinessRuleException(sprintf(
                    '%s appartient à l\'équipe %s, gérée par %s : son manager doit d\'abord le libérer.',
                    $agent->name,
                    $currentTeam->name,
                    $currentTeam->managerName,
                ));
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->memberships->closeCurrentForAgent((int) $agent->id);
            $this->memberships->open((int) $agent->id, (int) $team->id);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            // Filet de sécurité : si une autre requête a affecté l'agent entre-temps,
            // la contrainte UNIQUE refuse l'insertion. On traduit l'erreur SQL.
            if (MysqlError::is($e, MysqlError::DUPLICATE_ENTRY)) {
                throw new BusinessRuleException(sprintf('%s appartient déjà à une équipe. Rechargez la page et recommencez.', $agent->name));
            }
            throw $e;
        }
    }

    /**
     * Libère un agent de l'équipe (clôture de son appartenance en cours).
     *
     * @throws BusinessRuleException si l'agent n'est pas membre actuel de cette équipe
     */
    public function removeMember(Team $team, Agent $agent): void
    {
        $current = $this->memberships->findCurrentForAgent((int) $agent->id);
        if ($current === null || $current->teamId !== $team->id) {
            throw new BusinessRuleException(sprintf('%s ne fait pas partie de l\'équipe %s.', $agent->name, $team->name));
        }

        $this->memberships->closeCurrentForAgent((int) $agent->id);
    }

    /**
     * Archive une équipe et clôt toutes les appartenances en cours de ses membres,
     * dans la même transaction.
     *
     * @return int nombre de membres libérés
     * @throws BusinessRuleException si l'équipe est déjà archivée
     */
    public function archiveTeam(Team $team): int
    {
        if ($team->isArchived()) {
            throw new BusinessRuleException(sprintf('L\'équipe %s est déjà archivée.', $team->name));
        }

        $this->pdo->beginTransaction();
        try {
            $released = $this->memberships->closeAllForTeam((int) $team->id);
            $this->teams->archive((int) $team->id);
            $this->pdo->commit();

            return $released;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
