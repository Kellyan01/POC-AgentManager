<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Model\Agent;
use App\Model\ReferenceItem;
use App\Model\Training;
use App\Repository\AgentRepository;
use App\Repository\ReferenceRepository;
use App\Repository\TrainingRepository;
use DateTimeImmutable;

/**
 * Sessions de formation suivies par les agents.
 *
 * Saisie et modification : Administrateur et Manager. Suppression (correction
 * d'une saisie erronée) : Administrateur (route Access::Admin).
 */
final class TrainingController extends AbstractController
{
    /** Valeurs du champ « résultat » → colonne is_passed. */
    private const RESULTS = ['pending' => null, 'passed' => true, 'failed' => false];

    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly TrainingRepository $trainings,
        private readonly AgentRepository $agents,
        private readonly ReferenceRepository $formations,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function new(Request $request, int $id): Response
    {
        $agent = $this->activeAgent($id);
        if ($agent instanceof Response) {
            return $agent;
        }

        return $this->renderForm($agent, null, [
            'formation' => '', 'date_beg' => date('Y-m-d\T09:00'), 'date_end' => '', 'result' => 'pending',
        ], []);
    }

    public function create(Request $request, int $id): Response
    {
        $agent = $this->activeAgent($id);
        if ($agent instanceof Response) {
            return $agent;
        }

        $values = $this->values($request);
        $validator = $this->validate($values);
        if ($validator->fails()) {
            return $this->renderForm($agent, null, $values, $validator->errors(), 422);
        }

        $this->trainings->insert($this->toTraining(null, $id, $values));

        return $this->success(sprintf('Session de formation enregistrée pour %s.', $agent->name), '/agents/' . $id);
    }

    public function edit(Request $request, int $id): Response
    {
        $training = $this->found($this->trainings->findById($id));
        $agent = $this->activeAgent($training->agentId);
        if ($agent instanceof Response) {
            return $agent;
        }

        $result = array_search($training->isPassed, self::RESULTS, true);

        return $this->renderForm($agent, $training, [
            'formation' => (string) $training->formationId,
            'date_beg'  => input_datetime($training->dateBeg),
            'date_end'  => input_datetime($training->dateEnd),
            'result'    => (string) $result,
        ], []);
    }

    public function update(Request $request, int $id): Response
    {
        $training = $this->found($this->trainings->findById($id));
        $agent = $this->activeAgent($training->agentId);
        if ($agent instanceof Response) {
            return $agent;
        }

        $values = $this->values($request);
        $validator = $this->validate($values);
        if ($validator->fails()) {
            return $this->renderForm($agent, $training, $values, $validator->errors(), 422);
        }

        $this->trainings->update($this->toTraining($id, $agent->id, $values));

        return $this->success('Session de formation mise à jour.', '/agents/' . $agent->id);
    }

    public function delete(Request $request, int $id): Response
    {
        $training = $this->found($this->trainings->findById($id));
        $this->trainings->delete($id);

        return $this->success(
            sprintf('Session « %s » du %s supprimée.', $training->formation, format_date($training->dateBeg)),
            '/agents/' . $training->agentId,
        );
    }

    /** L'agent doit exister et être en service ; sinon 404 ou redirection avec message. */
    private function activeAgent(int $id): Agent|Response
    {
        $agent = $this->found($this->agents->findById($id));

        return $agent->isArchived()
            ? $this->failure('Cet agent est archivé : son historique de formation n\'est plus modifiable.', '/agents/' . $id)
            : $agent;
    }

    /** @return array{formation: string, date_beg: string, date_end: string, result: string} */
    private function values(Request $request): array
    {
        return [
            'formation' => (string) $request->input('formation'),
            'date_beg'  => (string) $request->input('date_beg'),
            'date_end'  => (string) $request->input('date_end'),
            'result'    => (string) ($request->input('result') ?? 'pending'),
        ];
    }

    /** @param array{formation: string, date_beg: string, date_end: string, result: string} $values */
    private function validate(array $values): Validator
    {
        $validator = (new Validator($values))
            ->required('formation', 'Choisissez une formation.')
            ->in('formation', array_map(static fn (ReferenceItem $f): int => (int) $f->id, $this->formations->findAll()))
            ->required('date_beg', 'Indiquez la date de début.')->dateTime('date_beg')
            ->dateTime('date_end')
            ->after('date_end', 'date_beg', 'La fin doit être postérieure au début.')
            ->in('result', array_keys(self::RESULTS));

        // Un résultat (réussie / échouée) suppose une formation terminée.
        if (in_array($values['result'], ['passed', 'failed'], true)) {
            $end = datetime_to_sql($values['date_end']);
            if ($end === null) {
                $validator->addError('date_end', 'Indiquez la date de fin pour enregistrer un résultat.');
            } elseif (new DateTimeImmutable($end) > new DateTimeImmutable()) {
                $validator->addError('result', 'Le résultat ne peut être saisi qu\'une fois la formation terminée.');
            }
        }

        return $validator;
    }

    /** @param array{formation: string, date_beg: string, date_end: string, result: string} $values */
    private function toTraining(?int $id, int $agentId, array $values): Training
    {
        $end = datetime_to_sql($values['date_end']);

        return new Training(
            $id,
            $agentId,
            (int) $values['formation'],
            new DateTimeImmutable((string) datetime_to_sql($values['date_beg'])),
            $end === null ? null : new DateTimeImmutable($end),
            self::RESULTS[$values['result']],
        );
    }

    /**
     * @param array{formation: string, date_beg: string, date_end: string, result: string} $values
     * @param array<string, string> $errors
     */
    private function renderForm(Agent $agent, ?Training $training, array $values, array $errors, int $status = 200): Response
    {
        return $this->render('trainings/form', [
            'title'      => ($training === null ? 'Nouvelle session de formation' : 'Modifier une session de formation') . ' — ' . $agent->name,
            'agent'      => $agent,
            'training'   => $training,
            'values'     => $values,
            'errors'     => $errors,
            'formations' => $this->formations->findAll(),
        ], $status);
    }
}
