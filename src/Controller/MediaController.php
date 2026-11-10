<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Exception\BusinessRuleException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Model\MediaOwner;
use App\Repository\AgentRepository;
use App\Repository\MediaRepository;
use App\Repository\OperationRepository;
use App\Repository\ThreatRepository;
use App\Service\MediaService;

/**
 * Upload et suppression des portraits (agents, menaces) et illustrations (opérations).
 *
 * Droits : ceux de modification de l'entité propriétaire.
 *  - agent (en service) et menace : Administrateur et Manager ;
 *  - opération : Administrateur ou déclarant.
 */
final class MediaController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly MediaRepository $medias,
        private readonly MediaService $mediaService,
        private readonly AgentRepository $agents,
        private readonly ThreatRepository $threats,
        private readonly OperationRepository $operations,
    ) {
        parent::__construct($view, $session, $auth);
    }

    /** $owner est fixé par la route : agents, threats ou operations. */
    public function upload(Request $request, int $id, string $owner): Response
    {
        $type = MediaOwner::from($owner);
        $back = '/' . $type->value . '/' . $id;

        $refusal = $this->checkCanEdit($type, $id);
        if ($refusal !== null) {
            return $this->failure($refusal, $back);
        }

        try {
            $this->mediaService->upload($request->file('image'), $type, $id);
        } catch (BusinessRuleException $e) {
            return $this->failure($e->getMessage(), $back);
        }

        return $this->success('Image enregistrée : elle devient l\'image principale.', $back);
    }

    public function delete(Request $request, int $id): Response
    {
        $media = $this->found($this->medias->findById($id));

        $refusal = $this->checkCanEdit($media->owner, $media->ownerId);
        if ($refusal !== null) {
            return $this->failure($refusal, $media->ownerUrl());
        }

        $this->mediaService->delete($media);

        return $this->success('Image supprimée.', $media->ownerUrl());
    }

    /**
     * Vérifie que l'entité existe (sinon 404) et que l'utilisateur peut la modifier
     * (sinon 403). Renvoie un message si la modification est impossible pour une
     * raison métier (agent archivé), null si tout est permis.
     */
    private function checkCanEdit(MediaOwner $owner, int $id): ?string
    {
        switch ($owner) {
            case MediaOwner::Agent:
                $agent = $this->found($this->agents->findById($id));

                return $agent->isArchived() ? 'Cet agent est archivé : son dossier n\'est plus modifiable.' : null;

            case MediaOwner::Threat:
                $this->found($this->threats->findById($id));

                return null;

            case MediaOwner::Operation:
                $operation = $this->found($this->operations->findById($id));
                $this->denyUnless($this->auth->canEditOperation($operation));

                return null;
        }
    }
}
