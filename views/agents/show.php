<?php
/**
 * Dossier d'un agent, présenté comme un dossier classifié.
 *
 * @var App\Core\View       $this
 * @var App\Model\Agent     $agent
 * @var App\Model\User      $currentUser
 * @var list<array{team_id: int, team_name: string, team_archived: bool, date_join: DateTimeImmutable, date_leave: ?DateTimeImmutable}> $teamHistory
 * @var App\Model\Team|null $currentTeam
 * @var bool                $canRelease   l'utilisateur gère l'équipe actuelle de l'agent
 * @var bool                $canMove      l'utilisateur peut changer l'agent d'équipe
 * @var list<App\Model\Team> $destinations équipes vers lesquelles il peut le transférer
 * @var list<App\Model\Training> $trainings
 * @var list<array{id: int, ability: string, sources: list<string>}> $abilities
 */
$age = age($agent->dob);
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/agents">Agents</a> // Dossier <?= e($agent->fileNumber()) ?> // Eyes only</p>
        <div class="dossier__header">
            <h1 class="page-title"><?= e($agent->name) ?></h1>
            <?php if ($agent->isArchived()): ?>
                <span class="stamp stamp--muted">Archived</span>
            <?php else: ?>
                <span class="stamp">Classified</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$agent->isArchived()): ?>
        <div class="page-actions">
            <a class="btn" href="/agents/<?= e($agent->id) ?>/edit">Modifier</a>
            <?php if ($currentUser->isAdmin()): ?>
                <form class="inline-form" method="post" action="/agents/<?= e($agent->id) ?>/archive"
                      data-confirm="Archiver <?= e($agent->name) ?> ? Son appartenance à une équipe sera close.">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="btn btn--danger">Archiver</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</header>

<?php if ($agent->isArchived()): ?>
    <div class="alert alert--warning" role="status">
        <span class="alert__label">Archivé</span>
        <span class="alert__message">Agent archivé le <?= e(format_date($agent->archivedAt, true)) ?>. Le dossier est conservé pour l'historique et n'est plus modifiable.</span>
    </div>
<?php endif; ?>

