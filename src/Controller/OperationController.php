<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Exception\BusinessRuleException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Model\Agent;
use App\Model\MediaOwner;
use App\Model\Operation;
use App\Model\ReferenceItem;
use App\Model\Status;
use App\Model\Team;
use App\Model\Threat;
use App\Repository\AgentRepository;
use App\Repository\MediaRepository;
use App\Repository\OperationRepository;
use App\Repository\ReferenceRepository;
use App\Repository\TeamRepository;
use App\Repository\ThreatRepository;
use App\Service\OperationData;
use App\Service\OperationService;
use App\Service\StaffingService;
use DateTimeImmutable;

/**
 * Opérations : liste, ordre de mission, déclaration, modification, équipes,
 * participants et staffing.
 *
 * Déclaration : tout utilisateur connecté (il devient le déclarant).
 * Modification et affectations : Administrateur ou déclarant (vérifié ici).
 */
final class OperationController extends AbstractController
{
    private const MAX_BRIEF = 5000;

    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly OperationRepository $operations,
        private readonly OperationService $operationService,
        private readonly StaffingService $staffing,
        private readonly TeamRepository $teams,
        private readonly AgentRepository $agents,
        private readonly ThreatRepository $threats,
        private readonly ReferenceRepository $statuses,
        private readonly ReferenceRepository $missions,
        private readonly ReferenceRepository $locations,
        private readonly ReferenceRepository $abilities,
        private readonly MediaRepository $medias,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $statusId = $status !== null && ctype_digit($status) ? (int) $status : null;

        return $this->render('operations/index', [
            'title'      => 'Opérations',
            'operations' => $this->operations->search($statusId),
            'statuses'   => $this->statuses->findAll(),
            'statusId'   => $statusId,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $operation = $this->found($this->operations->findById($id));
        $canEdit = $this->auth->canEditOperation($operation);

        $threats = $this->operations->findThreats($id);
        $participants = $this->operations->findParticipants($id);
        $strongest = $this->staffing->strongestThreat($threats);
        $assignedTeams = $this->operations->findTeams($id);
        $assignedTeamIds = array_column($assignedTeams, 'id');
        $participantIds = array_column($participants, 'agent_id');

        return $this->render('operations/show', [
            'title'             => 'Opération ' . $operation->codeName . ' — Ordre de mission',
            'operation'         => $operation,
            'canEdit'           => $canEdit,
            'threats'           => $threats,
            'missions'          => $this->operations->missionLabels($id),
            'locations'         => $this->operations->locationLabels($id),
            'requiredAbilities' => $this->operations->requiredAbilityLabels($id),
            'teams'             => $assignedTeams,
            'participants'      => $participants,
            'strongestThreat'   => $strongest,
            'underTiered'       => $this->staffing->underTiered($participants, $strongest),
            'suggestions'       => $this->staffing->suggest($id),
            'assignableTeams'   => $canEdit
                ? array_values(array_filter($this->teams->findAll(), static fn (Team $t): bool => !in_array($t->id, $assignedTeamIds, true)))
                : [],
            'addableAgents'     => $canEdit
                ? array_values(array_filter($this->agents->findAll(), static fn (Agent $a): bool => !in_array($a->id, $participantIds, true)))
                : [],
            'medias'            => $this->medias->findForOwner(MediaOwner::Operation, $id),
        ]);
    }

    /** Suppression (Administrateur ou déclarant, décision Q6) : médias et fichiers compris. */
    public function delete(Request $request, int $id): Response
    {
        $operation = $this->editable($id);
        $this->operationService->delete($operation);

        return $this->success(sprintf('Opération %s (%s) supprimée.', $operation->codeName, $operation->serial), '/operations');
    }

    public function new(Request $request): Response
    {
        $planned = $this->statusIdByLabel(Status::PLANNED);

        return $this->renderForm(null, [
            'code_name' => '', 'date_beg' => '', 'date_end' => '', 'status' => (string) $planned, 'brief' => '',
            'missions' => [], 'locations' => [], 'threats' => [], 'abilities' => [],
        ], []);
    }

    public function create(Request $request): Response
    {
        $values = $this->values($request);
        $validator = $this->validate($values, null);
        if ($validator->fails()) {
            return $this->renderForm(null, $values, $validator->errors(), 422);
        }

        try {
            $id = $this->operationService->create($this->toData($values), (int) $this->auth->requireUser()->id);
        } catch (BusinessRuleException $e) {
            return $this->renderForm(null, $values, ['code_name' => $e->getMessage()], 422);
        }
        $operation = $this->found($this->operations->findById($id));

        return $this->success(
            sprintf('Opération %s déclarée sous le numéro %s.', $operation->codeName, $operation->serial),
            '/operations/' . $id,
        );
    }

    public function edit(Request $request, int $id): Response
    {
        $operation = $this->editable($id);

        return $this->renderForm($operation, [
            'code_name' => $operation->codeName,
            'date_beg'  => input_datetime($operation->dateBeg),
            'date_end'  => input_datetime($operation->dateEnd),
            'status'    => (string) $operation->statusId,
            'brief'     => (string) $operation->brief,
            'missions'  => $this->operations->linkedIds($id, 'missions'),
            'locations' => $this->operations->linkedIds($id, 'locations'),
            'threats'   => $this->operations->linkedIds($id, 'threats'),
            'abilities' => $this->operations->linkedIds($id, 'abilities'),
        ], []);
    }

    public function update(Request $request, int $id): Response
    {
        $operation = $this->editable($id);
        $values = $this->values($request);
        $validator = $this->validate($values, $id);
        if ($validator->fails()) {
            return $this->renderForm($operation, $values, $validator->errors(), 422);
        }

        $this->operationService->update($operation, $this->toData($values));

        return $this->success(sprintf('Opération %s mise à jour.', $values['code_name']), '/operations/' . $id);
    }

    public function assignTeam(Request $request, int $id): Response
    {
        $operation = $this->editable($id);
        $teamId = $request->input('team');
        $team = $teamId !== null && ctype_digit($teamId) ? $this->teams->findById((int) $teamId) : null;
        $back = '/operations/' . $id;

        if ($team === null) {
            return $this->failure('Choisissez une équipe.', $back);
        }

        try {
            $result = $this->operationService->assignTeam($operation, $team);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), $back);
        }

