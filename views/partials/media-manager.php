<?php
/**
 * Galerie des images secondaires + suppression + formulaire d'upload.
 * L'image principale (la plus récente) est affichée par le partial « portrait ».
 *
 * @var App\Core\View          $this
 * @var list<App\Model\Media>  $medias  de la plus récente à la plus ancienne
 * @var string                 $alt     texte alternatif de base (« Portrait de Barry Allen »)
 * @var bool                   $canEdit
 * @var string                 $action  URL du formulaire d'upload
 * @var string                 $kind    agent|threat|operation
 */
$main = $medias[0] ?? null;
$others = array_slice($medias, 1);
$previewId = 'preview-' . $kind;
?>
<?php if ($canEdit && $main !== null): ?>
    <form class="upload-form" method="post" action="/medias/<?= e($main->id) ?>/delete" data-confirm="Supprimer l'image principale ? La précédente la remplacera.">
        <?= $this->csrfField() ?>
        <button type="submit" class="btn btn--danger btn--small btn--block">Supprimer l'image principale</button>
    </form>
<?php endif; ?>

<?php if ($others !== []): ?>
    <h3 class="kicker">Archives visuelles (<?= count($others) ?>)</h3>
    <ul class="gallery">
        <?php foreach ($others as $index => $media): ?>
            <li class="gallery__item">
                <?= $this->partial('portrait', ['url' => $media->url, 'alt' => $alt . ' (archive ' . ($index + 1) . ')', 'kind' => $kind]) ?>
                <?php if ($canEdit): ?>
                    <form method="post" action="/medias/<?= e($media->id) ?>/delete" data-confirm="Supprimer cette image ?">
                        <?= $this->csrfField() ?>
                        <button type="submit" class="btn btn--danger btn--small btn--block">Supprimer<span class="visually-hidden"> l'archive <?= e($index + 1) ?></span></button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($canEdit): ?>
    <form class="upload-form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
        <?= $this->csrfField() ?>
        <div class="field">
            <label class="field__label" for="f-image-<?= e($kind) ?>">Nouvelle image</label>
            <p class="field__hint" id="f-image-<?= e($kind) ?>-hint">JPEG, PNG ou WebP, 2 Mo maximum. Elle deviendra l'image principale.</p>
            <input class="field__control" type="file" id="f-image-<?= e($kind) ?>" name="image" required
                   accept="image/jpeg,image/png,image/webp" aria-describedby="f-image-<?= e($kind) ?>-hint"
                   data-preview="<?= e($previewId) ?>">
            <img id="<?= e($previewId) ?>" class="upload-preview" alt="Aperçu de l'image choisie" hidden>
        </div>
        <button type="submit" class="btn btn--primary btn--block">Envoyer l'image</button>
    </form>
<?php endif; ?>
