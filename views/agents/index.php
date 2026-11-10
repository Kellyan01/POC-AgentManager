<?php
/**
 * @var App\Core\View                 $this
 * @var list<App\Model\Agent>         $agents
 * @var array<string, int|string>     $filters
 * @var list<App\Model\Team>          $teams
 * @var list<App\Model\Tier>          $tiers
 * @var list<App\Model\ReferenceItem> $archetypes
 * @var list<App\Model\ReferenceItem> $abilities
 */
use App\Repository\AgentRepository;

$teamOptions = [[AgentRepository::FILTER_NO_TEAM, '— Sans équipe —']];
foreach ($teams as $team) {
    $teamOptions[] = [$team->id, $team->name];
}
?>
<header class="page-header">
    <div>
        <p class="kicker">Personnel en service // Registre</p>
        <h1 class="page-title">Agents</h1>
    </div>
    <div class="page-actions">
        <a class="btn btn--primary" href="/agents/new">Nouvel agent</a>
    </div>
</header>

<form class="filters panel" method="get" action="/agents" data-autosubmit role="search" aria-label="Filtrer les agents">
    <?= $this->partial('field', ['name' => 'team', 'label' => 'Équipe', 'type' => 'select', 'value' => $filters['team'] ?? '', 'placeholder' => 'Toutes', 'options' => $teamOptions]) ?>
    <?= $this->partial('field', ['name' => 'tier', 'label' => 'Tier', 'type' => 'select', 'value' => $filters['tier'] ?? '', 'placeholder' => 'Tous', 'options' => array_map(static fn ($t) => [$t->id, $t->tier], $tiers)]) ?>
    <?= $this->partial('field', ['name' => 'archetype', 'label' => 'Archétype', 'type' => 'select', 'value' => $filters['archetype'] ?? '', 'placeholder' => 'Tous', 'options' => array_map(static fn ($a) => [$a->id, $a->label], $archetypes)]) ?>
    <?= $this->partial('field', ['name' => 'ability', 'label' => 'Compétence', 'type' => 'select', 'value' => $filters['ability'] ?? '', 'placeholder' => 'Toutes', 'options' => array_map(static fn ($a) => [$a->id, $a->label], $abilities)]) ?>
    <div class="filters__actions">
        <button type="submit" class="btn">Filtrer</button>
        <?php if ($filters !== []): ?>
            <a class="btn btn--ghost" href="/agents">Réinitialiser</a>
        <?php endif; ?>
    </div>
</form>

<section class="panel hud" aria-label="Liste des agents">
    <?php if ($agents === []): ?>
        <p class="empty">Aucun agent ne correspond à ces critères.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <caption><?= count($agents) ?> agent(s) en service<?= $filters !== [] ? ' correspondant aux filtres' : '' ?></caption>
                <thead>
                <tr>
                    <th scope="col">Agent</th>
                    <th scope="col">Archétype</th>
                    <th scope="col">Tier</th>
                    <th scope="col">Équipe actuelle</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($agents as $agent): ?>
                    <tr>
                        <th scope="row" data-label="Agent">
                            <span class="agent-line">
                                <?= $this->partial('portrait', ['url' => $agent->portrait, 'alt' => 'Portrait de ' . $agent->name, 'size' => 'small']) ?>
                                <a class="agent-line__name" href="/agents/<?= e($agent->id) ?>"><?= e($agent->name) ?></a>
                            </span>
                        </th>
                        <td data-label="Archétype"><?= e($agent->archetype) ?></td>
                        <td data-label="Tier"><?= $this->partial('tier', ['tier' => (string) $agent->tier]) ?></td>
                        <td data-label="Équipe actuelle">
                            <?php if ($agent->teamId !== null): ?>
                                <a href="/teams/<?= e($agent->teamId) ?>"><?= e($agent->teamName) ?></a>
                            <?php else: ?>
                                <span class="tag tag--muted">Sans équipe</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
