<?php
/**
 * Compétences d'un agent et gestion des compétences d'expérience.
 *
 * @var App\Core\View                 $this
 * @var App\Model\Agent               $agent
 * @var list<array{id: int, ability: string, sources: list<string>}> $abilities
 * @var list<App\Model\ReferenceItem> $assignable compétences qu'il ne possède par aucune source
 */
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/agents/<?= e($agent->id) ?>"><?= e($agent->name) ?></a> // Dossier <?= e($agent->fileNumber()) ?></p>
        <h1 class="page-title">Compétences</h1>
    </div>
</header>

<div class="grid grid--2">
    <section class="panel hud" aria-labelledby="abilities-list">
        <h2 id="abilities-list" class="panel__title">Compétences acquises <small>avec leur provenance</small></h2>
        <?php if ($abilities === []): ?>
            <p class="empty">Aucune compétence.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($abilities as $ability): ?>
                    <li>
                        <span class="list__main"><?= e($ability['ability']) ?></span>
                        <?= $this->partial('ability-sources', ['sources' => $ability['sources']]) ?>
                        <?php if (in_array('Expérience', $ability['sources'], true) && !$agent->isArchived()): ?>
                            <form class="inline-form" method="post" action="/agents/<?= e($agent->id) ?>/abilities/<?= e($ability['id']) ?>/delete"
                                  data-confirm="Retirer la compétence d'expérience « <?= e($ability['ability']) ?> » ?">
                                <?= $this->csrfField() ?>
                                <button type="submit" class="btn btn--danger btn--small">Retirer<span class="visually-hidden"> la compétence d'expérience <?= e($ability['ability']) ?></span></button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="field__hint">Les compétences d'archétype et de formation se gèrent dans les référentiels et l'historique de formation ; seules les compétences d'expérience sont modifiables ici.</p>
    </section>

    <?php if (!$agent->isArchived()): ?>
        <section class="panel hud" aria-labelledby="abilities-add">
            <h2 id="abilities-add" class="panel__title">Ajouter une compétence d'expérience</h2>
            <?php if ($assignable === []): ?>
                <p class="empty">L'agent possède déjà toutes les compétences du référentiel.</p>
            <?php else: ?>
                <form method="post" action="/agents/<?= e($agent->id) ?>/abilities" class="inline-fields">
                    <?= $this->csrfField() ?>
                    <?= $this->partial('field', [
                        'name' => 'ability', 'label' => 'Compétence', 'type' => 'select', 'required' => true, 'placeholder' => '— Choisir —',
                        'options' => array_map(static fn ($a) => [$a->id, $a->label], $assignable),
                        'hint' => 'Seules les compétences que l\'agent ne possède pas déjà (archétype, formation réussie) sont proposées.',
                    ]) ?>
                    <button type="submit" class="btn btn--primary">Ajouter</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
