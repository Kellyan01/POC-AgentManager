<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Model\Status;
use App\Repository\OperationRepository;
use App\Repository\ThreatRepository;
use App\Repository\TrainingRepository;

/**
 * Tableau de bord : opérations en cours et planifiées, agents disponibles,
 * menaces de tier God, dernières formations.
 */
final class DashboardController extends AbstractController
{
    private const LATEST_TRAININGS = 5;

    public function __construct(
        View $view,
        Session $session,
        Auth $auth,
        private readonly OperationRepository $operations,
        private readonly ThreatRepository $threats,
        private readonly TrainingRepository $trainings,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function index(Request $request): Response
    {
        return $this->render('dashboard/index', [
            'title'           => 'Tableau de bord',
            'activeOps'       => $this->operations->findByStatuses([Status::IN_PROGRESS]),
            'plannedOps'      => $this->operations->findByStatuses([Status::PLANNED]),
            'availableAgents' => $this->operations->findAvailableAgents(),
            'godThreats'      => $this->threats->findGodTier(),
            'latestTrainings' => $this->trainings->findLatest(self::LATEST_TRAININGS),
        ]);
    }
}
