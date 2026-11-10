<?php
/**
 * Création et modification d'un agent.
 *
 * @var App\Core\View                 $this
 * @var App\Model\Agent|null          $agent
 * @var array{name: string, dob: string, archetype: string, tier: string} $values
 * @var array<string, string>         $errors
 * @var list<App\Model\ReferenceItem> $archetypes
 * @var list<App\Model\Tier>          $tiers
 * @var string                        $title
 */
$action = $agent === null ? '/agents' : '/agents/' . $agent->id;
$cancel = $agent === null ? '/agents' : '/agents/' . $agent->id;
$labels = ['name' => 'Nom', 'dob' => 'Date de naissance', 'archetype' => 'Archétype', 'tier' => 'Tier'];
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/agents">Agents</a> // <?= $agent === null ? 'Ouverture de dossier' : e($agent->fileNumber()) ?></p>
        <h1 class="page-title"><?= e($title) ?></h1>
    </div>
</header>

<section class="panel hud form" aria-label="Formulaire agent">
    <p class="required-note">Les champs marqués d'un astérisque (*) sont obligatoires.</p>
    <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => $labels]) ?>
    <form method="post" action="<?= e($action) ?>">
        <?= $this->csrfField() ?>
        <?= $this->partial('field', ['name' => 'name', 'label' => $labels['name'], 'required' => true, 'value' => $values['name'], 'errors' => $errors, 'attrs' => ['maxlength' => 50]]) ?>
        <?= $this->partial('field', [
            'name' => 'dob', 'label' => $labels['dob'], 'type' => 'date', 'value' => $values['dob'], 'errors' => $errors,
            'hint' => 'Facultative : certains agents sont trop anciens pour être datés.', 'attrs' => ['max' => date('Y-m-d')],
        ]) ?>
        <div class="form-grid">
            <?= $this->partial('field', [
                'name' => 'archetype', 'label' => $labels['archetype'], 'type' => 'select', 'required' => true, 'value' => $values['archetype'],
                'errors' => $errors, 'placeholder' => '— Choisir —', 'options' => array_map(static fn ($a) => [$a->id, $a->label], $archetypes),
                'hint' => 'L\'archétype apporte des compétences de base.',
            ]) ?>
            <?= $this->partial('field', [
                'name' => 'tier', 'label' => $labels['tier'], 'type' => 'select', 'required' => true, 'value' => $values['tier'],
                'errors' => $errors, 'placeholder' => '— Choisir —', 'options' => array_map(static fn ($t) => [$t->id, $t->tier . ' (niveau ' . $t->level . ')'], $tiers),
            ]) ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= $agent === null ? 'Ouvrir le dossier' : 'Enregistrer' ?></button>
            <a class="btn btn--ghost" href="<?= e($cancel) ?>">Annuler</a>
        </div>
    </form>
</section>
