<?php
/**
 * Fiche d'une opération, présentée comme un ordre de mission.
 *
 * @var App\Core\View          $this
 * @var App\Model\Operation    $operation
 * @var bool                   $canEdit Administrateur ou déclarant
 * @var list<array{id: int, name: string, serial: string, tier: string, tier_level: int}> $threats
 * @var list<string>           $missions
 * @var list<string>           $locations
 * @var list<string>           $requiredAbilities
 * @var list<array{id: int, name: string, archived: bool, manager_id: int}> $teams
 * @var list<array{agent_id: int, name: string, archived: bool, tier: string, tier_level: int, team_id: ?int, team_name: ?string}> $participants
 * @var array{tier: string, tier_level: int}|null $strongestThreat
 * @var list<int>              $underTiered identifiants des participants de tier insuffisant
 * @var list<array{agent_id: int, name: string, tier: string, tier_level: int, team_name: ?string, abilities: string}> $suggestions
 * @var list<App\Model\Team>   $assignableTeams
 * @var list<App\Model\Agent>  $addableAgents
 * @var list<App\Model\Media>  $medias illustrations, la plus récente d'abord
 */
$opUrl = '/operations/' . $operation->id;
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/operations">Opérations</a> // Ordre de mission <?= e($operation->serial) ?> // Top secret</p>
        <div class="dossier__header">
            <h1 class="page-title"><?= e($operation->codeName) ?></h1>
            <?= $this->partial('status', ['status' => (string) $operation->status]) ?>
        </div>
    </div>
    <?php if ($canEdit): ?>
        <div class="page-actions">
            <a class="btn" href="<?= e($opUrl) ?>/edit">Modifier</a>
            <form class="inline-form" method="post" action="<?= e($opUrl) ?>/delete"
                  data-confirm="Supprimer définitivement l'opération <?= e($operation->codeName) ?>, ses liaisons et ses images ?">
                <?= $this->csrfField() ?>
                <button type="submit" class="btn btn--danger">Supprimer</button>
            </form>
        </div>
    <?php endif; ?>
</header>

<div class="grid grid--2">
    <div>
        <?= $this->partial('portrait', ['url' => $operation->illustration, 'alt' => 'Illustration de l\'opération ' . $operation->codeName, 'kind' => 'operation']) ?>
        <?= $this->partial('media-manager', [
            'medias' => $medias, 'alt' => 'Illustration de l\'opération ' . $operation->codeName, 'kind' => 'operation',
            'canEdit' => $canEdit, 'action' => $opUrl . '/medias',
        ]) ?>
    </div>

    <section class="panel hud" aria-labelledby="op-order">
        <h2 id="op-order" class="panel__title">Ordre de mission <small class="serial"><?= e($operation->serial) ?></small></h2>
        <dl class="meta">
            <dt>Début</dt>
            <dd><time datetime="<?= e(iso_date($operation->dateBeg)) ?>"><?= e(format_date($operation->dateBeg, true)) ?></time></dd>
            <dt>Fin</dt>
            <dd>
                <?php if ($operation->dateEnd === null): ?>
                    <span class="tag tag--muted">Non définie</span>
                <?php else: ?>
                    <time datetime="<?= e(iso_date($operation->dateEnd)) ?>"><?= e(format_date($operation->dateEnd, true)) ?></time>
                <?php endif; ?>
            </dd>
            <dt>Statut</dt>
            <dd><?= $this->partial('status', ['status' => (string) $operation->status]) ?></dd>
            <dt>Déclarant</dt>
            <dd><?= e($operation->declarant) ?></dd>
            <dt>Missions</dt>
            <dd><?php foreach ($missions as $mission): ?><span class="tag"><?= e($mission) ?></span> <?php endforeach; ?></dd>
            <dt>Lieux</dt>
            <dd><?php foreach ($locations as $location): ?><span class="tag"><?= e($location) ?></span> <?php endforeach; ?></dd>
        </dl>
        <?php if ($operation->brief !== null): ?>
            <h3 class="visually-hidden">Brief</h3>
            <p class="brief"><?= e($operation->brief) ?></p>
        <?php endif; ?>
    </section>
