<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Model\ReferenceItem;
use App\Repository\AgentAbilityRepository;
use App\Repository\AgentRepository;

/**
 * Compétences d'expérience d'un agent (table Abilities_Agents).
 *
 * Seules les compétences que l'agent ne possède par aucune autre source
 * (archétype, formation réussie) peuvent être ajoutées : contrôlé à l'affichage
 * ET à l'enregistrement.
 */
final class AgentAbilityController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly AgentRepository $agents,
        private readonly AgentAbilityRepository $agentAbilities,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request, int $id): Response
    {
        $agent = $this->found($this->agents->findById($id));

        return $this->render('agents/abilities', [
            'title'      => 'Compétences de ' . $agent->name,
            'agent'      => $agent,
            'abilities'  => $this->agentAbilities->findForAgent($id),
            'assignable' => $agent->isArchived() ? [] : $this->agentAbilities->findAssignableExperience($id),
        ]);
    }

    public function add(Request $request, int $id): Response
    {
        $agent = $this->found($this->agents->findById($id));
        $back = '/agents/' . $id . '/abilities';
        if ($agent->isArchived()) {
            return $this->failure('Cet agent est archivé : ses compétences ne sont plus modifiables.', $back);
        }

        $abilityId = $request->input('ability');
        $assignable = $this->agentAbilities->findAssignableExperience($id);
        $ability = null;
        foreach ($assignable as $candidate) {
            if ((string) $candidate->id === $abilityId) {
                $ability = $candidate;
            }
        }

        // Compétence absente de la liste : inconnue, ou déjà acquise par une autre source.
        if (!$ability instanceof ReferenceItem) {
            return $this->failure('Cette compétence ne peut pas être ajoutée : choisissez-en une que l\'agent ne possède pas déjà.', $back);
        }

        $this->agentAbilities->addExperience($id, (int) $ability->id);

        return $this->success(sprintf('Compétence « %s » ajoutée au titre de l\'expérience.', $ability->label), $back);
    }

    public function remove(Request $request, int $id, int $abilityId): Response
    {
        $agent = $this->found($this->agents->findById($id));
        $back = '/agents/' . $id . '/abilities';
        if ($agent->isArchived()) {
            return $this->failure('Cet agent est archivé : ses compétences ne sont plus modifiables.', $back);
        }

        if (!$this->agentAbilities->removeExperience($id, $abilityId)) {
            return $this->failure('Cette compétence n\'est pas une compétence d\'expérience de l\'agent.', $back);
        }

        return $this->success('Compétence d\'expérience retirée.', $back);
    }
}
