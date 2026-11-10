<?php
/**
 * Récapitulatif des erreurs en tête de formulaire, avec un lien vers chaque champ.
 *
 * @var array<string, string> $errors
 * @var array<string, string> $labels libellé de chaque champ, pour le récapitulatif
 */
$labels ??= [];
?>
<?php if ($errors !== []): ?>
    <div class="error-summary" role="alert" tabindex="-1" data-error-summary>
        <h2 class="error-summary__title">
            <?= count($errors) === 1 ? 'Le formulaire contient une erreur' : 'Le formulaire contient ' . count($errors) . ' erreurs' ?>
        </h2>
        <ul>
            <?php foreach ($errors as $field => $message): ?>
                <li><a href="#f-<?= e($field) ?>"><?= e(($labels[$field] ?? $field) . ' : ' . $message) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
