<?php
/**
 * Fiche d'une équipe : membres actuels et anciens, gestion des membres.
 *
 * @var App\Core\View         $this
 * @var App\Model\Team        $team
 * @var App\Model\User        $currentUser
 * @var bool                  $canManage  droit de gérer les membres (et équipe active)
 * @var list<array{agent_id: int, name: string, archived: bool, tier: string, archetype: string, date_join: DateTimeImmutable, date_leave: ?DateTimeImmutable}> $currentMembers
 * @var list<array{agent_id: int, name: string, archived: bool, tier: string, archetype: string, date_join: DateTimeImmutable, date_leave: ?DateTimeImmutable}> $formerMembers
 * @var list<App\Model\Agent> $candidates agents que l'utilisateur peut intégrer
 */
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/teams">Équipes</a> // Unité <?= e(sprintf('TM-%03d', $team->id)) ?></p>
        <div class="dossier__header">
            <h1 class="page-title"><?= e($team->name) ?></h1>
            <?php if ($team->isArchived()): ?><span class="stamp stamp--muted">Archived</span><?php endif; ?>
        </div>
    </div>
    <?php if ($currentUser->isAdmin() && !$team->isArchived()): ?>
        <div class="page-actions">
            <a class="btn" href="/teams/<?= e($team->id) ?>/edit">Modifier</a>
            <form class="inline-form" method="post" action="/teams/<?= e($team->id) ?>/archive"
                  data-confirm="Archiver l'équipe <?= e($team->name) ?> ? Les appartenances de ses <?= e(count($currentMembers)) ?> membre(s) seront closes.">
                <?= $this->csrfField() ?>
                <button type="submit" class="btn btn--danger">Archiver</button>
            </form>
        </div>
    <?php endif; ?>
</header>

<?php if ($team->isArchived()): ?>
    <div class="alert alert--warning" role="status">
        <span class="alert__label">Archivée</span>
        <span class="alert__message">Équipe archivée le <?= e(format_date($team->archivedAt, true)) ?> : elle ne peut plus recevoir de membres ni être affectée à une nouvelle opération.</span>
    </div>
<?php endif; ?>

<div class="grid grid--2">
    <section class="panel hud" aria-labelledby="team-info">
        <h2 id="team-info" class="panel__title">Fiche</h2>
        <dl class="meta">
            <dt>Manager</dt><dd><?= e($team->managerName) ?></dd>
            <dt>Créée le</dt><dd><time datetime="<?= e(iso_date($team->createdAt)) ?>"><?= e(format_date($team->createdAt)) ?></time></dd>
            <dt>Effectif</dt><dd class="data"><?= e(count($currentMembers)) ?> agent(s)</dd>
        </dl>
    </section>

    <?php if ($canManage): ?>
        <section class="panel hud" aria-labelledby="team-add">
            <h2 id="team-add" class="panel__title">Intégrer un agent</h2>
            <?php if ($candidates === []): ?>
                <p class="empty">Aucun agent disponible pour vous : les agents des équipes que vous ne gérez pas doivent d'abord être libérés par leur manager.</p>
            <?php else: ?>
                <form method="post" action="/teams/<?= e($team->id) ?>/members" class="inline-fields">
                    <?= $this->csrfField() ?>
                    <?= $this->partial('field', [
                        'name' => 'agent', 'label' => 'Agent', 'type' => 'select', 'required' => true, 'placeholder' => '— Choisir un agent —',
                        'options' => array_map(static fn ($a) => [$a->id, $a->name . ($a->teamName !== null ? ' (transfert depuis ' . $a->teamName . ')' : ' (sans équipe)')], $candidates),
                        'hint' => 'Agents sans équipe, ou membres d\'une autre équipe que vous gérez (transfert).',
                    ]) ?>
                    <button type="submit" class="btn btn--primary">Intégrer</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<section class="panel hud" aria-labelledby="team-current">
    <h2 id="team-current" class="panel__title">Membres actuels <small><?= e(count($currentMembers)) ?></small></h2>
    <?php if ($currentMembers === []): ?>
        <p class="empty">Aucun membre actuellement.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <caption class="visually-hidden">Membres actuels de <?= e($team->name) ?></caption>
                <thead>
                <tr>
                    <th scope="col">Agent</th><th scope="col">Archétype</th><th scope="col">Tier</th><th scope="col">Depuis le</th>
                    <?php if ($canManage): ?><th scope="col" class="actions">Actions</th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($currentMembers as $member): ?>
                    <tr>
                        <th scope="row" data-label="Agent"><?= $this->partial('agent-name', ['id' => $member['agent_id'], 'name' => $member['name'], 'archived' => $member['archived']]) ?></th>
                        <td data-label="Archétype"><?= e($member['archetype']) ?></td>
                        <td data-label="Tier"><?= $this->partial('tier', ['tier' => $member['tier']]) ?></td>
                        <td data-label="Depuis le"><time datetime="<?= e(iso_date($member['date_join'])) ?>"><?= e(format_date($member['date_join'])) ?></time></td>
                        <?php if ($canManage): ?>
                            <td class="actions" data-label="Actions">
                                <form class="inline-form" method="post" action="/teams/<?= e($team->id) ?>/members/<?= e($member['agent_id']) ?>/remove"
                                      data-confirm="Libérer <?= e($member['name']) ?> de l'équipe <?= e($team->name) ?> ?">
                                    <?= $this->csrfField() ?>
                                    <button type="submit" class="btn btn--danger btn--small">Libérer<span class="visually-hidden"> <?= e($member['name']) ?></span></button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel hud" aria-labelledby="team-former">
    <h2 id="team-former" class="panel__title">Anciens membres <small><?= e(count($formerMembers)) ?></small></h2>
    <?php if ($formerMembers === []): ?>
        <p class="empty">Aucun ancien membre.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <caption class="visually-hidden">Anciens membres de <?= e($team->name) ?>, du départ le plus récent au plus ancien</caption>
                <thead>
                <tr><th scope="col">Agent</th><th scope="col">Arrivée</th><th scope="col">Départ</th></tr>
                </thead>
                <tbody>
                <?php foreach ($formerMembers as $member): ?>
                    <tr>
                        <th scope="row" data-label="Agent"><?= $this->partial('agent-name', ['id' => $member['agent_id'], 'name' => $member['name'], 'archived' => $member['archived']]) ?></th>
                        <td data-label="Arrivée"><time datetime="<?= e(iso_date($member['date_join'])) ?>"><?= e(format_date($member['date_join'])) ?></time></td>
                        <td data-label="Départ"><time datetime="<?= e(iso_date($member['date_leave'])) ?>"><?= e(format_date($member['date_leave'])) ?></time></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
