<?php
/**
 * Création et modification d'une menace.
 *
 * @var App\Core\View          $this
 * @var App\Model\Threat|null  $threat
 * @var array{name: string, tier: string, informations: string} $values
 * @var array<string, string>  $errors
 * @var list<App\Model\Tier>   $tiers
 * @var int                    $maxLength
 * @var string                 $title
 */
$action = $threat === null ? '/threats' : '/threats/' . $threat->id;
$cancel = $threat === null ? '/threats' : '/threats/' . $threat->id;
$labels = ['name' => 'Nom', 'tier' => 'Tier', 'informations' => 'Renseignements'];
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/threats">Menaces</a> // <?= $threat === null ? 'Nouvelle fiche' : e($threat->serial) ?></p>
        <h1 class="page-title"><?= e($title) ?></h1>
    </div>
</header>

<section class="panel hud hud--danger form" aria-label="Formulaire menace">
    <p class="required-note">Les champs marqués d'un astérisque (*) sont obligatoires.<?= $threat === null ? ' Le numéro de série (THR-NNNN) est attribué automatiquement.' : '' ?></p>
    <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => $labels]) ?>
    <form method="post" action="<?= e($action) ?>">
        <?= $this->csrfField() ?>
        <?= $this->partial('field', ['name' => 'name', 'label' => $labels['name'], 'required' => true, 'value' => $values['name'], 'errors' => $errors, 'attrs' => ['maxlength' => 50]]) ?>
        <?= $this->partial('field', [
            'name' => 'tier', 'label' => $labels['tier'], 'type' => 'select', 'required' => true, 'value' => $values['tier'], 'errors' => $errors,
            'placeholder' => '— Choisir —', 'options' => array_map(static fn ($t) => [$t->id, $t->tier . ' (niveau ' . $t->level . ')'], $tiers),
        ]) ?>
        <?= $this->partial('field', [
            'name' => 'informations', 'label' => $labels['informations'], 'type' => 'textarea', 'value' => $values['informations'], 'errors' => $errors,
            'hint' => 'Facultatif. ' . $maxLength . ' caractères maximum.', 'attrs' => ['maxlength' => $maxLength, 'rows' => 6],
        ]) ?>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= $threat === null ? 'Enregistrer la menace' : 'Enregistrer' ?></button>
            <a class="btn btn--ghost" href="<?= e($cancel) ?>">Annuler</a>
        </div>
    </form>
</section>
