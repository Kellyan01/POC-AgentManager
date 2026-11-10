<?php
/**
 * @var App\Core\View          $this
 * @var list<App\Model\Tier>   $tiers
 * @var array{tier: string, level: string} $values
 * @var array<string, string>  $errors
 */
use App\Model\Tier;

?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/admin">Administration</a> // Référentiel</p>
        <h1 class="page-title">Tiers</h1>
    </div>
</header>

<div class="grid grid--2">
    <section class="panel hud" aria-labelledby="tier-list">
        <h2 id="tier-list" class="panel__title">Échelle de puissance <small>du plus fort au plus faible</small></h2>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <caption class="visually-hidden">Tiers, du plus fort au plus faible</caption>
                <thead>
                <tr><th scope="col">Tier</th><th scope="col">Niveau</th><th scope="col" class="actions">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($tiers as $tier): ?>
                    <tr>
                        <td data-label="Tier"><span class="tier tier--<?= e(Tier::cssModifier($tier->tier)) ?>"><?= e($tier->tier) ?></span></td>
                        <td data-label="Niveau" class="data"><?= e($tier->level) ?></td>
                        <td class="actions" data-label="Actions">
                            <a class="btn btn--small" href="/admin/tiers/<?= e($tier->id) ?>/edit">Modifier<span class="visually-hidden"> le tier <?= e($tier->tier) ?></span></a>
                            <?php if ($tier->tier === Tier::GOD): ?>
                                <span class="tag tag--muted">Verrouillé</span>
                            <?php else: ?>
                                <form class="inline-form" method="post" action="/admin/tiers/<?= e($tier->id) ?>/delete" data-confirm="Supprimer le tier <?= e($tier->tier) ?> ?">
                                    <?= $this->csrfField() ?>
                                    <button type="submit" class="btn btn--danger btn--small">Supprimer<span class="visually-hidden"> le tier <?= e($tier->tier) ?></span></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel hud" aria-labelledby="tier-new">
        <h2 id="tier-new" class="panel__title">Ajouter un tier</h2>
        <?= $this->partial('form-errors', ['errors' => $errors, 'labels' => ['tier' => 'Tier', 'level' => 'Niveau']]) ?>
        <form method="post" action="/admin/tiers">
            <?= $this->csrfField() ?>
            <?= $this->partial('field', ['name' => 'tier', 'label' => 'Tier', 'required' => true, 'value' => $values['tier'], 'errors' => $errors, 'attrs' => ['maxlength' => 10]]) ?>
            <?= $this->partial('field', [
                'name' => 'level', 'label' => 'Niveau', 'type' => 'number', 'required' => true, 'value' => $values['level'], 'errors' => $errors,
                'hint' => 'Entier unique : plus il est élevé, plus le tier est puissant.', 'attrs' => ['min' => 1, 'max' => 100],
            ]) ?>
            <button type="submit" class="btn btn--primary">Ajouter</button>
        </form>
    </section>
</div>
