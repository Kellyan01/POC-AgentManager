# Agent Manager — Avancement

Suivi étape par étape du plan (`PLAN.md`). Chaque étape se termine par `php -l` (PHP 8.3), des tests `curl` et un commit.

## Environnement de test

- PHP **8.3.30** (`C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64`), lancé avec un `php.ini` minimal hors projet (extensions `pdo_mysql`, `fileinfo`, `mbstring`).
- MySQL 8.4.7 (Laragon), base `agent_manager_v3` ré-importée depuis le script fourni avant chaque campagne de tests.
- Tests : `tests/lib.sh` (fonctions curl : connexion, jeton CSRF, vérifications).

## Étapes

| # | Étape | État |
|---|---|---|
| 1-2 | Socle + authentification et rôles | ✅ |
| 3 | Référentiels et utilisateurs | ✅ |
| 4 | Agents | ✅ |
| 5 | Équipes et appartenances | ✅ |
| 6 | Formations et compétences | ✅ |
| 7 | Menaces | ✅ |
| 8 | Opérations et staffing | ✅ |
| 9 | Médias | ✅ |
| 10 | Tableau de bord | ✅ |
| 11 | Finitions graphiques et accessibilité | à faire |

## Journal

### Étapes 1 et 2 — Socle et authentification

**Fait**
- Dépôt Git, `.gitignore` (config locale, `vendor/`, contenu de `public/uploads/`), `.gitattributes` (LF).
- Composer : autoload PSR-4 `App\` → `src/` + `src/helpers.php`, aucune dépendance.
- Cœur MVC : `Router`/`Route` (motifs `{id}` numériques, 404/405), `Request`, `Response` (en-têtes de sécurité, CSP stricte sans inline), `Session` (cookie HttpOnly/SameSite, mode strict, flash), `Csrf` (vérifié **dans `App` pour toute requête POST**), `View` (layouts, partials), `Validator`, `Database` (fabrique PDO, pas de Singleton), `App` (contrôle d'accès déclaré par route, pages d'erreur).
- Racine de composition `src/bootstrap.php` : PDO créé une fois puis injecté.
- Couche repository : `RepositoryInterface`, `DeletableRepositoryInterface`, `ArchivableRepositoryInterface`, `AbstractRepository`.
- Authentification : `password_verify`, `password_needs_rehash`, `session_regenerate_id(true)`, nouveau jeton CSRF à la connexion, déconnexion en POST qui détruit la session, compte archivé refusé à la connexion **et** déconnecté à la requête suivante.
- Layouts, formulaire de connexion accessible (erreurs reliées par `aria-describedby`, récapitulatif), page d'erreur, feuille de style complète (tokens, HUD, formulaires, tableaux, badges, tampons, portraits, responsive, `prefers-reduced-motion`), polices hébergées localement.

**Tests curl (20/20)** : redirection vers `/login`, 404, 405 sur `GET /logout`, POST sans jeton refusé (403), mauvais mot de passe (422, e-mail conservé), erreur reliée au champ, connexion admin et manager, menu Administration réservé à l'admin, déconnexion.

**Écarts au plan**
- Étapes 1 et 2 livrées ensemble : le contrôle d'accès centralisé dans `App` a besoin de `Auth` dès le socle.
- Jeton CSRF invalide : réponse **403** (code standard) au lieu du 419 prévu (code non standard, propre à Laravel).
- Une seule vue `errors/error.php` paramétrée par le code, au lieu d'un fichier par code.

### Étape 3 — Référentiels et utilisateurs

**Fait**
- `ReferenceDefinition` (catalogue de 6 référentiels à libellé unique) + `ReferenceRepository` générique (une instance par référentiel, créée dans le bootstrap) + `ReferenceController` unique. Archétypes et formations : cases à cocher des compétences, enregistrées avec la valeur dans une transaction (`ReferenceRepository::save()`).
- `TierRepository` / `TierController` (libellé + niveau, unicité des deux).
- Suppression d'une valeur utilisée : l'erreur MySQL 1451 est traduite en message clair (`MysqlError`).
- Libellés verrouillés (décision Q9) : `Planifiée`, `En cours`, `Annulée`, `God` — ni renommage ni suppression.
- Utilisateurs : création, modification (mot de passe facultatif, confirmation), archivage. Garde-fous : pas d'auto-archivage, pas de changement de son propre rôle, pas d'archivage d'un manager d'équipes actives, compte archivé non modifiable.

**Tests curl (47/47)** : 403 pour un Manager sur `/admin` (GET et POST), CRUD des référentiels, doublon (422), suppression refusée d'une valeur utilisée (Gotham City), verrous des statuts et du tier God, archétype avec compétences, création d'utilisateur puis connexion, e-mail déjà pris (valeurs conservées), archivage refusé (soi-même, manager d'équipes), compte archivé refusé à la connexion et **déconnecté en cours de session**.

**Écarts au plan**
- Archétypes et formations passent par le contrôleur générique (option « compétences » de la définition) au lieu de contrôleurs dédiés : moins de code dupliqué.
- Formulaire d'ajout sur la page de liste du référentiel (pas de page `/new` séparée).

### Étape 4 — Agents

**Fait**
- Entités `Agent`, `Team`, `Membership` ; `AgentRepository` (équipe actuelle via la vue `current_memberships`, portrait principal = requête 8, filtres équipe / « sans équipe » / tier / archétype / compétence via la vue `agent_abilities`), `TeamRepository`, `MembershipRepository` (requête 2 pour l'historique).
- `AgentService::archive()` : clôture de l'appartenance + archivage dans une transaction (Q3), avertissement listant les opérations planifiées ou en cours où l'agent figure encore.
- Liste filtrable (envoi automatique en JS, bouton « Filtrer » sans JS), dossier (tampon CLASSIFIED / ARCHIVED, identification, historique des équipes), formulaire avec validation serveur.
- Partials `portrait` (SVG de remplacement « NO VISUAL // CLASSIFIED », variantes agent / menace / opération), `agent-name` (caviardage des archivés, mention « Archivé » en texte), `tier`.

**Tests curl (41/41)** : liste sans les archivés, chaque filtre et leur combinaison, dossier de Barry (historique Avengers → Justice League), dossier archivé consultable mais non modifiable, 404, création et modification par un Manager, validation (422, valeurs conservées, `aria-invalid`), archivage refusé au Manager (403), archivage de Peter par l'Admin avec avertissement « Crépuscule » et clôture de son appartenance.

### Étape 5 — Équipes et appartenances

**Fait**
- `MembershipService` : `addMember()` (clôture puis ouverture dans une transaction, erreur 1062 sur `active_agent` traduite en message), `removeMember()`, `archiveTeam()` (clôture de toutes les appartenances + archivage dans une transaction).
- Décision Q2 appliquée : le droit sur l'équipe de destination est vérifié par le contrôleur, le droit sur l'équipe d'origine par le service. Un Manager ne voit dans la liste des candidats que les agents sans équipe ou membres de ses autres équipes.
- La règle « peut gérer cette équipe / modifier cette opération » est portée par l'entité `User` (`canManageTeam()`, `canEditOperation()`), utilisée à la fois par `Auth` et par les services.
- Écrans : liste des équipes (« Mon équipe »), fiche (membres actuels et anciens, intégration, libération, archivage), formulaire Admin (nom, manager). Panneau « Affectation » sur le dossier d'un agent (transfert ou libération).

**Tests curl (51/51)** — parcours **« déplacer Barry Allen »** : Hill ne peut pas le prendre à Coulson (message « son manager doit d'abord le libérer »), Coulson ne peut pas l'envoyer chez les Avengers (403), Coulson le libère, Hill l'intègre puis le transfère entre ses deux équipes, l'Admin le transfère directement ; une seule appartenance en cours en base, clôture et ouverture au même instant. Parcours **« archiver une équipe »** : Dark Squad archivée par l'Admin, 2 appartenances closes (vérifié en base), équipe absente de la liste, membres sans équipe, ajout de membre refusé, non modifiable. Nom d'une équipe archivée non réutilisable.

**Écart au plan (étape 5)** : route supplémentaire `POST /agents/{id}/team` utilisée par le panneau « Affectation » (la destination est choisie dans une liste, l'URL du formulaire ne peut pas en dépendre sans JavaScript).

### Étape 6 — Formations et compétences

**Fait**
- `Training` (résultat NULL / 1 / 0 → « En cours » / « Réussie » / « Échouée », comme la requête 5), `TrainingRepository` (historique d'un agent, dernières sessions pour le tableau de bord).
- `TrainingController` : nouvelle session, modification, suppression (Admin). Validation : fin postérieure au début, résultat seulement avec une date de fin passée (décision Q14). Chaque tentative est une ligne distincte.
- `AgentAbilityRepository` : compétences lues **via la vue `agent_abilities`** avec leurs provenances regroupées (requête 4) ; compétences attribuables = celles que l'agent ne possède par **aucune** source.
- `AgentAbilityController` : la liste proposée est filtrée **et** l'ajout est revérifié côté serveur ; seules les compétences d'expérience sont retirables.
- Dossier agent : sections « Formations » et « Compétences » (provenance en toutes lettres : Archétype / Formation / Expérience).

**Tests curl (31/31)** : historique de Peter (échec puis réussite du pilotage), provenances, 9 compétences proposées à Barry (11 − 2), refus de forcer une compétence déjà acquise, ajout/retrait d'expérience, refus de retirer une compétence d'archétype, validations des dates et du résultat (valeurs conservées), réussite de Wally → « Premiers secours » apparaît via Formation, suppression réservée à l'Admin, agent archivé non modifiable.

**Écart au plan** : page `GET /agents/{id}/trainings/new` ajoutée pour le formulaire (les erreurs s'y affichent près des champs, plutôt que de recharger tout le dossier).

### Étape 7 — Menaces

**Fait**
- `Threat`, `ThreatRepository` (tier, portrait principal, nombre d'opérations, opérations liées, menaces de tier God par libellé).
- `SerialGenerator` (THR-NNNN et OP-AAAA-NNN) et `ThreatService::create()` : nouvelle tentative si l'index UNIQUE refuse un numéro pris par une création simultanée.
- Liste (de la plus dangereuse à la moins dangereuse), fiche (portrait SVG rouge, renseignements, opérations liées avec voyant de statut), formulaire. Numéro de série jamais modifiable.
- **Correctif transversal** : le `Validator` refuse toute saisie qui n'est pas en UTF-8 valide (422 avec message) au lieu de laisser MySQL lever une erreur 1366 (500). Découvert grâce au `curl.exe` de Windows, qui envoyait les accents en Latin-1 ; `tests/lib.sh` encode désormais les valeurs lui-même.

**Tests curl (14/14)** : liste, tri, fiche, création par un Manager (THR-0005 puis THR-0006), accents stockés correctement, informations vides → NULL, modification, contenu `<script>` échappé à l'affichage, saisie Latin-1 → 422.

**Écart au plan** : la suppression d'une menace (Admin, si elle n'est liée à aucune opération) est livrée à l'étape 9, avec `MediaService`, car elle doit d'abord supprimer les médias et leurs fichiers.

### Étape 8 — Opérations et staffing

**Fait**
- `Operation`, `OperationRepository` : liste filtrable par statut, liaisons (missions, lieux, menaces, compétences requises) via une liste blanche de tables, équipes, participants avec **l'équipe à l'époque (requête 3)**, conflits de dates (partie `NOT EXISTS` de la requête 6), **staffing (requête 6 complète)**, agents disponibles pour le tableau de bord.
- `OperationService` (transactions) : `create()` (numéro OP-AAAA-NNN, nouvelle tentative en cas de collision, doublon de nom de code traduit), `update()`, `assignTeam()` (ligne `Operations_Teams` + copie des membres, membres en conflit écartés et listés — Q4), `removeTeam()` (participants conservés — Q5), `addAgent()` (agent archivé, doublon et indisponibilité refusés avec message explicite — règle 8), `removeAgent()`. Les cardinalités 1,n (≥ 1 mission, ≥ 1 lieu) et `date_end > date_beg` sont vérifiées dans le contrôleur **et** dans le service.
- `StaffingService` : suggestions et avertissement de tier non bloquant (règle 10), affiché en tête des participants et sur chaque ligne concernée, en texte.
- Écrans : liste, ordre de mission (illustration, numéro, dates, statut, déclarant, brief, menaces, missions, lieux, compétences requises, équipes, participants, suggestions avec bouton « Ajouter »), formulaire avec groupes de cases à cocher. Section « Opérations » (avec l'équipe à l'époque) dans le dossier agent.
- Script `tests/operations.sh` (premier morceau du script de recette).

**Tests curl (58/58)** — parcours de recette : **staffing « Cendres » → seule Kara Danvers**, **action interdite refusée à un Manager** (Hill sur l'opération de Coulson : 403, rien en base), **création avec missions et lieux** (OP-2026-010, erreurs près des champs et valeurs conservées), **affectation d'équipe** (3 membres de la Justice League copiés ; Avengers affectés mais leurs membres écartés car engagés sur Crépuscule). Aussi : équipe à l'époque (Barry : Avengers pour Aube Rouge, Justice League pour Chevalier Noir), avertissement de tier sur Crépuscule (Thanos, God), conflit de dates bloqué puis levé après annulation de Cendres, agent archivé refusé, retrait d'équipe sans perte de participants, équipe archivée non affectable.

**Écart au plan** : pas d'`AvailabilityService` séparé. La requête de conflit est dans `OperationRepository::findConflicts()` et la règle est appliquée par `OperationService` (un service d'une seule ligne n'apportait rien). La suppression d'une opération arrive à l'étape 9 avec les médias.

### Étape 9 — Médias

**Fait**
- `MediaOwner` (énumération : agent / menace / opération, avec sa colonne et son dossier), `Media`, `MediaRepository`.
- `MediaService::upload()` : code d'erreur PHP, `is_uploaded_file`, 2 Mo maximum, **type réel lu par `finfo`**, confirmation par `getimagesize()` (même type, dimensions non nulles), nom `bin2hex(random_bytes(16))` + extension tirée de la liste blanche, fichier supprimé si l'insertion en base échoue. `removeFiles()` ne supprime que des chemins de la forme produite par l'upload.
- Suppression d'un média : la ligne d'abord, puis le fichier. Suppression d'une **opération** (Admin ou déclarant, Q6) et d'une **menace** (Admin, si aucune opération ne la vise, Q7) : lignes `Medias` supprimées dans la même transaction que l'entité (FK en RESTRICT), fichiers effacés **après le commit**.
- Fiches agent, menace et opération : image principale (la plus récente, requête 8), galerie des précédentes, suppression, formulaire d'upload avec aperçu JS. Textes alternatifs « Portrait de … » / « Illustration de l'opération … ».
- Script `tests/medias.sh` (images de test embarquées en base64).

**Tests curl (48/48)** — parcours **« uploader un portrait et le voir remplacer le visuel par défaut »** (Barry : SVG avant, image après, fichier sur le disque, ligne en base, vignette dans la liste). Aussi : la plus récente devient principale et l'ancienne passe en galerie ; un PNG nommé `evil.php` est enregistré en `.png` ; faux JPEG, polyglotte JPEG + PHP, fichier de 2,5 Mo et envoi vide refusés avec un message ; agent archivé refusé ; upload sans jeton CSRF refusé (403) ; suppression d'un média (ligne et fichier) ; illustration d'une opération réservée à son déclarant ; suppression d'une opération et d'une menace avec leurs fichiers.

### Étape 10 — Tableau de bord

**Fait** : quatre panneaux avec un indicateur chiffré : opérations en cours puis planifiées (par libellé de statut), agents disponibles (en service, sur aucune opération « En cours », décision Q12), menaces de tier God (par libellé, Q9), cinq dernières sessions de formation avec leur résultat.

**Tests curl (13/13)** : Crépuscule (en cours) et Cendres (planifiée) affichées, Aube Rouge (réussie) absente ; 7 agents disponibles (les 3 participants de Crépuscule et Jane, archivée, exclus) ; Darkseid et Thanos ; 5 formations, la plus récente en tête.
