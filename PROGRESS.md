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
| 4 | Agents | à faire |
| 5 | Équipes et appartenances | à faire |
| 6 | Formations et compétences | à faire |
| 7 | Menaces | à faire |
| 8 | Opérations et staffing | à faire |
| 9 | Médias | à faire |
| 10 | Tableau de bord | à faire |
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
