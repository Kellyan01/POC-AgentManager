<?php

declare(strict_types=1);

/*
 * Vérifications d'accessibilité structurelle sur des pages HTML enregistrées.
 * Usage : php tests/a11y_check.php page1.html page2.html ...
 * Ne remplace pas un audit manuel (contrastes, lecteur d'écran, clavier),
 * mais détecte les oublis courants.
 */

$titles = [];
$problems = 0;

foreach (array_slice($argv, 1) as $file) {
    $html = (string) file_get_contents($file);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $issues = [];

    if ($xpath->evaluate('string(/html/@lang)') !== 'fr') {
        $issues[] = 'attribut lang="fr" absent';
    }
    $h1 = $xpath->query('//h1')->length;
    if ($h1 !== 1) {
        $issues[] = "$h1 titre(s) h1 (1 attendu)";
    }
    $title = trim($xpath->evaluate('string(//title)'));
    if ($title === '') {
        $issues[] = 'balise <title> vide';
    } elseif (isset($titles[$title])) {
        $issues[] = "titre identique à {$titles[$title]} : « $title »";
    }
    $titles[$title] = basename($file);

    if ($xpath->query('//a[@class="skip-link"][@href="#main"]')->length !== 1 || $xpath->query('//*[@id="main"]')->length !== 1) {
        $issues[] = 'lien d\'évitement ou cible #main absent';
    }

    // Identifiants uniques
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $node) {
        $id = $node->getAttribute('id');
        if (isset($ids[$id])) {
            $issues[] = "id dupliqué : $id";
        }
        $ids[$id] = true;
    }

    // Chaque champ a un label
    foreach ($xpath->query('//input[not(@type="hidden") and not(@type="submit")] | //select | //textarea') as $field) {
        $id = $field->getAttribute('id');
        $labelled = ($id !== '' && $xpath->query("//label[@for='$id']")->length > 0)
            || $xpath->query('ancestor::label', $field)->length > 0;
        if (!$labelled) {
            $issues[] = 'champ sans label : ' . $field->getAttribute('name');
        }
    }

    // aria-describedby pointe vers des éléments existants
    foreach ($xpath->query('//*[@aria-describedby]') as $node) {
        foreach (preg_split('/\s+/', $node->getAttribute('aria-describedby')) as $ref) {
            if (!isset($ids[$ref])) {
                $issues[] = "aria-describedby vers un id inexistant : $ref";
            }
        }
    }

    // Images : alt obligatoire ; SVG porteurs de sens : role="img" + aria-label
    foreach ($xpath->query('//img[not(@alt)]') as $img) {
        $issues[] = 'image sans alt : ' . $img->getAttribute('src');
    }
    foreach ($xpath->query('//svg[not(@aria-hidden="true")][not(@role="img") or not(@aria-label)]') as $svg) {
        $issues[] = 'SVG sans aria-hidden ni role="img" + aria-label';
    }

    // Pas d'élément interactif dans un titre
    if ($xpath->query('//h1//button | //h2//button | //h3//button | //h2//a[contains(@class,"btn")]')->length > 0) {
        $issues[] = 'bouton à l\'intérieur d\'un titre';
    }

    // Tableaux : en-têtes avec scope
    foreach ($xpath->query('//table//th[not(@scope)]') as $th) {
        $issues[] = 'th sans scope : ' . trim($th->textContent);
    }

    printf("%-22s %s\n", basename($file), $issues === [] ? 'OK' : 'PROBLÈMES');
    foreach ($issues as $issue) {
        echo "    - $issue\n";
    }
    $problems += count($issues);
}

echo "\n" . ($problems === 0 ? 'Aucun problème détecté.' : "$problems problème(s) détecté(s).") . "\n";
exit($problems === 0 ? 0 : 1);
