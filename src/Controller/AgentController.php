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
use App\Model\ReferenceItem;
use App\Model\Tier;
use App\Repository\AgentRepository;
use App\Repository\MembershipRepository;
use App\Repository\ReferenceRepository;
use App\Repository\TeamRepository;
use App\Repository\TierRepository;
use App\Service\AgentService;
use DateTimeImmutable;

/**
 * Agents : liste filtrable, dossier, création, modification, archivage.
 *
 * Création et modification : Administrateur et Manager (tout utilisateur connecté).
 * Archivage : Administrateur (route Access::Admin).
 */
final class AgentController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly AgentRepository $agents,
        private readonly TeamRepository $teams,
        private readonly TierRepository $tiers,
        private readonly ReferenceRepository $archetypes,
        private readonly ReferenceRepository $abilities,
        private readonly MembershipRepository $memberships,
        private readonly AgentService $agentService,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request): Response
    {
        // Filtres lus dans l'URL : seules les valeurs bien formées sont retenues.
        $filters = [];
        $team = $request->query('team');
        if ($team === AgentRepository::FILTER_NO_TEAM || ($team !== null && ctype_digit($team))) {
            $filters['team'] = $team;
        }
        foreach (['tier', 'archetype', 'ability'] as $key) {
            $value = $request->query($key);
            if ($value !== null && ctype_digit($value)) {
                $filters[$key] = (int) $value;
            }
        }

        return $this->render('agents/index', [
            'title'      => 'Agents',
            'agents'     => $this->agents->search($filters),
            'filters'    => $filters,
            'teams'      => $this->teams->findAll(),
            'tiers'      => $this->tiers->findAll(),
            'archetypes' => $this->archetypes->findAll(),
            'abilities'  => $this->abilities->findAll(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $agent = $this->found($this->agents->findById($id));

        return $this->render('agents/show', [
            'title'       => $agent->name . ' — Dossier agent',
            'agent'       => $agent,
            'teamHistory' => $this->memberships->findHistoryForAgent($id),
        ]);
    }

    public function new(Request $request): Response
    {
        return $this->renderForm(null, ['name' => '', 'dob' => '', 'archetype' => '', 'tier' => ''], []);
    }

    public function create(Request $request): Response
    {
        $values = $this->values($request);
        $validator = $this->validate($values);
        if ($validator->fails()) {
            return $this->renderForm(null, $values, $validator->errors(), 422);
        }

        $id = $this->agents->insert($this->toAgent(null, $values));

        return $this->success(sprintf('Dossier de %s ouvert.', $values['name']), '/agents/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $agent = $this->found($this->agents->findById($id));
        if ($agent->isArchived()) {
            return $this->failure('Cet agent est archivé : son dossier n\'est plus modifiable.', '/agents/' . $id);
        }

        return $this->renderForm($agent, [
            'name'      => $agent->name,
            'dob'       => input_date($agent->dob),
            'archetype' => (string) $agent->archetypeId,
            'tier'      => (string) $agent->tierId,
        ], []);
    }

    public function update(Request $request, int $id): Response
    {
        $agent = $this->found($this->agents->findById($id));
        if ($agent->isArchived()) {
            return $this->failure('Cet agent est archivé : son dossier n\'est plus modifiable.', '/agents/' . $id);
        }

        $values = $this->values($request);
        $validator = $this->validate($values);
        if ($validator->fails()) {
            return $this->renderForm($agent, $values, $validator->errors(), 422);
        }

        $this->agents->update($this->toAgent($id, $values));

        return $this->success(sprintf('Dossier de %s mis à jour.', $values['name']), '/agents/' . $id);
    }

    public function archive(Request $request, int $id): Response
    {
        $agent = $this->found($this->agents->findById($id));

        try {
            $operations = $this->agentService->archive($id);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), '/agents/' . $id);
        }

        $this->session->flash('success', sprintf('%s a été archivé. Son appartenance à une équipe est close.', $agent->name));
        if ($operations !== []) {
            $this->session->flash('warning', sprintf(
                '%s figure encore parmi les participants de : %s. Pensez à ajuster ces opérations.',
                $agent->name,
                implode(', ', $operations),
            ));
        }

        return $this->redirect('/agents/' . $id);
    }

    /** @return array{name: string, dob: string, archetype: string, tier: string} */
    private function values(Request $request): array
    {
        return [
            'name'      => (string) $request->input('name'),
            'dob'       => (string) $request->input('dob'),
            'archetype' => (string) $request->input('archetype'),
            'tier'      => (string) $request->input('tier'),
        ];
    }

    /** @param array{name: string, dob: string, archetype: string, tier: string} $values */
    private function validate(array $values): Validator
    {
        return (new Validator($values))
            ->required('name')->maxLength('name', 50)
            ->date('dob')->notInFuture('dob', 'La date de naissance ne peut pas être dans le futur.')
            ->required('archetype', 'Choisissez un archétype.')
            ->in('archetype', array_map(static fn (ReferenceItem $a): int => (int) $a->id, $this->archetypes->findAll()))
            ->required('tier', 'Choisissez un tier.')
            ->in('tier', array_map(static fn (Tier $t): int => (int) $t->id, $this->tiers->findAll()));
    }

    /** @param array{name: string, dob: string, archetype: string, tier: string} $values */
    private function toAgent(?int $id, array $values): Agent
    {
        return new Agent(
            $id,
            $values['name'],
            $values['dob'] === '' ? null : new DateTimeImmutable($values['dob']),
            (int) $values['archetype'],
            (int) $values['tier'],
        );
    }

    /**
     * @param array{name: string, dob: string, archetype: string, tier: string} $values
     * @param array<string, string> $errors
     */
    private function renderForm(?Agent $agent, array $values, array $errors, int $status = 200): Response
    {
        return $this->render('agents/form', [
            'title'      => $agent === null ? 'Nouvel agent' : 'Modifier le dossier de ' . $agent->name,
            'agent'      => $agent,
            'values'     => $values,
            'errors'     => $errors,
            'archetypes' => $this->archetypes->findAll(),
            'tiers'      => $this->tiers->findAll(),
        ], $status);
    }
}
