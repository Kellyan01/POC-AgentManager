<?php
/**
 * Badge de tier. La lettre est le contenu, la couleur n'est qu'un renfort.
 *
 * @var string $tier
 */
?>
<span class="tier tier--<?= e(App\Model\Tier::cssModifier($tier)) ?>"><span class="visually-hidden">Tier </span><?= e($tier) ?></span>
