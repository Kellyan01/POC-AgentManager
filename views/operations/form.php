<?php
/**
 * Déclaration et modification d'une opération.
 *
 * @var App\Core\View                 $this
 * @var App\Model\Operation|null      $operation
 * @var array<string, mixed>          $values
 * @var array<string, string>         $errors
 * @var list<App\Model\ReferenceItem> $statuses
 * @var list<App\Model\ReferenceItem> $missions
 * @var list<App\Model\ReferenceItem> $locations
 * @var list<App\Model\Threat>        $threats
 * @var list<App\Model\ReferenceItem> $abilities
 * @var int                           $maxBrief
 * @var string                        $title
 */
$action = $operation === null ? '/operations' : '/operations/' . $operation->id;
$cancel = $operation === null ? '/operations' : '/operations/' . $operation->id;
$labels = [
    'code_name' => 'Nom de code', 'date_beg' => 'Début', 'date_end' => 'Fin', 'status' => 'Statut', 'brief' => 'Brief',
    'missions' => 'Types de mission', 'locations' => 'Lieux', 'threats' => 'Menaces visées', 'abilities' => 'Compétences requises',
];
$options = static fn (array $items): array => array_map(static fn ($item) => [$item->id, $item->label], $items);
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/operations">Opérations</a> // <?= $operation === null ? 'Nouvel ordre de mission' : e($operation->serial) ?></p>
        <h1 class="page-title"><?= e($title) ?></h1>
    </div>
</header>

<section class="panel hud form" aria-label="Formulaire opération">
    <p class="required-note">
        Les champs marqués d'un astérisque (*) sont obligatoires.
        <?= $operation === null ? 'Le numéro de série (OP-AAAA-NNN) est attribué automatiquement et vous deviendrez le déclarant de l\'opération.' : '' ?>
    </p>
    <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => $labels]) ?>
    <form method="post" action="<?= e($action) ?>">
        <?= $this->csrfField() ?>
        <div class="form-grid">
            <?= $this->partial('field', ['name' => 'code_name', 'label' => $labels['code_name'], 'required' => true, 'value' => $values['code_name'], 'errors' => $errors, 'attrs' => ['maxlength' => 50]]) ?>
            <?= $this->partial('field', [
                'name' => 'status', 'label' => $labels['status'], 'type' => 'select', 'required' => true, 'value' => $values['status'],
                'errors' => $errors, 'placeholder' => '— Choisir —', 'options' => $options($statuses),
            ]) ?>
        </div>
        <div class="form-grid">
            <?= $this->partial('field', ['name' => 'date_beg', 'label' => $labels['date_beg'], 'type' => 'datetime-local', 'required' => true, 'value' => $values['date_beg'], 'errors' => $errors]) ?>
            <?= $this->partial('field', [
                'name' => 'date_end', 'label' => $labels['date_end'], 'type' => 'datetime-local', 'value' => $values['date_end'], 'errors' => $errors,
                'hint' => 'Facultative (opération en cours, fin inconnue). Doit être postérieure au début.',
            ]) ?>
        </div>
        <?= $this->partial('field', [
            'name' => 'brief', 'label' => $labels['brief'], 'type' => 'textarea', 'value' => $values['brief'], 'errors' => $errors,
            'hint' => 'Objectif et consignes de l\'opération. ' . $maxBrief . ' caractères maximum.', 'attrs' => ['maxlength' => $maxBrief, 'rows' => 5],
        ]) ?>
        <?= $this->partial('checkboxes', ['name' => 'missions', 'legend' => $labels['missions'], 'required' => true, 'options' => $options($missions), 'selected' => $values['missions'], 'errors' => $errors]) ?>
        <?= $this->partial('checkboxes', ['name' => 'locations', 'legend' => $labels['locations'], 'required' => true, 'options' => $options($locations), 'selected' => $values['locations'], 'errors' => $errors]) ?>
        <?= $this->partial('checkboxes', [
            'name' => 'threats', 'legend' => $labels['threats'], 'selected' => $values['threats'], 'errors' => $errors,
            'options' => array_map(static fn ($t) => [$t->id, $t->name . ' (tier ' . $t->tier . ')'], $threats),
        ]) ?>
        <?= $this->partial('checkboxes', [
            'name' => 'abilities', 'legend' => $labels['abilities'], 'options' => $options($abilities), 'selected' => $values['abilities'], 'errors' => $errors,
            'hint' => 'Utilisées pour proposer des agents qualifiés (staffing).',
        ]) ?>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= $operation === null ? 'Déclarer l\'opération' : 'Enregistrer' ?></button>
            <a class="btn btn--ghost" href="<?= e($cancel) ?>">Annuler</a>
        </div>
    </form>
</section>
