<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Exception\BusinessRuleException;
use App\Model\Agent;
use App\Model\Operation;
use App\Model\Team;
use App\Repository\AgentRepository;
use App\Repository\MembershipRepository;
use App\Repository\MysqlError;
use App\Repository\OperationRepository;
use PDO;
use PDOException;
use Throwable;

/**
 * Règles métier des opérations. Toute écriture qui touche plusieurs tables est
 * faite dans une transaction : l'opération est enregistrée entièrement, ou pas du tout.
 */
final class OperationService
{
    private const MAX_SERIAL_ATTEMPTS = 3;

    public function __construct(
        private readonly PDO $pdo,
        private readonly OperationRepository $operations,
        private readonly MembershipRepository $memberships,
        private readonly AgentRepository $agents,
        private readonly SerialGenerator $serials,
    ) {
    }

    /**
     * Crée l'opération avec ses missions, lieux, menaces et compétences requises (règle 6).
     * L'utilisateur qui déclare l'opération en devient le déclarant (Operations.Id_User).
     *
     * @return int identifiant de l'opération créée
     */
    public function create(OperationData $data, int $declarantId): int
    {
        $this->assertCardinalities($data);

        for ($attempt = 1; ; $attempt++) {
            $this->pdo->beginTransaction();
            try {
                $serial = $this->serials->nextOperationSerial((int) $data->dateBeg->format('Y'));
                $id = $this->operations->insert(new Operation(
                    null, $serial, $data->codeName, $data->dateBeg, $data->dateEnd, $data->brief, $data->statusId, $declarantId,
                ));
                $this->saveLinks($id, $data);
                $this->pdo->commit();

                return $id;
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                if (!$e instanceof PDOException || !MysqlError::is($e, MysqlError::DUPLICATE_ENTRY)) {
                    throw $e;
                }
                // MySQL nomme l'index en cause : « Duplicate entry '...' for key 'Operations.serial' ».
                if (!str_contains($e->getMessage(), ".serial'")) {
                    throw new BusinessRuleException('Ce nom de code est déjà utilisé par une autre opération.');
                }
                // Numéro de série pris par une création simultanée : on recommence avec le suivant.
                if ($attempt === self::MAX_SERIAL_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /** Met à jour l'opération et remplace ses liaisons, dans une transaction. */
    public function update(Operation $operation, OperationData $data): void
    {
        $this->assertCardinalities($data);

        $this->pdo->beginTransaction();
        try {
            $this->operations->update(new Operation(
                $operation->id, $operation->serial, $data->codeName, $data->dateBeg, $data->dateEnd,
                $data->brief, $data->statusId, $operation->declarantId,
            ));
            $this->saveLinks((int) $operation->id, $data);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Affecte une équipe et copie ses membres actuels en participants (règle 7),
     * dans une transaction. Les membres engagés sur une autre opération aux dates
     * qui se chevauchent sont écartés et signalés (règle 8, décision Q4).
     *
     * @throws BusinessRuleException équipe archivée ou déjà affectée
     */
    public function assignTeam(Operation $operation, Team $team): TeamAssignmentResult
    {
        if ($team->isArchived()) {
            throw new BusinessRuleException(sprintf('L\'équipe %s est archivée : elle ne peut plus être affectée à une opération.', $team->name));
        }
        $operationId = (int) $operation->id;
        if ($this->operations->hasTeam($operationId, (int) $team->id)) {
            throw new BusinessRuleException(sprintf('L\'équipe %s est déjà affectée à cette opération.', $team->name));
        }

        $added = [];
        $alreadyIncluded = [];
        $skipped = [];

        $this->pdo->beginTransaction();
        try {
            $this->operations->addTeam($operationId, (int) $team->id);

            foreach ($this->memberships->currentMemberIds((int) $team->id) as $agentId) {
                $agent = $this->agents->findById($agentId);
                if ($agent === null) {
                    continue;
                }
                if ($this->operations->isParticipant($operationId, $agentId)) {
                    $alreadyIncluded[] = $agent->name;
                    continue;
                }
                $conflicts = $this->operations->findConflicts($agentId, $operationId);
                if ($conflicts !== []) {
                    $skipped[$agent->name] = implode(', ', $conflicts);
                    continue;
                }
                $this->operations->addParticipant($operationId, $agentId);
                $added[] = $agent->name;
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return new TeamAssignmentResult($added, $alreadyIncluded, $skipped);
    }

    /**
     * Retire l'équipe de l'opération. Les participants restent (décision Q5) :
     * la liste des participants s'ajuste ensuite à la main.
     */
    public function removeTeam(Operation $operation, Team $team): void
    {
        if ($this->operations->removeTeam((int) $operation->id, (int) $team->id) === 0) {
            throw new BusinessRuleException(sprintf('L\'équipe %s n\'est pas affectée à cette opération.', $team->name));
        }
    }

    /**
     * Ajoute un participant (renfort, suggestion de staffing...).
     *
     * @throws BusinessRuleException agent archivé, déjà participant, ou indisponible (règle 8)
     */
    public function addAgent(Operation $operation, Agent $agent): void
    {
        if ($agent->isArchived()) {
            throw new BusinessRuleException(sprintf('%s est archivé : il ne peut pas participer à une opération.', $agent->name));
        }
        if ($this->operations->isParticipant((int) $operation->id, (int) $agent->id)) {
            throw new BusinessRuleException(sprintf('%s participe déjà à l\'opération %s.', $agent->name, $operation->codeName));
        }

        $conflicts = $this->operations->findConflicts((int) $agent->id, (int) $operation->id);
        if ($conflicts !== []) {
            throw new BusinessRuleException(sprintf(
                '%s est indisponible : déjà engagé sur %s, dont les dates chevauchent celles de %s.',
                $agent->name,
                implode(', ', $conflicts),
                $operation->codeName,
            ));
        }

        try {
            $this->operations->addParticipant((int) $operation->id, (int) $agent->id);
        } catch (PDOException $e) {
            if (MysqlError::is($e, MysqlError::DUPLICATE_ENTRY)) {
                throw new BusinessRuleException(sprintf('%s participe déjà à cette opération.', $agent->name));
            }
            throw $e;
        }
    }

    public function removeAgent(Operation $operation, Agent $agent): void
    {
        if ($this->operations->removeParticipant((int) $operation->id, (int) $agent->id) === 0) {
            throw new BusinessRuleException(sprintf('%s ne participe pas à cette opération.', $agent->name));
        }
    }

    /**
     * La base ne garantit pas les cardinalités 1,n (au moins une mission, au moins
     * un lieu) : le service les vérifie, en plus du contrôleur.
     */
    private function assertCardinalities(OperationData $data): void
    {
        if ($data->missions === [] || $data->locations === []) {
            throw new BusinessRuleException('Une opération doit avoir au moins un type de mission et au moins un lieu.');
        }
        if ($data->dateEnd !== null && $data->dateEnd <= $data->dateBeg) {
            throw new BusinessRuleException('La date de fin doit être postérieure à la date de début.');
        }
    }

    private function saveLinks(int $operationId, OperationData $data): void
    {
        $this->operations->replaceLinks($operationId, 'missions', $data->missions);
        $this->operations->replaceLinks($operationId, 'locations', $data->locations);
        $this->operations->replaceLinks($operationId, 'threats', $data->threats);
        $this->operations->replaceLinks($operationId, 'abilities', $data->abilities);
    }
}
