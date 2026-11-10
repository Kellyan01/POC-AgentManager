<?php
/**
 * Nom d'un agent avec lien vers son dossier.
 * Agent archivé : nom barré, barre caviardée décorative et mention « Archivé » en toutes lettres.
 *
 * @var int    $id
 * @var string $name
 * @var bool   $archived
 */
$archived ??= false;
?>
<?php if ($archived): ?>
    <span class="redacted">
        <a class="redacted__name" href="/agents/<?= e($id) ?>"><?= e($name) ?></a>
        <span class="redacted__bar" aria-hidden="true"></span>
        <span class="tag tag--muted">Archivé</span>
    </span>
<?php else: ?>
    <a href="/agents/<?= e($id) ?>"><?= e($name) ?></a>
<?php endif; ?>
