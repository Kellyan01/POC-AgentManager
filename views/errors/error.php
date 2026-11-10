<?php
/**
 * Page d'erreur générique (403, 404, 405, 409, 413, 500).
 *
 * @var string $title
 * @var int    $status
 * @var string $message pour une 500, contient la trace uniquement en mode debug
 */
$defaults = [
    403 => 'Votre niveau d\'habilitation ne permet pas cette action.',
    404 => 'Ce dossier est introuvable ou a été déplacé.',
    405 => 'Cette adresse n\'accepte pas ce type de requête.',
    500 => 'Un incident technique empêche d\'afficher cette page. Réessayez dans un instant.',
];
?>
<section class="panel hud error-page" aria-labelledby="error-title">
    <p class="kicker">Code <?= e($status) ?> // Transmission interrompue</p>
    <h1 id="error-title" class="page-title"><?= e($title) ?></h1>
    <?php if ($status === 500 && $message !== ''): ?>
        <p><?= e($defaults[500]) ?></p>
        <pre class="debug-trace"><?= e($message) ?></pre>
    <?php else: ?>
        <p><?= e($message !== '' ? $message : ($defaults[$status] ?? 'Une erreur est survenue.')) ?></p>
    <?php endif; ?>
    <p><a class="btn btn--primary" href="/">Retour au tableau de bord</a></p>
</section>
