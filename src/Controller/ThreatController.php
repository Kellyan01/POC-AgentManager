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
use App\Model\MediaOwner;
use App\Model\Threat;
use App\Model\Tier;
use App\Repository\MediaRepository;
use App\Repository\ThreatRepository;
use App\Repository\TierRepository;
use App\Service\ThreatService;

/**
 * Menaces : liste, fiche, création et modification (Administrateur et Manager).
 */
final class ThreatController extends AbstractController
{
    private const MAX_INFORMATIONS = 5000;

    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly ThreatRepository $threats,
        private readonly TierRepository $tiers,
        private readonly ThreatService $threatService,
        private readonly MediaRepository $medias,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request): Response
    {
        return $this->render('threats/index', [
            'title'   => 'Menaces',
            'threats' => $this->threats->findAll(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $threat = $this->found($this->threats->findById($id));

        return $this->render('threats/show', [
            'title'      => $threat->name . ' — Fiche menace',
            'threat'     => $threat,
            'operations' => $this->threats->findOperations($id),
            'medias'     => $this->medias->findForOwner(MediaOwner::Threat, $id),
        ]);
    }

    /** Suppression (Administrateur, route Access::Admin) d'une menace visée par aucune opération. */
    public function delete(Request $request, int $id): Response
    {
        $threat = $this->found($this->threats->findById($id));

        try {
            $this->threatService->delete($threat);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), '/threats/' . $id);
        }

        return $this->success(sprintf('Fiche %s (%s) supprimée.', $threat->name, $threat->serial), '/threats');
    }

    public function new(Request $request): Response
    {
        return $this->renderForm(null, ['name' => '', 'tier' => '', 'informations' => ''], []);
    }

    public function create(Request $request): Response
    {
        $values = $this->values($request);
        $validator = $this->validate($values);
        if ($validator->fails()) {
            return $this->renderForm(null, $values, $validator->errors(), 422);
        }

        $id = $this->threatService->create($values['name'], $values['informations'] ?: null, (int) $values['tier']);
        $threat = $this->found($this->threats->findById($id));

        return $this->success(sprintf('Menace %s enregistrée sous le numéro %s.', $threat->name, $threat->serial), '/threats/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $threat = $this->found($this->threats->findById($id));

        return $this->renderForm($threat, [
            'name'         => $threat->name,
            'tier'         => (string) $threat->tierId,
            'informations' => (string) $threat->informations,
        ], []);
    }

    public function update(Request $request, int $id): Response
    {
        $threat = $this->found($this->threats->findById($id));
        $values = $this->values($request);
        $validator = $this->validate($values);
        if ($validator->fails()) {
            return $this->renderForm($threat, $values, $validator->errors(), 422);
        }

        $this->threats->update(new Threat($id, $values['name'], $threat->serial, $values['informations'] ?: null, (int) $values['tier']));

        return $this->success(sprintf('Fiche de %s mise à jour.', $values['name']), '/threats/' . $id);
    }

    /** @return array{name: string, tier: string, informations: string} */
    private function values(Request $request): array
    {
        return [
            'name'         => (string) $request->input('name'),
            'tier'         => (string) $request->input('tier'),
            'informations' => (string) $request->input('informations'),
        ];
    }

    /** @param array{name: string, tier: string, informations: string} $values */
    private function validate(array $values): Validator
    {
        return (new Validator($values))
            ->required('name')->maxLength('name', 50)
            ->required('tier', 'Choisissez un tier.')
            ->in('tier', array_map(static fn (Tier $t): int => (int) $t->id, $this->tiers->findAll()))
            ->maxLength('informations', self::MAX_INFORMATIONS);
    }

    /**
     * @param array{name: string, tier: string, informations: string} $values
     * @param array<string, string> $errors
     */
    private function renderForm(?Threat $threat, array $values, array $errors, int $status = 200): Response
    {
        return $this->render('threats/form', [
            'title'     => $threat === null ? 'Nouvelle menace' : 'Modifier la fiche de ' . $threat->name,
            'threat'    => $threat,
            'values'    => $values,
            'errors'    => $errors,
            'tiers'     => $this->tiers->findAll(),
            'maxLength' => self::MAX_INFORMATIONS,
        ], $status);
    }
}