        $message = sprintf('Équipe %s affectée.', $team->name);
        $message .= $result->added === []
            ? ' Aucun membre ajouté aux participants.'
            : sprintf(' %d membre(s) ajouté(s) aux participants : %s.', count($result->added), implode(', ', $result->added));
        if ($result->alreadyIncluded !== []) {
            $message .= sprintf(' Déjà participant(s) : %s.', implode(', ', $result->alreadyIncluded));
        }
        $this->session->flash('success', $message);

        foreach ($result->skipped as $name => $conflicts) {
            $this->session->flash('warning', sprintf('%s n\'a pas été ajouté : déjà engagé sur %s aux mêmes dates.', $name, $conflicts));
        }

        return $this->redirect($back);
    }

    public function removeTeam(Request $request, int $id, int $teamId): Response
    {
        $operation = $this->editable($id);
        $team = $this->found($this->teams->findById($teamId));

        try {
            $this->operationService->removeTeam($operation, $team);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), '/operations/' . $id);
        }

        return $this->success(
            sprintf('Équipe %s retirée. Les participants sont conservés : ajustez la liste si nécessaire.', $team->name),
            '/operations/' . $id,
        );
    }

    public function addAgent(Request $request, int $id): Response
    {
        $operation = $this->editable($id);
        $agentId = $request->input('agent');
        $agent = $agentId !== null && ctype_digit($agentId) ? $this->agents->findById((int) $agentId) : null;
        $back = '/operations/' . $id;

        if ($agent === null) {
            return $this->failure('Choisissez un agent.', $back);
        }

        try {
            $this->operationService->addAgent($operation, $agent);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), $back);
        }

        return $this->success(sprintf('%s ajouté aux participants.', $agent->name), $back);
    }

    public function removeAgent(Request $request, int $id, int $agentId): Response
    {
        $operation = $this->editable($id);
        $agent = $this->found($this->agents->findById($agentId));

        try {
            $this->operationService->removeAgent($operation, $agent);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), '/operations/' . $id);
        }

        return $this->success(sprintf('%s retiré des participants.', $agent->name), '/operations/' . $id);
    }

    /** Opération existante (sinon 404) que l'utilisateur a le droit de modifier (sinon 403). */
    private function editable(int $id): Operation
    {
        $operation = $this->found($this->operations->findById($id));
        $this->denyUnless($this->auth->canEditOperation($operation));

        return $operation;
    }

    private function statusIdByLabel(string $label): ?int
    {
        foreach ($this->statuses->findAll() as $status) {
            if ($status->label === $label) {
                return $status->id;
            }
        }

        return null;
    }

    /**
     * @return array{code_name: string, date_beg: string, date_end: string, status: string, brief: string,
     *               missions: list<int>, locations: list<int>, threats: list<int>, abilities: list<int>}
     */
    private function values(Request $request): array
    {
        return [
            'code_name' => (string) $request->input('code_name'),
            'date_beg'  => (string) $request->input('date_beg'),
            'date_end'  => (string) $request->input('date_end'),
            'status'    => (string) $request->input('status'),
            'brief'     => (string) $request->input('brief'),
            'missions'  => $request->inputIds('missions'),
            'locations' => $request->inputIds('locations'),
            'threats'   => $request->inputIds('threats'),
            'abilities' => $request->inputIds('abilities'),
        ];
    }

    /** @param array<string, mixed> $values */
    private function validate(array $values, ?int $id): Validator
    {
        $ids = static fn (array $items): array => array_map(static fn (ReferenceItem|Threat $item): int => (int) $item->id, $items);

        $validator = (new Validator([
            'code_name' => $values['code_name'],
            'date_beg'  => $values['date_beg'],
            'date_end'  => $values['date_end'],
            'status'    => $values['status'],
            'brief'     => $values['brief'],
        ]))
            ->required('code_name')->maxLength('code_name', 50)
            ->required('date_beg', 'Indiquez la date de début.')->dateTime('date_beg')
            ->dateTime('date_end')
            ->after('date_end', 'date_beg', 'La fin doit être postérieure au début.')
            ->required('status', 'Choisissez un statut.')->in('status', $ids($this->statuses->findAll()))
            ->maxLength('brief', self::MAX_BRIEF);

        if ($values['code_name'] !== '' && $this->operations->codeNameExists($values['code_name'], $id)) {
            $validator->addError('code_name', 'Ce nom de code est déjà utilisé par une autre opération.');
        }

        // Cardinalités 1,n non garanties par la base : au moins une mission et un lieu.
        if ($values['missions'] === []) {
            $validator->addError('missions', 'Cochez au moins un type de mission.');
        }
        if ($values['locations'] === []) {
            $validator->addError('locations', 'Cochez au moins un lieu.');
        }

        // Les identifiants cochés doivent exister (formulaire modifié à la main).
        $checks = [
            'missions'  => $ids($this->missions->findAll()),
            'locations' => $ids($this->locations->findAll()),
            'threats'   => $ids($this->threats->findAll()),
            'abilities' => $ids($this->abilities->findAll()),
        ];
        foreach ($checks as $field => $known) {
            if (array_diff($values[$field], $known) !== []) {
                $validator->addError($field, 'Valeur inconnue.');
            }
        }

        return $validator;
    }

    /** @param array<string, mixed> $values */
    private function toData(array $values): OperationData
    {
        $end = datetime_to_sql($values['date_end']);

        return new OperationData(
            $values['code_name'],
            new DateTimeImmutable((string) datetime_to_sql($values['date_beg'])),
            $end === null ? null : new DateTimeImmutable($end),
            $values['brief'] !== '' ? $values['brief'] : null,
            (int) $values['status'],
            $values['missions'],
            $values['locations'],
            $values['threats'],
            $values['abilities'],
        );
    }

    /**
     * @param array<string, mixed>  $values
     * @param array<string, string> $errors
     */
    private function renderForm(?Operation $operation, array $values, array $errors, int $status = 200): Response
    {
        return $this->render('operations/form', [
            'title'     => $operation === null ? 'Déclarer une opération' : 'Modifier l\'opération ' . $operation->codeName,
            'operation' => $operation,
            'values'    => $values,
            'errors'    => $errors,
            'statuses'  => $this->statuses->findAll(),
            'missions'  => $this->missions->findAll(),
            'locations' => $this->locations->findAll(),
            'threats'   => $this->threats->findAll(),
            'abilities' => $this->abilities->findAll(),
            'maxBrief'  => self::MAX_BRIEF,
        ], $status);
    }
}
