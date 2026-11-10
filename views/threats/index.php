<?php
/**
 * @var App\Core\View          $this
 * @var list<App\Model\Threat> $threats
 */
?>
<header class="page-header">
    <div>
        <p class="kicker">Renseignement // Cibles prioritaires</p>
        <h1 class="page-title">Menaces</h1>
    </div>
    <div class="page-actions">
        <a class="btn btn--primary" href="/threats/new">Nouvelle menace</a>
    </div>
</header>

<section class="panel hud hud--danger" aria-label="Liste des menaces">
    <?php if ($threats === []): ?>
        <p class="empty">Aucune menace répertoriée.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <caption><?= count($threats) ?> menace(s), de la plus dangereuse à la moins dangereuse</caption>
                <thead>
                <tr>
                    <th scope="col">Menace</th>
                    <th scope="col">Numéro</th>
                    <th scope="col">Tier</th>
                    <th scope="col">Opérations</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($threats as $threat): ?>
                    <tr>
                        <th scope="row" data-label="Menace">
                            <span class="agent-line">
                                <?= $this->partial('portrait', ['url' => $threat->portrait, 'alt' => 'Portrait de ' . $threat->name, 'kind' => 'threat', 'size' => 'small']) ?>
                                <a class="agent-line__name" href="/threats/<?= e($threat->id) ?>"><?= e($threat->name) ?></a>
                            </span>
                        </th>
                        <td data-label="Numéro" class="serial"><?= e($threat->serial) ?></td>
                        <td data-label="Tier"><?= $this->partial('tier', ['tier' => (string) $threat->tier]) ?></td>
                        <td data-label="Opérations" class="data"><?= e($threat->operationCount) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
