<?php
/**
 * @var App\Core\View         $this
 * @var list<App\Model\User>  $users
 * @var App\Model\User        $currentUser
 */
?>
<header class="page-header">
    <div>
        <p class="kicker"><a href="/admin">Administration</a> // Personnel habilité</p>
        <h1 class="page-title">Utilisateurs</h1>
    </div>
    <div class="page-actions">
        <a class="btn btn--primary" href="/admin/users/new">Nouvel utilisateur</a>
    </div>
</header>

<section class="panel hud" aria-label="Liste des utilisateurs">
    <div class="table-wrap">
        <table class="data-table data-table--cards">
            <caption><?= count($users) ?> compte(s), archivés en fin de liste</caption>
            <thead>
            <tr>
                <th scope="col">Nom</th>
                <th scope="col">E-mail</th>
                <th scope="col">Rôle</th>
                <th scope="col">Créé le</th>
                <th scope="col">État</th>
                <th scope="col" class="actions">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr<?= $user->isArchived() ? ' class="row-archived"' : '' ?>>
                    <th scope="row" data-label="Nom"><?= e($user->fullName()) ?></th>
                    <td data-label="E-mail" class="data"><?= e($user->email) ?></td>
                    <td data-label="Rôle"><span class="tag<?= $user->isAdmin() ? ' tag--warn' : '' ?>"><?= e($user->role) ?></span></td>
                    <td data-label="Créé le"><time datetime="<?= e(iso_date($user->createdAt)) ?>"><?= e(format_date($user->createdAt)) ?></time></td>
                    <td data-label="État">
                        <?php if ($user->isArchived()): ?>
                            <span class="tag tag--muted">Archivé le <?= e(format_date($user->archivedAt)) ?></span>
                        <?php else: ?>
                            <span class="tag tag--accent">Actif</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions" data-label="Actions">
                        <?php if (!$user->isArchived()): ?>
                            <a class="btn btn--small" href="/admin/users/<?= e($user->id) ?>/edit">Modifier<span class="visually-hidden"> <?= e($user->fullName()) ?></span></a>
                            <?php if ($user->id !== $currentUser->id): ?>
                                <form class="inline-form" method="post" action="/admin/users/<?= e($user->id) ?>/archive" data-confirm="Archiver le compte de <?= e($user->fullName()) ?> ? Il ne pourra plus se connecter.">
                                    <?= $this->csrfField() ?>
                                    <button type="submit" class="btn btn--danger btn--small">Archiver<span class="visually-hidden"> <?= e($user->fullName()) ?></span></button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