</div>

<div class="grid grid--2">
    <section class="panel hud hud--danger" aria-labelledby="op-threats">
        <h2 id="op-threats" class="panel__title">Menaces visées <small><?= count($threats) ?></small></h2>
        <?php if ($threats === []): ?>
            <p class="empty">Aucune menace identifiée.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($threats as $threat): ?>
                    <li>
                        <a class="list__main" href="/threats/<?= e($threat['id']) ?>"><?= e($threat['name']) ?></a>
                        <span class="serial"><?= e($threat['serial']) ?></span>
                        <?= $this->partial('tier', ['tier' => $threat['tier']]) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel hud" aria-labelledby="op-abilities">
        <h2 id="op-abilities" class="panel__title">Compétences requises <small><?= count($requiredAbilities) ?></small></h2>
        <?php if ($requiredAbilities === []): ?>
            <p class="empty">Aucune compétence requise.</p>
        <?php else: ?>
            <p><?php foreach ($requiredAbilities as $ability): ?><span class="tag tag--accent"><?= e($ability) ?></span> <?php endforeach; ?></p>
        <?php endif; ?>
    </section>
</div>

<section class="panel hud" aria-labelledby="op-teams">
    <h2 id="op-teams" class="panel__title">Équipes affectées <small><?= count($teams) ?></small></h2>
    <?php if ($teams === []): ?>
        <p class="empty">Aucune équipe affectée.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($teams as $team): ?>
                <li>
                    <a class="list__main" href="/teams/<?= e($team['id']) ?>"><?= e($team['name']) ?></a>
                    <?php if ($team['archived']): ?><span class="tag tag--muted">Archivée</span><?php endif; ?>
                    <?php if ($canEdit): ?>
                        <form class="inline-form" method="post" action="<?= e($opUrl) ?>/teams/<?= e($team['id']) ?>/remove"
                              data-confirm="Retirer l'équipe <?= e($team['name']) ?> ? Les participants seront conservés.">
                            <?= $this->csrfField() ?>
                            <button type="submit" class="btn btn--danger btn--small">Retirer<span class="visually-hidden"> l'équipe <?= e($team['name']) ?></span></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($canEdit && $assignableTeams !== []): ?>
        <form method="post" action="<?= e($opUrl) ?>/teams" class="inline-fields panel__footer">
            <?= $this->csrfField() ?>
            <?= $this->partial('field', [
                'name' => 'team', 'label' => 'Affecter une équipe', 'type' => 'select', 'required' => true, 'placeholder' => '— Choisir —',
                'options' => array_map(static fn ($t) => [$t->id, $t->name . ' (' . $t->memberCount . ' membre(s))'], $assignableTeams),
                'hint' => 'Ses membres actuels deviennent participants ; ceux déjà engagés ailleurs aux mêmes dates sont écartés.',
            ]) ?>
            <button type="submit" class="btn btn--primary">Affecter</button>
        </form>
    <?php endif; ?>
</section>

