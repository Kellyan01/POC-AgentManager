<?php
/**
 * Voyant de statut d'une opération : pastille + libellé (jamais la couleur seule).
 *
 * @var string $status
 */
?>
<span class="status status--<?= e(App\Model\Status::cssModifier($status)) ?>"><?= e($status) ?></span>
