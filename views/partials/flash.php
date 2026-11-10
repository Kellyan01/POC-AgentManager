<?php
/**
 * Messages flash (succès, avertissement, erreur) posés avant une redirection.
 * Le type est toujours écrit en toutes lettres : l'information ne repose pas sur la couleur.
 *
 * @var App\Core\View $this
 */
$labels = ['success' => 'Confirmé', 'warning' => 'Attention', 'error' => 'Erreur'];
$flashes = $this->flashes();
?>
<?php if ($flashes !== []): ?>
    <div class="flashes">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert--<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
                <span class="alert__label"><?= e($labels[$flash['type']] ?? 'Information') ?></span>
                <span class="alert__message"><?= e($flash['message']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
