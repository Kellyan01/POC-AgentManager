<?php
/**
 * Création et modification d'une session de formation.
 *
 * @var App\Core\View                 $this
 * @var App\Model\Agent               $agent
 * @var App\Model\Training|null       $training
 * @var array{formation: string, date_beg: string, date_end: string, result: string} $values
 * @var array<string, string>         $errors
 * @var list<App\Model\ReferenceItem> $formations
 * @var App\Model\User                $currentUser
 */
$action = $training === null ? '/agents/' . $agent->id . '/trainings' : '/trainings/' . $training->id;
$labels = ['formation' => 'Formation', 'date_beg' => 'Début', 'date_end' => 'Fin', 'result' => 'Résultat'];
$results = ['pending' => 'En cours', 'passed' => 'Réussie', 'failed' => 'Échouée'];
$resultError = $errors['result'] ?? null;
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/agents/<?= e($agent->id) ?>"><?= e($agent->name) ?></a> // Formation</p>
        <h1 class="page-title"><?= $training === null ? 'Nouvelle session' : 'Modifier la session' ?></h1>
    </div>
</header>

<section class="panel hud form" aria-label="Formulaire de session de formation">
    <p class="required-note">Les champs marqués d'un astérisque (*) sont obligatoires. Un agent peut repasser une formation : chaque tentative est une session distincte.</p>
    <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => $labels]) ?>
    <form method="post" action="<?= e($action) ?>">
        <?= $this->csrfField() ?>
        <?= $this->partial('field', [
            'name' => 'formation', 'label' => $labels['formation'], 'type' => 'select', 'required' => true, 'value' => $values['formation'],
            'errors' => $errors, 'placeholder' => '— Choisir —', 'options' => array_map(static fn ($f) => [$f->id, $f->label], $formations),
        ]) ?>
        <div class="form-grid">
            <?= $this->partial('field', ['name' => 'date_beg', 'label' => $labels['date_beg'], 'type' => 'datetime-local', 'required' => true, 'value' => $values['date_beg'], 'errors' => $errors]) ?>
            <?= $this->partial('field', [
                'name' => 'date_end', 'label' => $labels['date_end'], 'type' => 'datetime-local', 'value' => $values['date_end'], 'errors' => $errors,
                'hint' => 'Laisser vide tant que la formation n\'est pas terminée.',
            ]) ?>
        </div>

        <fieldset class="fieldset<?= $resultError !== null ? ' fieldset--invalid' : '' ?>" id="f-result"<?= $resultError !== null ? ' aria-describedby="f-result-error"' : '' ?>>
            <legend class="fieldset__legend"><?= e($labels['result']) ?></legend>
            <?php if ($resultError !== null): ?>
                <p class="field__error" id="f-result-error"><span class="field__error-label">Erreur :</span> <?= e($resultError) ?></p>
            <?php endif; ?>
            <ul class="checkbox-list">
                <?php foreach ($results as $value => $label): ?>
                    <li>
                        <label class="checkbox radio">
                            <input type="radio" name="result" value="<?= e($value) ?>"<?= $values['result'] === $value ? ' checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </fieldset>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Enregistrer</button>
            <a class="btn btn--ghost" href="/agents/<?= e($agent->id) ?>">Annuler</a>
        </div>
    </form>

    <?php if ($training !== null && $currentUser->isAdmin()): ?>
        <form class="panel__footer" method="post" action="/trainings/<?= e($training->id) ?>/delete" data-confirm="Supprimer définitivement cette session de formation ?">
            <?= $this->csrfField() ?>
            <button type="submit" class="btn btn--danger btn--small">Supprimer cette session (saisie erronée)</button>
        </form>
    <?php endif; ?>
</section>