<section class="panel hud" aria-labelledby="op-participants">
    <h2 id="op-participants" class="panel__title">Participants <small><?= count($participants) ?></small></h2>

    <?php if ($underTiered !== [] && $strongestThreat !== null): ?>
        <div class="alert alert--warning" role="status">
            <span class="alert__label">Avertissement</span>
            <span class="alert__message">
                <?= count($underTiered) ?> participant(s) ont un tier inférieur à celui de la menace la plus dangereuse (tier <?= e($strongestThreat['tier']) ?>).
                L'affectation reste possible.
            </span>
        </div>
    <?php endif; ?>

    <?php if ($participants === []): ?>
        <p class="empty">Aucun participant pour l'instant.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <caption class="visually-hidden">Participants de l'opération <?= e($operation->codeName) ?>, avec leur équipe au début de l'opération</caption>
                <thead>
                <tr>
                    <th scope="col">Agent</th><th scope="col">Tier</th><th scope="col">Équipe à l'époque</th>
                    <?php if ($canEdit): ?><th scope="col" class="actions">Actions</th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($participants as $participant): ?>
                    <tr>
                        <th scope="row" data-label="Agent"><?= $this->partial('agent-name', ['id' => $participant['agent_id'], 'name' => $participant['name'], 'archived' => $participant['archived']]) ?></th>
                        <td data-label="Tier">
                            <span>
                                <?= $this->partial('tier', ['tier' => $participant['tier']]) ?>
                                <?php if (in_array($participant['agent_id'], $underTiered, true)): ?>
                                    <span class="warning-text">Tier inférieur à la menace</span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td data-label="Équipe à l'époque">
                            <?php if ($participant['team_id'] !== null): ?>
                                <a href="/teams/<?= e($participant['team_id']) ?>"><?= e($participant['team_name']) ?></a>
                            <?php else: ?>
                                <span class="tag tag--muted">Sans équipe</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($canEdit): ?>
                            <td class="actions" data-label="Actions">
                                <form class="inline-form" method="post" action="<?= e($opUrl) ?>/agents/<?= e($participant['agent_id']) ?>/remove"
                                      data-confirm="Retirer <?= e($participant['name']) ?> des participants ?">
                                    <?= $this->csrfField() ?>
                                    <button type="submit" class="btn btn--danger btn--small">Retirer<span class="visually-hidden"> <?= e($participant['name']) ?></span></button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($canEdit && $addableAgents !== []): ?>
        <form method="post" action="<?= e($opUrl) ?>/agents" class="inline-fields panel__footer">
            <?= $this->csrfField() ?>
            <?= $this->partial('field', [
                'name' => 'agent', 'label' => 'Ajouter un renfort', 'type' => 'select', 'required' => true, 'placeholder' => '— Choisir un agent —',
                'options' => array_map(static fn ($a) => [$a->id, $a->name . ' (tier ' . $a->tier . ')'], $addableAgents),
                'hint' => 'Sa disponibilité est vérifiée : un agent engagé sur une autre opération aux mêmes dates est refusé.',
            ]) ?>
            <button type="submit" class="btn btn--primary">Ajouter</button>
        </form>
    <?php endif; ?>
</section>

<section class="panel hud" aria-labelledby="op-staffing">
    <h2 id="op-staffing" class="panel__title">Suggestions de staffing <small>en service, disponibles, toutes compétences requises</small></h2>
    <?php if ($requiredAbilities === []): ?>
        <p class="empty">Définissez les compétences requises pour obtenir des suggestions.</p>
    <?php elseif ($suggestions === []): ?>
        <p class="empty">Aucun agent disponible ne possède toutes les compétences requises.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($suggestions as $candidate): ?>
                <li>
                    <span class="list__main">
                        <a href="/agents/<?= e($candidate['agent_id']) ?>"><?= e($candidate['name']) ?></a>
                        <?= $this->partial('tier', ['tier' => $candidate['tier']]) ?>
                        <span class="tag tag--muted"><?= e($candidate['team_name'] ?? 'Sans équipe') ?></span>
                        <?php if ($strongestThreat !== null && $candidate['tier_level'] < $strongestThreat['tier_level']): ?>
                            <span class="warning-text">Tier inférieur à la menace</span>
                        <?php endif; ?>
                    </span>
                    <span class="field__hint">Compétences : <?= e($candidate['abilities']) ?></span>
                    <?php if ($canEdit): ?>
                        <form class="inline-form" method="post" action="<?= e($opUrl) ?>/agents">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="agent" value="<?= e($candidate['agent_id']) ?>">
                            <button type="submit" class="btn btn--primary btn--small">Ajouter<span class="visually-hidden"> <?= e($candidate['name']) ?> aux participants</span></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
