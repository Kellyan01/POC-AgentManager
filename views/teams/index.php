<?php
/**
 * @var App\Core\View        $this
 * @var list<App\Model\Team> $teams
 * @var App\Model\User       $currentUser
 */
?>
<header class="page-header">
    <div>
        <p class="kicker">Unités opérationnelles // Registre</p>
        <h1 class="page-title">Équipes</h1>
    </div>
    <?php if ($currentUser->isAdmin()): ?>
        <div class="page-actions">
            <a class="btn btn--primary" href="/teams/new">Nouvelle équipe</a>
        </div>
    <?php endif; ?>
</header>

<section class="panel hud" aria-label="Liste des équipes">
    <?php if ($teams === []): ?>
        <p class="empty">Aucune équipe active.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table data-table--cards">
                <caption><?= count($teams) ?> équipe(s) active(s)</caption>
                <thead>
                <tr>
                    <th scope="col">Équipe</th>
                    <th scope="col">Manager</th>
                    <th scope="col">Membres</th>
                    <th scope="col">Créée le</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($teams as $team): ?>
                    <tr>
                        <th scope="row" data-label="Équipe">
                            <a href="/teams/<?= e($team->id) ?>"><?= e($team->name) ?></a>
                            <?php if ($team->managerId === $currentUser->id): ?><span class="tag tag--accent">Mon équipe</span><?php endif; ?>
                        </th>
                        <td data-label="Manager"><?= e($team->managerName) ?></td>
                        <td data-label="Membres" class="data"><?= e($team->memberCount) ?></td>
                        <td data-label="Créée le"><time datetime="<?= e(iso_date($team->createdAt)) ?>"><?= e(format_date($team->createdAt)) ?></time></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
