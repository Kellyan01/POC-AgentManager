<?php
/**
 * Layout des pages publiques (connexion).
 *
 * @var App\Core\View $this
 * @var string        $content
 * @var string        $title
 */
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — Agent Manager</title>
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body class="auth-body">
<a class="skip-link" href="#main">Aller au contenu principal</a>
<div class="classification-bar"><span>TOP SECRET // EYES ONLY</span></div>
<main id="main" class="auth-page" tabindex="-1">
    <?= $this->partial('flash') ?>
    <?= $content ?>
</main>
</body>
</html>
