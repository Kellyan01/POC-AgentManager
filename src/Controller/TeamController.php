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
use App\Model\Team;
use App\Model\User;
use App\Repository\AgentRepository;
use App\Repository\MembershipRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Service\MembershipService;

/**
 * Équipes : liste, fiche, membres.
 *
 * Création, modification et archivage : Administrateur (routes Access::Admin).
 * Gestion des membres : Administrateur ou manager de l'équipe (vérifié ici).
 */
final class TeamController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly TeamRepository $teams,
        private readonly AgentRepository $agents,
        private readonly UserRepository $users,
        private readonly MembershipRepository $memberships,
        private readonly MembershipService $membershipService,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request): Response
    {
        return $this->render('teams/index', [
            'title' => 'Équipes',
            'teams' => $this->teams->findAll(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $team = $this->found($this->teams->findById($id));
        $canManage = $this->auth->canManageTeam($team) && !$team->isArchived();

        return $this->render('teams/show', [
            'title'          => $team->name . ' — Équipe',
            'team'           => $team,
            'canManage'      => $canManage,
            'currentMembers' => $this->memberships->findMembersOfTeam($id, true),
            'formerMembers'  => $this->memberships->findMembersOfTeam($id, false),
            'candidates'     => $canManage ? $this->candidates($team, $this->auth->requireUser()) : [],
        ]);
    }

    public function new(Request $request): Response
    {
        return $this->renderForm(null, ['name' => '', 'manager' => ''], []);
    }

    public function create(Request $request): Response
    {
        $values = $this->values($request);
        $validator = $this->validate($values, null);
        if ($validator->fails()) {
            return $this->renderForm(null, $values, $validator->errors(), 422);
        }

        $id = $this->teams->insert(new Team(null, $values['name'], (int) $values['manager']));

        return $this->success(sprintf('Équipe %s créée.', $values['name']), '/teams/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $team = $this->found($this->teams->findById($id));
        if ($team->isArchived()) {
            return $this->failure('Cette équipe est archivée : elle n\'est plus modifiable.', '/teams/' . $id);
        }

        return $this->renderForm($team, ['name' => $team->name, 'manager' => (string) $team->managerId], []);
    }

    public function update(Request $request, int $id): Response
    {
        $team = $this->found($this->teams->findById($id));
        if ($team->isArchived()) {
            return $this->failure('Cette équipe est archivée : elle n\'est plus modifiable.', '/teams/' . $id);
        }

        $values = $this->values($request);
        $validator = $this->validate($values, $id);
        if ($validator->fails()) {
            return $this->renderForm($team, $values, $validator->errors(), 422);
        }

        $this->teams->update(new Team($id, $values['name'], (int) $values['manager']));

        return $this->success(sprintf('Équipe %s mise à jour.', $values['name']), '/teams/' . $id);
    }

    public function archive(Request $request, int $id): Response
    {
        $team = $this->found($this->teams->findById($id));

        try {
            $released = $this->membershipService->archiveTeam($team);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), '/teams/' . $id);
        }

        return $this->success(
            sprintf('Équipe %s archivée. %d appartenance(s) en cours ont été closes.', $team->name, $released),
            '/teams/' . $id,
        );
    }

    /** Intègre un agent dans l'équipe (avec transfert depuis son équipe actuelle si autorisé). */
    public function addMember(Request $request, int $id): Response
    {
        $team = $this->found($this->teams->findById($id));
        $this->denyUnless($this->auth->canManageTeam($team));

        $agentId = $request->input('agent');
        $agent = $agentId !== null && ctype_digit($agentId) ? $this->agents->findById((int) $agentId) : null;
        $back = $request->input('from') === 'agent' && $agent !== null ? '/agents/' . $agent->id : '/teams/' . $id;

        if ($agent === null) {
            return $this->failure('Choisissez un agent.', $back);
        }

        try {
            $this->membershipService->addMember($team, $agent, $this->auth->requireUser());
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), $back);
        }

        return $this->success(sprintf('%s rejoint l\'équipe %s.', $agent->name, $team->name), $back);
    }

    /** Libère un agent de l'équipe. */
    public function removeMember(Request $request, int $id, int $agentId): Response
    {
        $team = $this->found($this->teams->findById($id));
        $this->denyUnless($this->auth->canManageTeam($team));
        $agent = $this->found($this->agents->findById($agentId));
        $back = $request->input('from') === 'agent' ? '/agents/' . $agentId : '/teams/' . $id;

        try {
            $this->membershipService->removeMember($team, $agent);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), $back);
        }

        return $this->success(sprintf('%s a quitté l\'équipe %s.', $agent->name, $team->name), $back);
    }

    /**
     * Agents que l'utilisateur peut intégrer dans cette équipe (décision Q2) :
     * agents sans équipe, ou membres d'une équipe qu'il gère aussi.
     *
     * @return list<Agent>
     */
    private function candidates(Team $team, User $user): array
    {
        $managedTeamIds = array_map(static fn (Team $t): ?int => $t->id, $this->teams->findManagedBy((int) $user->id));

        return array_values(array_filter(
            $this->agents->findAll(),
            static fn (Agent $agent): bool => $agent->teamId !== $team->id
                && ($agent->teamId === null || $user->isAdmin() || in_array($agent->teamId, $managedTeamIds, true)),
        ));
    }

    /** @return array{name: string, manager: string} */
    private function values(Request $request): array
    {
        return ['name' => (string) $request->input('name'), 'manager' => (string) $request->input('manager')];
    }

    /** @param array{name: string, manager: string} $values */
    private function validate(array $values, ?int $id): Validator
    {
        $validator = (new Validator($values))
            ->required('name')->maxLength('name', 50)
            ->required('manager', 'Choisissez le manager de l\'équipe.')
            ->in('manager', array_map(static fn (User $u): int => (int) $u->id, $this->users->findActive()));

        if ($values['name'] !== '' && $this->teams->nameExists($values['name'], $id)) {
            $validator->addError('name', 'Ce nom est déjà pris (y compris par une équipe archivée).');
        }

        return $validator;
    }

    /**
     * @param array{name: string, manager: string} $values
     * @param array<string, string> $errors
     */
    private function renderForm(?Team $team, array $values, array $errors, int $status = 200): Response
    {
        return $this->render('teams/form', [
            'title'    => $team === null ? 'Nouvelle équipe' : 'Modifier l\'équipe ' . $team->name,
            'team'     => $team,
            'values'   => $values,
            'errors'   => $errors,
            'managers' => $this->users->findActive(),
        ], $status);
    }
}
