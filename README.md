# Agent Manager

Application web de gestion d'agents dotés de super-pouvoirs : équipes, formations, compétences, menaces et opérations. Sous l'habillage « agence secrète », c'est un outil de gestion RH et de staffing : historique des affectations, formations réussies ou échouées, recherche des agents qualifiés et disponibles pour une opération.

Implémentation de référence d'un fil rouge pédagogique : **PHP 8.3 orienté objet, MVC fait main, PDO, sessions**. Aucun framework, aucune dépendance Composer (autoload PSR-4 uniquement), CSS et JavaScript vanilla.

---

## 1. Installation

### Prérequis

- PHP **8.3** ou plus, avec les extensions `pdo_mysql`, `fileinfo` et `mbstring`
- MySQL 8.0.16+ ou MariaDB 10.5+
- Composer 2

Dans `php.ini`, `upload_max_filesize` doit valoir au moins `2M` (c'est la valeur par défaut) et `post_max_size` au moins `8M`.

### Étapes

```bash
# 1. Créer la base et le jeu de données (le script commence par DROP DATABASE !)
mysql -u root -p < ../agent_manager_v3.sql

# 2. Configurer l'accès à la base
cp config/config.example.php config/config.php
#    puis renseigner host / port / name / user / password dans config/config.php

# 3. Générer l'autoload (aucune dépendance n'est téléchargée)
composer install

# 4. Lancer le serveur de développement
php -S localhost:8000 -t public
```

Ouvrir ensuite <http://localhost:8000>.

`config/config.php` est ignoré par Git : les identifiants ne sont jamais versionnés. En production, passer `'debug' => false` (les traces d'erreur ne sont alors plus affichées).

## 2. Comptes de démonstration

Mot de passe commun : **`Agent2026!`**

| Compte | Rôle | Particularités |
|---|---|---|
| `nick.fury@agentmanager.test` | Administrateur | Accès total, administration |
| `phil.coulson@agentmanager.test` | Manager | Gère la **Justice League**, déclarant de *Chevalier Noir* et *Cendres* |
| `maria.hill@agentmanager.test` | Manager | Gère les **Avengers** et la **Dark Squad**, déclarante de *Aube Rouge* et *Crépuscule* |

### Rôles

| | Administrateur | Manager |
|---|---|---|
| Consulter tout | oui | oui |
| Créer / modifier agents, menaces, formations suivies, compétences d'expérience | oui | oui |
| Archiver agents, équipes, utilisateurs ; créer / modifier les équipes | oui | non |
| Gérer les membres d'une équipe | toutes | **ses** équipes |
| Déplacer un agent d'une équipe à une autre | oui | s'il gère **les deux** équipes ; sinon l'autre manager doit d'abord libérer l'agent |
| Déclarer une opération | oui | oui (il en devient le déclarant) |
| Modifier / supprimer une opération, ses équipes, participants, illustrations | toutes | celles qu'il a déclarées |
| Supprimer une menace (si aucune opération ne la vise) | oui | non |
| Utilisateurs et référentiels | oui | non |

## 3. Architecture

### Arborescence

```
Project/
├── public/               seul dossier exposé par le serveur web
│   ├── index.php         front controller : toutes les requêtes passent ici
│   ├── assets/           css/app.css, js/app.js, fonts/ (Rajdhani, JetBrains Mono — OFL), img/
│   └── uploads/          agents/, threats/, operations/ (fichiers uploadés, non versionnés)
├── config/
│   ├── config.php        configuration locale (ignorée par Git)
│   └── routes.php        table des routes + niveau d'accès de chacune
├── src/
│   ├── bootstrap.php     racine de composition : crée le PDO et câble toutes les dépendances
│   ├── helpers.php       e(), format_date()... (fonctions pures)
│   ├── Core/             App, Router, Route, Request, Response, Session, Csrf, Auth, View, Validator, Database
│   ├── Controller/       un contrôleur par ressource (+ Admin/)
│   ├── Model/            entités (Agent, Team, Operation, Threat, Training, Media...) et énumérations
│   ├── Repository/       RepositoryInterface, AbstractRepository, un repository par agrégat
│   └── Service/          règles métier transactionnelles
├── views/                layouts/, partials/, un dossier par écran
└── tests/                smoke.sh (recette), lib.sh, operations.sh, medias.sh, a11y_check.php
```

### Cycle d'une requête

```
 Navigateur ──HTTP──▶ public/index.php
                          │
                          ▼
                   src/bootstrap.php ─── config ──▶ Database::create() ──▶ PDO (une seule fois)
                          │                                   │
                          │        injecté par constructeur   ▼
                          │        Repositories ◀─────────── PDO
                          │        Services     ◀─────────── Repositories (+ PDO pour les transactions)
                          │        Contrôleurs  ◀─────────── Repositories + Services + View + Auth
                          ▼
                     App::handle()
                          │ 1. Session::start()
                          │ 2. POST ? → Csrf::isValid()          sinon 403
                          │ 3. Router::match()                    sinon 404 / 405
                          │ 4. Access (Guest/Authenticated/Admin) sinon redirection / 403
                          ▼
                     Contrôleur::action(Request, ...paramètres)
                          │ droits fins (Auth::canManageTeam, canEditOperation) → 403
                          │ Validator → 422 + formulaire ré-affiché
                          │ Repository (lecture) / Service (écriture multi-tables, transaction)
                          ▼
                     Response ── View::render(gabarit + layout) ──▶ HTML
                              └─ ou Response::redirect() + message flash (Post/Redirect/Get)
```

### Principes appliqués

- **Pas de Singleton pour PDO** : `Database::create()` est une simple fabrique, appelée une fois dans `src/bootstrap.php` ; la connexion est **injectée** dans chaque repository par son constructeur. `bootstrap.php` est le seul fichier qui fait des `new` : on y lit toutes les dépendances.
- **Repositories** : `RepositoryInterface` (lire, créer, modifier) ; la suppression et l'archivage sont dans deux interfaces séparées, `DeletableRepositoryInterface` et `ArchivableRepositoryInterface`. Un `AgentRepository` n'a donc pas de `delete()` qui lèverait une exception (principes de ségrégation des interfaces et de substitution de Liskov). `AbstractRepository` porte le PDO et les méthodes communes.
- **Contrôleurs fins** : ils lisent la requête, vérifient les droits, valident, puis délèguent. Toute écriture qui touche plusieurs tables passe par un **service** et une **transaction**.
- **Vues sans logique** : aucune requête SQL, toute donnée affichée passe par `e()` (`htmlspecialchars`).
- **Responsabilité unique** : ni `echo` ni `header()` dans les modèles, repositories ou services ; les contrôleurs renvoient un objet `Response`.
- **Requêtes préparées partout**, émulation désactivée. Les seuls identifiants SQL concaténés (noms de table ou de colonne) viennent de constantes ou de listes blanches du code, jamais d'une saisie.

## 4. Règles métier et emplacement dans le code

| # | Règle | Où elle est appliquée |
|---|---|---|
| 1 | **Suppression logique** des agents, équipes et utilisateurs (`archived_at`) ; archivés absents des listes et sélecteurs, visibles dans les historiques | `AgentRepository::archive()`, `TeamRepository::archive()`, `UserRepository::archive()` ; filtres `archived_at IS NULL` dans `AgentRepository::search()`, `TeamRepository::findAll()`, `UserRepository::findActive()` ; historiques non filtrés (`MembershipRepository::findHistoryForAgent()`, `findMembersOfTeam()`, `OperationRepository::findParticipants()`) ; affichage caviardé : `views/partials/agent-name.php`. Archiver un agent clôt aussi son appartenance : `AgentService::archive()` |
| 2 | **Une équipe à la fois** : clore puis ouvrir dans une transaction, message clair si la contrainte est violée | Base : colonne générée `Memberships.active_agent` + `UNIQUE`. Code : `MembershipService::addMember()` (transaction, erreur 1062 traduite) ; droit sur l'équipe d'origine (le manager doit gérer les deux équipes) vérifié dans ce même service ; liste des agents proposés : `TeamController::candidates()` |
| 3 | **Archiver une équipe** clôt les appartenances en cours ; plus de nouveaux membres ni de nouvelles opérations | `MembershipService::archiveTeam()` (transaction) ; gardes dans `MembershipService::addMember()` et `OperationService::assignTeam()` |
| 4 | **Formations** : `is_passed` NULL / 1 / 0, plusieurs tentatives possibles | `Training::resultLabel()` ; `TrainingController::validate()` (résultat seulement avec une date de fin passée) ; une ligne par tentative : `TrainingRepository::insert()` |
| 5 | **Compétences** : trois sources, lues via la vue `agent_abilities` ; saisie d'expérience limitée aux compétences non acquises | `AgentAbilityRepository::findForAgent()` (vue, provenance) et `findAssignableExperience()` ; revérification serveur dans `AgentAbilityController::add()` |
| 6 | **Opération** : au moins une mission et un lieu, `date_end` facultative et postérieure à `date_beg`, création en transaction | `OperationController::validate()` (formulaire) + `OperationService::assertCardinalities()` (service) ; `OperationService::create()` / `update()` (transaction, numéro `OP-AAAA-NNN` par `SerialGenerator`) |
| 7 | **Affectation d'une équipe** : ligne `Operations_Teams` + copie des membres actuels en participants, en transaction ; participants ajustables ensuite | `OperationService::assignTeam()` (les membres indisponibles sont écartés et listés : l'équipe peut partir incomplète) ; `addAgent()` / `removeAgent()` ; retirer une équipe conserve ses participants : `removeTeam()` |
| 8 | **Disponibilité** : pas d'autre opération non annulée aux dates qui se chevauchent ; blocage avec message explicite | `OperationRepository::findConflicts()` (partie `NOT EXISTS` de la requête 6) ; appliquée dans `OperationService::addAgent()` et `assignTeam()` |
| 9 | **Staffing** : agents en service, disponibles, possédant **toutes** les compétences requises | `OperationRepository::findStaffingCandidates()` (requête 6, division relationnelle) ; `StaffingService::suggest()` ; bouton « Ajouter » dans `views/operations/show.php` |
| 10 | **Avertissement de tier** (non bloquant) si un participant est moins fort que la menace la plus dangereuse | `StaffingService::strongestThreat()` et `underTiered()` ; affichage en texte dans `views/operations/show.php` |
| — | **Médias** : JPEG / PNG / WebP, 2 Mo, type réel vérifié, nom aléatoire ; image principale = la plus récente ; suppression du fichier et de la ligne | `MediaService::upload()` (`finfo` puis `getimagesize()`), `MediaService::delete()` ; `MediaRepository::findForOwner()` (tri par `Id_Media` décroissant, requête 8) ; visuel de remplacement SVG : `views/partials/portrait.php` |
| — | **Supprimer une opération ou une menace** : médias d'abord (FK en RESTRICT), fichiers compris | `OperationService::delete()`, `ThreatService::delete()` : lignes `Medias` supprimées dans la transaction, fichiers effacés après le `commit` (`MediaService::deleteRowsForOwner()` + `removeFiles()`) |

Les requêtes du fichier `agent_manager_v3_requetes.sql` sont reprises dans les repositories, avec un commentaire qui renvoie à leur numéro. Seule adaptation : la requête 6 exclut les agents déjà participants, et utilise des marqueurs distincts (`:op_required`, `:op_target`…), car une vraie requête préparée n'accepte pas deux fois le même marqueur nommé.

Les statuts et le tier God sont référencés **par leur libellé** (`Status::CANCELLED`, `Tier::GOD`…). Pour éviter qu'un renommage casse ces règles sans erreur visible, ces libellés sont verrouillés dans l'écran des référentiels.

## 5. Sécurité

| Mesure | Emplacement |
|---|---|
| `password_hash()` / `password_verify()`, re-hachage si besoin, temps constant pour un e-mail inconnu | `Core/Auth::attempt()`, `Admin/UserController` |
| `session_regenerate_id(true)` + nouveau jeton CSRF à la connexion ; déconnexion en POST qui détruit la session et le cookie | `Core/Auth::login()` / `logout()`, `Core/Session::destroy()` |
| Compte archivé refusé à la connexion **et** déconnecté à la requête suivante | `Core/Auth::attempt()` / `user()` |
| Cookie de session `HttpOnly`, `SameSite=Lax`, mode strict | `Core/Session::start()` |
| Jeton CSRF vérifié pour **toute** requête POST, avant le routage | `Core/App::verifyPost()` |
| Aucune action modifiante en GET (archivage, suppression, déconnexion : formulaires POST) | `config/routes.php` |
| Droits vérifiés côté serveur : niveau par route, droits fins dans les contrôleurs | `Core/App::checkAccess()`, `Core/Auth`, `Model/User::canManageTeam()` / `canEditOperation()` |
| Requêtes préparées, `ERRMODE_EXCEPTION`, `charset=utf8mb4` | `Core/Database`, tous les repositories |
| Échappement de toute sortie ; en-têtes `Content-Security-Policy` (aucun script ni style inline), `nosniff` | `helpers.php::e()`, `Core/Response` |
| Saisie non UTF-8 refusée proprement | `Core/Validator::__construct()` |

## 6. Accessibilité (RGAA / WCAG AA)

- Contrastes vérifiés : texte ≥ 4,5:1 (le plus faible, le texte atténué sur fond survolé, est à 7,8:1), composants ≥ 3:1 (bordures de champs ≥ 4,5:1). Couleurs définies par des variables CSS sur `:root`.
- Focus clavier très visible (contour jaune de 3 px), lien d'évitement, `lang="fr"`, un titre de page unique et un seul `h1` par page, landmarks, tableaux avec `caption` et `th scope`.
- Labels associés à tous les champs, erreurs reliées par `aria-describedby` et `aria-invalid`, récapitulatif des erreurs en tête de formulaire (focus automatique avec JS).
- Aucune information portée par la seule couleur : voyants de statut, tiers, provenances et avertissements toujours accompagnés d'un texte.
- Toutes les animations sont coupées sous `prefers-reduced-motion: reduce`.
- Responsive : sous 640 px, les tableaux deviennent des cartes ; navigation sur plusieurs lignes ; l'application fonctionne entièrement sans JavaScript.
- `tests/a11y_check.php` contrôle automatiquement la structure des pages (labels, alt, ids, titres...). Il ne remplace pas un test au clavier et au lecteur d'écran.

## 7. Tests

```bash
bash tests/smoke.sh
```

Le script rejoue les parcours du cahier des charges : déplacement de Barry Allen, archivage d'équipe, création d'opération, affectation d'équipe, staffing de « Cendres » (seule Kara Danvers est proposée), upload de portrait, action interdite à un Manager. Il couvre aussi la disponibilité, les uploads refusés, les suppressions et l'accessibilité structurelle, soit environ 130 vérifications.

> **Attention** : le script **ré-importe la base `agent_manager_v3`** (DROP DATABASE) avant chaque série et à la fin. Variables utiles : `MYSQL` (chemin du client mysql), `DB_USER`, `DB_PASSWORD`, `BASE` (URL du serveur), `PHP`.

Sous Windows, il se lance depuis Git Bash ; il convertit lui-même les chemins de fichiers et l'encodage des accents pour `curl.exe`.
