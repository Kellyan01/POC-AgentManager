<?php
/**
 * Layout principal des pages connectées.
 *
 * @var App\Core\View          $this
 * @var string                 $content     HTML de la page
 * @var string                 $title       titre unique de la page
 * @var App\Model\User|null    $currentUser
 */
$navigation = [
    '/'           => 'Tableau de bord',
    '/agents'     => 'Agents',
    '/teams'      => 'Équipes',
    '/operations' => 'Opérations',
    '/threats'    => 'Menaces',
];
if ($currentUser?->isAdmin()) {
    $navigation['/admin'] = 'Administration';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — Agent Manager</title>
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preload" href="/assets/fonts/rajdhani-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Aller au contenu principal</a>

<header class="site-header">
    <div class="classification-bar">
        <span>TOP SECRET // EYES ONLY</span>
        <?php if ($currentUser !== null): ?>
            <span class="classification-bar__clearance">Habilitation : <?= e($currentUser->role) ?></span>
        <?php endif; ?>
    </div>

    <div class="site-header__main">
        <a class="brand" href="/">
            <svg class="brand__mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                <path d="M16 2 29 9v14L16 30 3 23V9Z" fill="none" stroke="currentColor" stroke-width="2"/>
                <path d="M16 9v14M9 16h14" stroke="currentColor" stroke-width="2"/>
                <circle cx="16" cy="16" r="4" fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span class="brand__name">Agent<span class="brand__sep" aria-hidden="true">//</span>Manager</span>
        </a>

        <?php if ($currentUser !== null): ?>
            <nav class="main-nav" aria-label="Navigation principale">
                <ul>
                    <?php foreach ($navigation as $href => $label): ?>
                        <li>
                            <a href="<?= e($href) ?>"<?= $this->isCurrent($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="user-box">
                <span class="user-box__name"><?= e($currentUser->fullName()) ?></span>
                <form method="post" action="/logout">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="btn btn--ghost btn--small">Déconnexion</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</header>

<main id="main" class="page" tabindex="-1">
    <?= $this->partial('flash') ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <p>Agent Manager — gestion des ressources opérationnelles. Usage strictement interne.</p>
</footer>
</body>
</html>
