<?php
/**
 * Groupe de cases à cocher dans un fieldset (missions, lieux, compétences...).
 *
 * @var string                            $name     nom du champ (envoyé sous la forme name[])
 * @var string                            $legend
 * @var list<array{0: int, 1: string}>    $options  [valeur, libellé]
 * @var list<int>                         $selected valeurs cochées
 * @var array<string, string>             $errors
 * @var bool                              $required au moins une case doit être cochée (contrôle serveur)
 * @var string|null                       $hint
 */
$selected ??= [];
$errors   ??= [];
$required ??= false;
$hint     ??= null;

$id = 'f-' . $name;
$error = $errors[$name] ?? null;
$describedBy = array_filter([$hint !== null ? $id . '-hint' : null, $error !== null ? $id . '-error' : null]);
?>
<fieldset class="fieldset<?= $error !== null ? ' fieldset--invalid' : '' ?>" id="<?= e($id) ?>"<?= $describedBy !== [] ? ' aria-describedby="' . e(implode(' ', $describedBy)) . '"' : '' ?>>
    <legend class="fieldset__legend">
        <?= e($legend) ?><?php if ($required): ?> <span class="field__required" aria-hidden="true">*</span><span class="visually-hidden">(au moins un choix obligatoire)</span><?php endif; ?>
    </legend>
    <?php if ($hint !== null): ?>
        <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <p class="field__error" id="<?= e($id) ?>-error"><span class="field__error-label">Erreur :</span> <?= e($error) ?></p>
    <?php endif; ?>
    <?php if ($options === []): ?>
        <p class="empty">Aucune valeur disponible.</p>
    <?php else: ?>
        <ul class="checkbox-list">
            <?php foreach ($options as [$value, $label]): ?>
                <li>
                    <label class="checkbox">
                        <input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($value) ?>"<?= in_array((int) $value, $selected, true) ? ' checked' : '' ?>>
                        <span><?= e($label) ?></span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</fieldset>
