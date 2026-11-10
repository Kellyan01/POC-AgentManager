<?php
/**
 * Tableau de bord.
 *
 * @var App\Core\View              $this
 * @var App\Model\User             $currentUser
 * @var list<App\Model\Operation>  $activeOps
 * @var list<App\Model\Operation>  $plannedOps
 * @var list<array{agent_id: int, name: string, tier: string, team_name: ?string}> $availableAgents
 * @var list<App\Model\Threat>     $godThreats
 * @var list<App\Model\Training>   $latestTrainings
 */
$operationRow = function (App\Model\Operation $operation): string {
    return '<li><a class="list__main" href="/operations/' . e($operation->id) . '">' . e($operation->codeName) . '</a>'
        . ' <span class="serial">' . e($operation->serial) . '</span>'
        . ' <time datetime="' . e(iso_date($operation->dateBeg)) . '">' . e(format_date($operation->dateBeg, true)) . '</time>'
        . ' ' . $this->partial('status', ['status' => (string) $operation->status]) . '</li>';
};
?>
<header class="page-header">
    <div>
        <p class="kicker">Centre opérationnel // Situation au <time datetime="<?= e(date('Y-m-d\TH:i')) ?>"><?= e(date('d/m/Y H:i')) ?></time></p>
        <h1 class="page-title">Tableau de bord</h1>
    </div>
    <div class="page-actions">
        <a class="btn btn--primary" href="/operations/new">Déclarer une opération</a>
    </div>
</header>

<div class="grid grid--2">
    <section class="panel hud" aria-labelledby="dash-ops">
        <h2 id="dash-ops" class="panel__title">Opérations en cours et planifiées</h2>
        <p class="stat">
            <span class="stat__value"><?= count($activeOps) ?></span><span class="stat__label">en cours</span>
            <span class="stat__value"><?= count($plannedOps) ?></span><span class="stat__label">planifiée(s)</span>
        </p>
        <?php if ($activeOps === [] && $plannedOps === []): ?>
            <p class="empty">Aucune opération en cours ni planifiée.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach (array_merge($activeOps, $plannedOps) as $operation): ?>
                    <?= $operationRow($operation) ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel hud" aria-labelledby="dash-agents">
        <h2 id="dash-agents" class="panel__title">Agents disponibles <small>aucune opération en cours</small></h2>
        <p class="stat"><span class="stat__value"><?= count($availableAgents) ?></span><span class="stat__label">agent(s) mobilisable(s)</span></p>
        <?php if ($availableAgents === []): ?>
            <p class="empty">Tous les agents sont engagés.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($availableAgents as $agent): ?>
                    <li>
                        <a class="list__main" href="/agents/<?= e($agent['agent_id']) ?>"><?= e($agent['name']) ?></a>
                        <?= $this->partial('tier', ['tier' => $agent['tier']]) ?>
                        <span class="tag tag--muted"><?= e($agent['team_name'] ?? 'Sans équipe') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel hud hud--danger" aria-labelledby="dash-threats">
        <h2 id="dash-threats" class="panel__title">Menaces de tier God</h2>
        <p class="stat"><span class="stat__value"><?= count($godThreats) ?></span><span class="stat__label">menace(s) de niveau maximal</span></p>
        <?php if ($godThreats === []): ?>
            <p class="empty">Aucune menace de tier God répertoriée.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($godThreats as $threat): ?>
                    <li>
                        <span class="agent-line list__main">
                            <?= $this->partial('portrait', ['url' => $threat->portrait, 'alt' => 'Portrait de ' . $threat->name, 'kind' => 'threat', 'size' => 'small']) ?>
                            <a href="/threats/<?= e($threat->id) ?>"><?= e($threat->name) ?></a>
                        </span>
                        <span class="serial"><?= e($threat->serial) ?></span>
                        <span class="tag tag--danger"><?= e($threat->operationCount) ?> opération(s)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel hud" aria-labelledby="dash-trainings">
        <h2 id="dash-trainings" class="panel__title">Dernières formations</h2>
        <?php if ($latestTrainings === []): ?>
            <p class="empty">Aucune session de formation.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($latestTrainings as $training): ?>
                    <li>
                        <span class="list__main">
                            <a href="/agents/<?= e($training->agentId) ?>"><?= e($training->agentName) ?></a> — <?= e($training->formation) ?>
                        </span>
                        <time datetime="<?= e(iso_date($training->dateBeg)) ?>"><?= e(format_date($training->dateBeg)) ?></time>
                        <span class="status status--<?= e($training->resultModifier()) ?>"><?= e($training->resultLabel()) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
