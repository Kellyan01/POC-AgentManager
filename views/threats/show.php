<?php
/**
 * Fiche d'une menace.
 *
 * @var App\Core\View    $this
 * @var App\Model\Threat $threat
 * @var App\Model\User   $currentUser
 * @var list<array{id: int, serial: string, code_name: string, status: string, date_beg: DateTimeImmutable, date_end: ?DateTimeImmutable}> $operations
 * @var list<App\Model\Media> $medias
 */
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/threats">Menaces</a> // Fiche <?= e($threat->serial) ?> // Eyes only</p>
        <div class="dossier__header">
            <h1 class="page-title"><?= e($threat->name) ?></h1>
            <span class="stamp">Hostile</span>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn" href="/threats/<?= e($threat->id) ?>/edit">Modifier</a>
        <?php if ($currentUser->isAdmin() && $operations === []): ?>
            <form class="inline-form" method="post" action="/threats/<?= e($threat->id) ?>/delete"
                  data-confirm="Supprimer définitivement la fiche <?= e($threat->name) ?> et ses images ?">
                <?= $this->csrfField() ?>
                <button type="submit" class="btn btn--danger">Supprimer</button>
            </form>
        <?php endif; ?>
    </div>
</header>

<div class="dossier">
    <aside aria-label="Portrait">
        <?= $this->partial('portrait', ['url' => $threat->portrait, 'alt' => 'Portrait de ' . $threat->name, 'kind' => 'threat']) ?>
        <?= $this->partial('media-manager', [
            'medias' => $medias, 'alt' => 'Portrait de ' . $threat->name, 'kind' => 'threat',
            'canEdit' => true, 'action' => '/threats/' . $threat->id . '/medias',
        ]) ?>
    </aside>

    <div class="stack">
        <section class="panel hud hud--danger" aria-labelledby="threat-info">
            <h2 id="threat-info" class="panel__title">Renseignements <small class="serial"><?= e($threat->serial) ?></small></h2>
            <dl class="meta">
                <dt>Tier</dt>
                <dd><?= $this->partial('tier', ['tier' => (string) $threat->tier]) ?></dd>
                <dt>Fiche ouverte</dt>
                <dd><time datetime="<?= e(iso_date($threat->createdAt)) ?>"><?= e(format_date($threat->createdAt)) ?></time></dd>
            </dl>
            <?php if ($threat->informations !== null): ?>
                <p class="brief"><?= e($threat->informations) ?></p>
            <?php else: ?>
                <p class="empty">Aucun renseignement complémentaire.</p>
            <?php endif; ?>
        </section>

        <section class="panel hud" aria-labelledby="threat-operations">
            <h2 id="threat-operations" class="panel__title">Opérations liées <small><?= count($operations) ?></small></h2>
            <?php if ($operations === []): ?>
                <p class="empty">Aucune opération ne vise cette menace.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table data-table--cards">
                        <caption class="visually-hidden">Opérations visant <?= e($threat->name) ?></caption>
                        <thead>
                        <tr><th scope="col">Opération</th><th scope="col">Numéro</th><th scope="col">Début</th><th scope="col">Statut</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($operations as $operation): ?>
                            <tr>
                                <th scope="row" data-label="Opération"><a href="/operations/<?= e($operation['id']) ?>"><?= e($operation['code_name']) ?></a></th>
                                <td data-label="Numéro" class="serial"><?= e($operation['serial']) ?></td>
                                <td data-label="Début"><time datetime="<?= e(iso_date($operation['date_beg'])) ?>"><?= e(format_date($operation['date_beg'])) ?></time></td>
                                <td data-label="Statut"><?= $this->partial('status', ['status' => $operation['status']]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
