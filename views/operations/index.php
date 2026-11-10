<?php
/**
 * @var App\Core\View                 $this
 * @var list<App\Model\Operation>     $operations
 * @var list<App\Model\ReferenceItem> $statuses
 * @var int|null                      $statusId
 */
?>
<header class="page-header">
    <div>
        <p class="kicker">Ordres de mission // Registre</p>
        <h1 class="page-title">Opérations</h1>
    </div>
    <div class="page-actions">
        <a class="btn btn--primary" href="/operations/new">Déclarer une opération</a>
    </div>
</header>

<form class="filters panel" method="get" action="/operations" data-autosubmit role="search" aria-label="Filtrer les opérations">
    <?= $this->partial('field', [
        'name' => 'status', 'label' => 'Statut', 'type' => 'select', 'value' => $statusId ?? '', 'placeholder' => 'Tous',
        'options' => array_map(static fn ($s) => [$s->id, $s->label], $statuses),
    ]) ?>
    <div class="filters__actions">
        <button type="submit" class="btn">Filtrer</button>
        <?php if ($statusId !== null): ?><a class="btn btn--ghost" href="/operations">Réinitialiser</a><?php endif; ?>
    </div>
</form>

<section class="panel hud" aria-label="Liste des opérations">
    <?php if ($operations === []): ?>
        <p class="empty">Aucune opération pour ce statut.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <caption><?= count($operations) ?> opération(s), de la plus récente à la plus ancienne</caption>
                <thead>
                <tr>
                    <th scope="col">Nom de code</th>
                    <th scope="col">Numéro</th>
                    <th scope="col">Début</th>
                    <th scope="col">Fin</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Déclarant</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($operations as $operation): ?>
                    <tr>
                        <th scope="row" data-label="Nom de code"><a href="/operations/<?= e($operation->id) ?>"><?= e($operation->codeName) ?></a></th>
                        <td data-label="Numéro" class="serial"><?= e($operation->serial) ?></td>
                        <td data-label="Début"><time datetime="<?= e(iso_date($operation->dateBeg)) ?>"><?= e(format_date($operation->dateBeg, true)) ?></time></td>
                        <td data-label="Fin">
                            <?php if ($operation->dateEnd === null): ?>
                                <span class="tag tag--muted">Non définie</span>
                            <?php else: ?>
                                <time datetime="<?= e(iso_date($operation->dateEnd)) ?>"><?= e(format_date($operation->dateEnd, true)) ?></time>
                            <?php endif; ?>
                        </td>
                        <td data-label="Statut"><?= $this->partial('status', ['status' => (string) $operation->status]) ?></td>
                        <td data-label="Déclarant"><?= e($operation->declarant) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
