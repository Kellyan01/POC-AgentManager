<?php
/**
 * Champ de formulaire accessible : label associé, aide et erreur reliées par aria-describedby.
 *
 * @var string                     $name      attribut name
 * @var string                     $label
 * @var string                     $type      text|email|password|date|datetime-local|number|textarea|select
 * @var string|int|null            $value
 * @var array<string, string>      $errors    erreurs du formulaire (indexées par nom de champ)
 * @var bool                       $required
 * @var string|null                $hint      texte d'aide
 * @var list<array{0: string|int, 1: string}> $options  pour un select : [valeur, libellé]
 * @var string|null                $placeholder  option vide d'un select
 * @var array<string, string|int>  $attrs     attributs supplémentaires (maxlength, min...)
 */
$type        ??= 'text';
$value       ??= '';
$errors      ??= [];
$required    ??= false;
$hint        ??= null;
$options     ??= [];
$placeholder ??= null;
$attrs       ??= [];

$id = 'f-' . $name;
$error = $errors[$name] ?? null;

$describedBy = [];
if ($hint !== null) {
    $describedBy[] = $id . '-hint';
}
if ($error !== null) {
    $describedBy[] = $id . '-error';
}

$attributes = ['id' => $id, 'name' => $name] + $attrs;
if ($describedBy !== []) {
    $attributes['aria-describedby'] = implode(' ', $describedBy);
}
if ($error !== null) {
    $attributes['aria-invalid'] = 'true';
}

$renderAttributes = static function (array $attributes) use ($required): string {
    $html = '';
    foreach ($attributes as $attribute => $attributeValue) {
        $html .= ' ' . $attribute . '="' . e($attributeValue) . '"';
    }

    return $html . ($required ? ' required' : '');
};
?>
<div class="field<?= $error !== null ? ' field--invalid' : '' ?>">
    <label class="field__label" for="<?= e($id) ?>">
        <?= e($label) ?><?php if ($required): ?> <span class="field__required" aria-hidden="true">*</span><?php endif; ?>
    </label>

    <?php if ($hint !== null): ?>
        <p class="field__hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p>
    <?php endif; ?>

    <?php if ($type === 'textarea'): ?>
        <textarea class="field__control"<?= $renderAttributes($attributes) ?>><?= e($value) ?></textarea>
    <?php elseif ($type === 'select'): ?>
        <select class="field__control"<?= $renderAttributes($attributes) ?>>
            <?php if ($placeholder !== null): ?>
                <option value=""><?= e($placeholder) ?></option>
            <?php endif; ?>
            <?php foreach ($options as [$optionValue, $optionLabel]): ?>
                <option value="<?= e($optionValue) ?>"<?= (string) $optionValue === (string) $value ? ' selected' : '' ?>><?= e($optionLabel) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input class="field__control" type="<?= e($type) ?>" value="<?= e($value) ?>"<?= $renderAttributes($attributes) ?>>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="field__error" id="<?= e($id) ?>-error"><span class="field__error-label">Erreur :</span> <?= e($error) ?></p>
    <?php endif; ?>
</div>
