<?php
/**
 * @var App\Core\View          $this
 * @var App\Model\Tier         $tier
 * @var array{tier: string, level: string} $values
 * @var array<string, string>  $errors
 */
use App\Model\Tier;

$locked = $tier->tier === Tier::GOD;
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/admin">Administration</a> // <a href="/admin/tiers">Tiers</a></p>
        <h1 class="page-title">Modifier le tier « <?= e($tier->tier) ?> »</h1>
    </div>
</header>

<section class="panel hud form" aria-label="Formulaire de modification">
    <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => ['tier' => 'Tier', 'level' => 'Niveau']]) ?>
    <form method="post" action="/admin/tiers/<?= e($tier->id) ?>">
        <?= $this->csrfField() ?>
        <?= $this->partial('field', [
            'name' => 'tier', 'label' => 'Tier', 'required' => true, 'value' => $values['tier'], 'errors' => $errors,
            'hint' => $locked ? 'Libellé verrouillé : il est utilisé par le tableau de bord.' : null,
            'attrs' => ['maxlength' => 10] + ($locked ? ['readonly' => 'readonly'] : []),
        ]) ?>
        <?= $this->partial('field', [
            'name' => 'level', 'label' => 'Niveau', 'type' => 'number', 'required' => true, 'value' => $values['level'],
            'errors' => $errors, 'attrs' => ['min' => 1, 'max' => 100],
        ]) ?>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Enregistrer</button>
            <a class="btn btn--ghost" href="/admin/tiers">Annuler</a>
        </div>
    </form>
</section>
