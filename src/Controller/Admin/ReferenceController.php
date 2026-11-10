<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Core\Auth;
use App\Core\Exception\BusinessRuleException;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Model\ReferenceItem;
use App\Repository\ReferenceRepository;

/**
 * Gestion des référentiels à libellé unique (compétences, archétypes, formations,
 * statuts, types de mission, lieux). Le référentiel est désigné par $ref, fixé
 * dans la table des routes.
 */
final class ReferenceController extends AbstractController
{
    /**
     * @param array<string, ReferenceRepository> $repositories indexés par slug
     */
    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly array $repositories,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request, string $ref): Response
    {
        return $this->renderIndex($this->repository($ref), ['label' => '', 'abilities' => []], []);
    }

    public function create(Request $request, string $ref): Response
    {
        $repository = $this->repository($ref);
        $values = ['label' => (string) $request->input('label'), 'abilities' => $request->inputIds('abilities')];

        $validator = $this->validate($repository, $values, null);
        if ($validator->fails()) {
            return $this->renderIndex($repository, $values, $validator->errors(), 422);
        }

        $repository->save(new ReferenceItem(null, $values['label']), $values['abilities']);

        return $this->success(
            sprintf('« %s » ajouté au référentiel %s.', $values['label'], $repository->definition()->title),
            '/admin/' . $ref,
        );
    }

    public function edit(Request $request, string $ref, int $id): Response
    {
        $repository = $this->repository($ref);
        $item = $this->found($repository->findById($id));

        return $this->renderEdit($repository, $item, [
            'label'     => $item->label,
            'abilities' => $repository->abilityIds($id),
        ], []);
    }

    public function update(Request $request, string $ref, int $id): Response
    {
        $repository = $this->repository($ref);
        $item = $this->found($repository->findById($id));
        $values = ['label' => (string) $request->input('label'), 'abilities' => $request->inputIds('abilities')];

        $validator = $this->validate($repository, $values, $id);
        if ($repository->definition()->isLocked($item->label) && $values['label'] !== $item->label) {
            $validator->addError('label', 'Ce libellé est utilisé par les règles de l\'application : il ne peut pas être renommé.');
        }
        if ($validator->fails()) {
            return $this->renderEdit($repository, $item, $values, $validator->errors(), 422);
        }

        $repository->save(new ReferenceItem($id, $values['label']), $values['abilities']);

        return $this->success(sprintf('« %s » mis à jour.', $values['label']), '/admin/' . $ref);
    }

    public function delete(Request $request, string $ref, int $id): Response
    {
        $repository = $this->repository($ref);
        $item = $this->found($repository->findById($id));

        if ($repository->definition()->isLocked($item->label)) {
            return $this->failure('Ce libellé est utilisé par les règles de l\'application : il ne peut pas être supprimé.', '/admin/' . $ref);
        }

        try {
            $repository->delete($id);
        } catch (BusinessRuleException $e) {
            return $this->failure(sprintf('« %s » : %s', $item->label, $e->getMessage()), '/admin/' . $ref);
        }

        return $this->success(sprintf('« %s » supprimé.', $item->label), '/admin/' . $ref);
    }

    private function repository(string $ref): ReferenceRepository
    {
        return $this->repositories[$ref] ?? throw HttpException::notFound();
    }

    /** @param array{label: string, abilities: list<int>} $values */
    private function validate(ReferenceRepository $repository, array $values, ?int $id): Validator
    {
        $definition = $repository->definition();
        $validator = (new Validator(['label' => $values['label']]))
            ->required('label')
            ->maxLength('label', $definition->maxLength);

        if ($values['label'] !== '' && $repository->labelExists($values['label'], $id)) {
            $validator->addError('label', 'Cette valeur existe déjà.');
        }

        $knownAbilities = array_map(fn (ReferenceItem $a): int => (int) $a->id, $this->repositories['abilities']->findAll());
        if (array_diff($values['abilities'], $knownAbilities) !== []) {
            $validator->addError('abilities', 'Compétence inconnue.');
        }

        return $validator;
    }

    /**
     * @param array{label: string, abilities: list<int>} $values
     * @param array<string, string> $errors
     */
    private function renderIndex(ReferenceRepository $repository, array $values, array $errors, int $status = 200): Response
    {
        $definition = $repository->definition();

        return $this->render('admin/references/index', [
            'title'         => $definition->title . ' — Référentiels',
            'definition'    => $definition,
            'items'         => $repository->findAll(),
            'abilityLabels' => $repository->abilityLabelsByItem(),
            'abilities'     => $definition->hasAbilities() ? $this->repositories['abilities']->findAll() : [],
            'values'        => $values,
            'errors'        => $errors,
        ], $status);
    }

    /**
     * @param array{label: string, abilities: list<int>} $values
     * @param array<string, string> $errors
     */
    private function renderEdit(ReferenceRepository $repository, ReferenceItem $item, array $values, array $errors, int $status = 200): Response
    {
        $definition = $repository->definition();

        return $this->render('admin/references/edit', [
            'title'      => 'Modifier « ' . $item->label . ' » — ' . $definition->title,
            'definition' => $definition,
            'item'       => $item,
            'abilities'  => $definition->hasAbilities() ? $this->repositories['abilities']->findAll() : [],
            'values'     => $values,
            'errors'     => $errors,
        ], $status);
    }
}
