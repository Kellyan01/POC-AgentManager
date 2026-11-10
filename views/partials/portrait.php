<?php
/**
 * Portrait d'un agent ou d'une menace, illustration d'une opération.
 * Sans image : visuel de remplacement en SVG (« NO VISUAL // CLASSIFIED »).
 *
 * @var string|null $url   chemin relatif de l'image (ex. uploads/agents/xxx.webp), null si aucune
 * @var string      $alt   texte alternatif (« Portrait de Barry Allen »)
 * @var string      $kind  agent|threat|operation
 * @var string      $size  large|small
 */
$size ??= 'large';
$kind ??= 'agent';
$wide = $kind === 'operation';
$classes = 'portrait' . ($wide ? ' portrait--wide' : '') . ($size === 'small' ? ' portrait--small' : '');
?>
<figure class="<?= e($classes) ?>">
    <?php if ($url !== null): ?>
        <img src="/<?= e($url) ?>" alt="<?= e($alt) ?>"<?= $size === 'small' ? ' width="56" height="75"' : '' ?> loading="lazy">
    <?php elseif ($wide): ?>
        <svg viewBox="0 0 160 90" role="img" aria-label="<?= e($alt) ?> : aucun visuel disponible" focusable="false">
            <rect width="160" height="90" fill="#0c1410"/>
            <path d="M0 22.5h160M0 45h160M0 67.5h160M40 0v90M80 0v90M120 0v90" stroke="#1f3428" stroke-width="0.6"/>
            <path d="M18 70 42 40l18 14 26-30 22 22 34-20" fill="none" stroke="#5e8c73" stroke-width="1.4"/>
            <circle cx="86" cy="24" r="9" fill="none" stroke="#ff7070" stroke-width="1.2"/>
            <path d="M86 10v8M86 30v8M72 24h8M92 24h8" stroke="#ff7070" stroke-width="1.2"/>
            <text x="80" y="84" text-anchor="middle" font-family="JetBrains Mono, Consolas, monospace" font-size="6" fill="#a3bcad" letter-spacing="0.6">NO VISUAL // CLASSIFIED</text>
        </svg>
    <?php else: ?>
        <svg viewBox="0 0 120 160" role="img" aria-label="<?= e($alt) ?> : aucun visuel disponible" focusable="false">
            <rect width="120" height="160" fill="#0c1410"/>
            <path d="M0 40h120M0 80h120M0 120h120M40 0v160M80 0v160" stroke="#1f3428" stroke-width="0.6"/>
            <circle cx="60" cy="58" r="21" fill="#111c16" stroke="#5e8c73" stroke-width="2"/>
            <path d="M20 134c3-27 19-42 40-42s37 15 40 42Z" fill="#111c16" stroke="#5e8c73" stroke-width="2"/>
            <circle cx="60" cy="58" r="32" fill="none" stroke="<?= $kind === 'threat' ? '#ff7070' : '#4ee38f' ?>" stroke-width="1" stroke-dasharray="4 3"/>
            <path d="M60 18v14M60 84v14M20 58h14M86 58h14" stroke="<?= $kind === 'threat' ? '#ff7070' : '#4ee38f' ?>" stroke-width="1.2"/>
            <?php if ($size !== 'small'): ?>
                <rect x="6" y="140" width="108" height="14" fill="#060a08"/>
                <text x="60" y="150" text-anchor="middle" font-family="JetBrains Mono, Consolas, monospace" font-size="7" fill="#a3bcad" letter-spacing="0.5">NO VISUAL // CLASSIFIED</text>
            <?php endif; ?>
        </svg>
    <?php endif; ?>
</figure>
