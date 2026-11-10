<?php
/**
 * @var App\Core\View                      $this
 * @var App\Repository\ReferenceDefinition $definition
 * @var App\Model\ReferenceItem            $item
 * @var list<App\Model\ReferenceItem>      $abilities
 * @var array{label: string, abilities: list<int>} $values
 * @var array<string, string>              $errors
 */
$abilityOptions = array_map(static fn ($a) => [$a->id, $a->label], $abilities);
$locked = $definition->isLocked($item->label);
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/admin">Administration</a> // <a href="/admin/<?= e($definition->slug) ?>"><?= e($definition->title) ?></a></p>
        <h1 class="page-title">Modifier « <?= e($item->label) ?> »</h1>
    </div>
</header>

<section class="panel hud form" aria-label="Formulaire de modification">
    <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => ['label' => $definition->fieldLabel, 'abilities' => 'Compétences']]) ?>
    <form method="post" action="/admin/<?= e($definition->slug) ?>/<?= e($item->id) ?>">
        <?= $this->csrfField() ?>
        <?= $this->partial('field', [
            'name' => 'label', 'label' => $definition->fieldLabel, 'required' => true,
            'value' => $values['label'], 'errors' => $errors,
            'hint' => $locked ? 'Libellé verrouillé : il est utilisé par les règles de l\'application.' : null,
            'attrs' => ['maxlength' => $definition->maxLength] + ($locked ? ['readonly' => 'readonly'] : []),
        ]) ?>
        <?php if ($definition->hasAbilities()): ?>
            <?= $this->partial('checkboxes', [
                'name' => 'abilities', 'legend' => 'Compétences apportées', 'options' => $abilityOptions,
                'selected' => $values['abilities'], 'errors' => $errors,
            ]) ?>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Enregistrer</button>
            <a class="btn btn--ghost" href="/admin/<?= e($definition->slug) ?>">Annuler</a>
        </div>
    </form>
</section>
