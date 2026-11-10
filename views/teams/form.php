<?php
/**
 * Création et modification d'une équipe (Administrateur).
 *
 * @var App\Core\View          $this
 * @var App\Model\Team|null    $team
 * @var array{name: string, manager: string} $values
 * @var array<string, string>  $errors
 * @var list<App\Model\User>   $managers utilisateurs actifs
 * @var string                 $title
 */
$action = $team === null ? '/teams' : '/teams/' . $team->id;
$cancel = $team === null ? '/teams' : '/teams/' . $team->id;
$labels = ['name' => 'Nom de l\'équipe', 'manager' => 'Manager'];
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/teams">Équipes</a> // <?= $team === null ? 'Création' : 'Modification' ?></p>
        <h1 class="page-title"><?= e($title) ?></h1>
    </div>
</header>

<section class="panel hud form" aria-label="Formulaire équipe">
    <p class="required-note">Les champs marqués d'un astérisque (*) sont obligatoires.</p>
    <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => $labels]) ?>
    <form method="post" action="<?= e($action) ?>">
        <?= $this->csrfField() ?>
        <?= $this->partial('field', ['name' => 'name', 'label' => $labels['name'], 'required' => true, 'value' => $values['name'], 'errors' => $errors, 'attrs' => ['maxlength' => 50]]) ?>
        <?= $this->partial('field', [
            'name' => 'manager', 'label' => $labels['manager'], 'type' => 'select', 'required' => true, 'value' => $values['manager'],
            'errors' => $errors, 'placeholder' => '— Choisir —',
            'options' => array_map(static fn ($u) => [$u->id, $u->fullName() . ' (' . $u->role . ')'], $managers),
            'hint' => 'Le manager gère les membres de l\'équipe.',
        ]) ?>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= $team === null ? 'Créer l\'équipe' : 'Enregistrer' ?></button>
            <a class="btn btn--ghost" href="<?= e($cancel) ?>">Annuler</a>
        </div>
    </form>
</section>
