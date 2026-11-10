<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Core\Auth;
use App\Core\Exception\BusinessRuleException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Model\Tier;
use App\Repository\TierRepository;

/**
 * Gestion des tiers (libellé + niveau numérique).
 */
final class TierController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly TierRepository $tiers,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request): Response
    {
        return $this->renderIndex(['tier' => '', 'level' => ''], []);
    }

    public function create(Request $request): Response
    {
        $values = $this->values($request);
        $validator = $this->validate($values, null);
        if ($validator->fails()) {
            return $this->renderIndex($values, $validator->errors(), 422);
        }

        $this->tiers->insert(new Tier(null, $values['tier'], (int) $values['level']));

        return $this->success(sprintf('Tier « %s » ajouté.', $values['tier']), '/admin/tiers');
    }

    public function edit(Request $request, int $id): Response
    {
        $tier = $this->found($this->tiers->findById($id));

        return $this->renderEdit($tier, ['tier' => $tier->tier, 'level' => (string) $tier->level], []);
    }

    public function update(Request $request, int $id): Response
    {
        $tier = $this->found($this->tiers->findById($id));
        $values = $this->values($request);

        $validator = $this->validate($values, $id);
        if ($tier->tier === Tier::GOD && $values['tier'] !== Tier::GOD) {
            $validator->addError('tier', 'Le tier « God » est utilisé par le tableau de bord : il ne peut pas être renommé.');
        }
        if ($validator->fails()) {
            return $this->renderEdit($tier, $values, $validator->errors(), 422);
        }

        $this->tiers->update(new Tier($id, $values['tier'], (int) $values['level']));

        return $this->success(sprintf('Tier « %s » mis à jour.', $values['tier']), '/admin/tiers');
    }

    public function delete(Request $request, int $id): Response
    {
        $tier = $this->found($this->tiers->findById($id));
        if ($tier->tier === Tier::GOD) {
            return $this->failure('Le tier « God » est utilisé par le tableau de bord : il ne peut pas être supprimé.', '/admin/tiers');
        }

        try {
            $this->tiers->delete($id);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), '/admin/tiers');
        }

        return $this->success(sprintf('Tier « %s » supprimé.', $tier->tier), '/admin/tiers');
    }

    /** @return array{tier: string, level: string} */
    private function values(Request $request): array
    {
        return ['tier' => (string) $request->input('tier'), 'level' => (string) $request->input('level')];
    }

    /** @param array{tier: string, level: string} $values */
    private function validate(array $values, ?int $id): Validator
    {
        $validator = (new Validator($values))
            ->required('tier')->maxLength('tier', 10)
            ->required('level')->integer('level', 1, 100);

        if ($values['tier'] !== '' && $this->tiers->tierExists($values['tier'], $id)) {
            $validator->addError('tier', 'Ce tier existe déjà.');
        }
        if (ctype_digit($values['level']) && $this->tiers->levelExists((int) $values['level'], $id)) {
            $validator->addError('level', 'Ce niveau est déjà attribué à un autre tier.');
        }

        return $validator;
    }

    /**
     * @param array{tier: string, level: string} $values
     * @param array<string, string> $errors
     */
    private function renderIndex(array $values, array $errors, int $status = 200): Response
    {
        return $this->render('admin/tiers/index', [
            'title'  => 'Tiers — Référentiels',
            'tiers'  => $this->tiers->findAll(),
            'values' => $values,
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array{tier: string, level: string} $values
     * @param array<string, string> $errors
     */
    private function renderEdit(Tier $tier, array $values, array $errors, int $status = 200): Response
    {
        return $this->render('admin/tiers/edit', [
            'title'  => 'Modifier le tier « ' . $tier->tier . ' »',
            'tier'   => $tier,
            'values' => $values,
            'errors' => $errors,
        ], $status);
    }
}