<div class="dossier">
    <aside aria-label="Portrait">
        <?= $this->partial('portrait', ['url' => $agent->portrait, 'alt' => 'Portrait de ' . $agent->name, 'kind' => 'agent']) ?>
    </aside>

    <div class="stack">
        <section class="panel hud" aria-labelledby="identity-title">
            <h2 id="identity-title" class="panel__title">Identification <small><?= e($agent->fileNumber()) ?></small></h2>
            <dl class="meta">
                <dt>Archétype</dt>
                <dd><?= e($agent->archetype) ?></dd>
                <dt>Tier</dt>
                <dd><?= $this->partial('tier', ['tier' => (string) $agent->tier]) ?></dd>
                <dt>Naissance</dt>
                <dd>
                    <?php if ($agent->dob !== null): ?>
                        <time datetime="<?= e(input_date($agent->dob)) ?>"><?= e(format_date($agent->dob)) ?></time> (<?= e($age) ?> ans)
                    <?php else: ?>
                        <span class="tag tag--muted">Non datée</span>
                    <?php endif; ?>
                </dd>
                <dt>Équipe actuelle</dt>
                <dd>
                    <?php if ($agent->teamId !== null): ?>
                        <a href="/teams/<?= e($agent->teamId) ?>"><?= e($agent->teamName) ?></a>
                    <?php else: ?>
                        <span class="tag tag--muted">Sans équipe</span>
                    <?php endif; ?>
                </dd>
                <dt>Dossier ouvert</dt>
                <dd><time datetime="<?= e(iso_date($agent->createdAt)) ?>"><?= e(format_date($agent->createdAt)) ?></time></dd>
            </dl>
        </section>

        <?php if (!$agent->isArchived()): ?>
            <section class="panel hud" aria-labelledby="assign-title">
                <h2 id="assign-title" class="panel__title">Affectation</h2>
                <?php if ($canMove && $destinations !== []): ?>
                    <form method="post" action="/agents/<?= e($agent->id) ?>/team" class="inline-fields">
                        <?= $this->csrfField() ?>
                        <?= $this->partial('field', [
                            'name' => 'team', 'label' => $currentTeam === null ? 'Intégrer l\'équipe' : 'Transférer vers', 'type' => 'select',
                            'required' => true, 'placeholder' => '— Choisir une équipe —',
                            'options' => array_map(static fn ($t) => [$t->id, $t->name], $destinations),
                            'hint' => $currentTeam === null ? null : 'L\'appartenance à ' . $currentTeam->name . ' sera close, puis la nouvelle ouverte.',
                        ]) ?>
                        <button type="submit" class="btn btn--primary"><?= $currentTeam === null ? 'Intégrer' : 'Transférer' ?></button>
                    </form>
                <?php elseif ($currentTeam !== null && !$canRelease): ?>
                    <p>Membre de <strong><?= e($currentTeam->name) ?></strong>, équipe gérée par <?= e($currentTeam->managerName) ?>. Pour l'intégrer à l'une de vos équipes, son manager doit d'abord le libérer.</p>
                <?php else: ?>
                    <p class="empty">Aucune équipe de destination disponible pour vous.</p>
                <?php endif; ?>

                <?php if ($canRelease): ?>
                    <form class="panel__footer" method="post" action="/teams/<?= e($currentTeam->id) ?>/members/<?= e($agent->id) ?>/remove"
                          data-confirm="Libérer <?= e($agent->name) ?> de l'équipe <?= e($currentTeam->name) ?> ?">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="from" value="agent">
                        <button type="submit" class="btn btn--danger btn--small">Libérer de l'équipe <?= e($currentTeam->name) ?></button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="panel hud" aria-labelledby="teams-title">
            <h2 id="teams-title" class="panel__title">Historique des équipes</h2>
            <?php if ($teamHistory === []): ?>
                <p class="empty">Aucune affectation enregistrée.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table data-table--cards">
                        <caption class="visually-hidden">Équipes successives de <?= e($agent->name) ?>, de la plus récente à la plus ancienne</caption>
                        <thead>
                        <tr><th scope="col">Équipe</th><th scope="col">Arrivée</th><th scope="col">Départ</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($teamHistory as $membership): ?>
                            <tr>
                                <td data-label="Équipe">
                                    <a href="/teams/<?= e($membership['team_id']) ?>"><?= e($membership['team_name']) ?></a>
                                    <?php if ($membership['team_archived']): ?><span class="tag tag--muted">Archivée</span><?php endif; ?>
                                </td>
                                <td data-label="Arrivée"><time datetime="<?= e(iso_date($membership['date_join'])) ?>"><?= e(format_date($membership['date_join'])) ?></time></td>
                                <td data-label="Départ">
                                    <?php if ($membership['date_leave'] === null): ?>
                                        <span class="status status--active">En poste</span>
                                    <?php else: ?>
                                        <time datetime="<?= e(iso_date($membership['date_leave'])) ?>"><?= e(format_date($membership['date_leave'])) ?></time>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel hud" aria-labelledby="trainings-title">
            <h2 id="trainings-title" class="panel__title">
                Formations
                <?php if (!$agent->isArchived()): ?>
                    <a class="btn btn--small" href="/agents/<?= e($agent->id) ?>/trainings/new">Nouvelle session</a>
                <?php endif; ?>
            </h2>
            <?php if ($trainings === []): ?>
                <p class="empty">Aucune formation suivie.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table data-table--cards">
                        <caption class="visually-hidden">Sessions de formation de <?= e($agent->name) ?>, échecs compris</caption>
                        <thead>
                        <tr>
                            <th scope="col">Formation</th><th scope="col">Début</th><th scope="col">Fin</th><th scope="col">Résultat</th>
                            <?php if (!$agent->isArchived()): ?><th scope="col" class="actions">Actions</th><?php endif; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($trainings as $training): ?>
                            <tr>
                                <td data-label="Formation"><?= e($training->formation) ?></td>
                                <td data-label="Début"><time datetime="<?= e(iso_date($training->dateBeg)) ?>"><?= e(format_date($training->dateBeg)) ?></time></td>
                                <td data-label="Fin"><?= $training->dateEnd === null ? '—' : '<time datetime="' . e(iso_date($training->dateEnd)) . '">' . e(format_date($training->dateEnd)) . '</time>' ?></td>
                                <td data-label="Résultat"><span class="status status--<?= e($training->resultModifier()) ?>"><?= e($training->resultLabel()) ?></span></td>
                                <?php if (!$agent->isArchived()): ?>
                                    <td class="actions" data-label="Actions">
                                        <a class="btn btn--small" href="/trainings/<?= e($training->id) ?>/edit">Modifier<span class="visually-hidden"> la session <?= e($training->formation) ?> du <?= e(format_date($training->dateBeg)) ?></span></a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel hud" aria-labelledby="abilities-title">
            <h2 id="abilities-title" class="panel__title">
                Compétences
                <a class="btn btn--small" href="/agents/<?= e($agent->id) ?>/abilities"><?= $agent->isArchived() ? 'Détail' : 'Gérer l\'expérience' ?></a>
            </h2>
            <?php if ($abilities === []): ?>
                <p class="empty">Aucune compétence.</p>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($abilities as $ability): ?>
                        <li>
                            <span class="list__main"><?= e($ability['ability']) ?></span>
                            <?= $this->partial('ability-sources', ['sources' => $ability['sources']]) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
