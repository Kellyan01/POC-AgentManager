<?php
/**
 * @var App\Core\View                      $this
 * @var App\Repository\ReferenceDefinition $definition
 * @var list<App\Model\ReferenceItem>      $items
 * @var array<int, list<string>>           $abilityLabels
 * @var list<App\Model\ReferenceItem>      $abilities
 * @var array{label: string, abilities: list<int>} $values
 * @var array<string, string>              $errors
 */
$abilityOptions = array_map(static fn ($a) => [$a->id, $a->label], $abilities);
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/admin">Administration</a> // Référentiel</p>
        <h1 class="page-title"><?= e($definition->title) ?></h1>
    </div>
</header>

<div class="grid grid--2">
    <section class="panel hud" aria-labelledby="ref-list">
        <h2 id="ref-list" class="panel__title">Valeurs <small><?= count($items) ?> entrée(s)</small></h2>
        <?php if ($items === []): ?>
            <p class="empty">Aucune valeur pour l'instant.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table data-table--cards">
                    <caption class="visually-hidden"><?= e($definition->title) ?></caption>
                    <thead>
                    <tr>
                        <th scope="col"><?= e($definition->fieldLabel) ?></th>
                        <?php if ($definition->hasAbilities()): ?><th scope="col">Compétences</th><?php endif; ?>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td data-label="<?= e($definition->fieldLabel) ?>"><?= e($item->label) ?></td>
                            <?php if ($definition->hasAbilities()): ?>
                                <td data-label="Compétences"><?= e(implode(', ', $abilityLabels[$item->id] ?? []) ?: '—') ?></td>
                            <?php endif; ?>
                            <td class="actions" data-label="Actions">
                                <a class="btn btn--small" href="/admin/<?= e($definition->slug) ?>/<?= e($item->id) ?>/edit">Modifier<span class="visually-hidden"> « <?= e($item->label) ?> »</span></a>
                                <?php if ($definition->isLocked($item->label)): ?>
                                    <span class="tag tag--muted" title="Libellé utilisé par les règles de l'application">Verrouillé</span>
                                <?php else: ?>
                                    <form class="inline-form" method="post" action="/admin/<?= e($definition->slug) ?>/<?= e($item->id) ?>/delete" data-confirm="Supprimer « <?= e($item->label) ?> » ?">
                                        <?= $this->csrfField() ?>
                                        <button type="submit" class="btn btn--danger btn--small">Supprimer<span class="visually-hidden"> « <?= e($item->label) ?> »</span></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel hud" aria-labelledby="ref-new">
        <h2 id="ref-new" class="panel__title">Ajouter</h2>
        <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => ['label' => $definition->fieldLabel, 'abilities' => 'Compétences']]) ?>
        <form method="post" action="/admin/<?= e($definition->slug) ?>">
            <?= $this->csrfField() ?>
            <?= $this->partial('field', [
                'name' => 'label', 'label' => $definition->fieldLabel, 'required' => true,
                'value' => $values['label'], 'errors' => $errors, 'attrs' => ['maxlength' => $definition->maxLength],
            ]) ?>
            <?php if ($definition->hasAbilities()): ?>
                <?= $this->partial('checkboxes', [
                    'name' => 'abilities', 'legend' => 'Compétences apportées', 'options' => $abilityOptions,
                    'selected' => $values['abilities'], 'errors' => $errors,
                ]) ?>
            <?php endif; ?>
            <button type="submit" class="btn btn--primary">Ajouter</button>
        </form>
    </section>
</div>
