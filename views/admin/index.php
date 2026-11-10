<?php
/**
 * @var App\Core\View                                    $this
 * @var array<string, App\Repository\ReferenceDefinition> $references
 */
?>
<header class="page-header">
    <div>
        <p class="kicker">Niveau d'habilitation maximal // Administration</p>
        <h1 class="page-title">Administration</h1>
    </div>
</header>

<div class="grid grid--2">
    <section class="panel hud" aria-labelledby="admin-users">
        <h2 id="admin-users" class="panel__title">Utilisateurs</h2>
        <p>Comptes Administrateur et Manager : création, rôle, mot de passe, archivage.</p>
        <a class="btn btn--primary" href="/admin/users">Gérer les utilisateurs</a>
    </section>

    <section class="panel hud" aria-labelledby="admin-references">
        <h2 id="admin-references" class="panel__title">Référentiels</h2>
        <ul class="list">
            <li><a class="list__main" href="/admin/tiers">Tiers</a></li>
            <?php foreach ($references as $slug => $definition): ?>
                <li>
                    <a class="list__main" href="/admin/<?= e($slug) ?>"><?= e($definition->title) ?></a>
                    <?php if ($definition->hasAbilities()): ?>
                        <span class="tag tag--muted">avec compétences</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
