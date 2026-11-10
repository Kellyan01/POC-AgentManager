/*
 * Agent Manager — JavaScript d'amélioration progressive.
 *
 * L'application fonctionne entièrement sans ce fichier : il n'ajoute que du confort.
 *  - confirmation avant une action irréversible (data-confirm sur le formulaire) ;
 *  - envoi automatique des filtres quand une liste déroulante change ;
 *  - aperçu de l'image choisie avant l'upload ;
 *  - focus placé sur le récapitulatif des erreurs d'un formulaire.
 */
(function () {
    'use strict';

    // Confirmation : <form data-confirm="Archiver cet agent ?">
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Filtres : <form data-autosubmit> ; le bouton « Filtrer » reste disponible sans JS
    document.querySelectorAll('form[data-autosubmit] select').forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    });

    // Aperçu : <input type="file" data-preview="id-de-l-image">
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        var preview = document.getElementById(input.getAttribute('data-preview'));
        if (!preview || !window.URL) {
            return;
        }
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (file && /^image\/(jpeg|png|webp)$/.test(file.type)) {
                preview.src = URL.createObjectURL(file);
                preview.hidden = false;
            } else {
                preview.hidden = true;
            }
        });
    });

    // Erreurs de formulaire : le lecteur d'écran annonce le récapitulatif
    var summary = document.querySelector('[data-error-summary]');
    if (summary) {
        summary.focus();
    }
})();
