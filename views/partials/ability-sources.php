<?php
/**
 * Provenance(s) d'une compétence, en toutes lettres (pas seulement par la couleur).
 *
 * @var list<string> $sources Archétype | Formation | Expérience
 */
$modifiers = ['Archétype' => 'tag--muted', 'Formation' => 'tag--accent', 'Expérience' => 'tag--warn'];
?>
<?php foreach ($sources as $source): ?>
    <span class="tag <?= e($modifiers[$source] ?? '') ?>"><?= e($source) ?></span>
<?php endforeach; ?>
