# Agent Manager — Plan de développement

> Statut : **validé le 10/11/2026**. Les décisions sont consignées en section 10 ; elles priment sur les recommandations de la section 9.

---

## 1. Environnement constaté

| Élément | Constat | Conséquence |
|---|---|---|
| Dossier | `D:\Project_Ideas\Agent_Manager\` (tiret bas, pas d'espace) | Les chemins du cahier des charges sont lus avec ce nom. |
| PHP | 8.5.12 par défaut ; **8.3.30** disponible dans `C:\laragon\bin\php\` (sans `php.ini`) | Code écrit pour PHP 8.3 (aucune fonctionnalité 8.4+ : pas de *property hooks*, pas d'*asymmetric visibility*, pas d'opérateur `\|>`). `php -l` et les tests tourneront **sous 8.3.30**, extensions chargées par `-d extension=…` (rien n'est modifié hors du projet). |
| Extensions | `pdo_mysql`, `fileinfo`, `mbstring`, `gd` présentes | Rien à installer. `getimagesize()` ne dépend pas de GD. |
| Base | MySQL **8.4.7** (Laragon) en service, `root` protégé par mot de passe | Identifiants nécessaires (question Q1). |
| Composer | 2.9.5 | Autoload PSR-4 uniquement. |
| Git | 2.55, `Project/` n'est pas dans un dépôt | `git init` dans `Project/` à l'étape 1. |
| Données de démo | Le hash des trois comptes correspond bien à `Agent2026!` | — |

---

## 2. Points relevés dans le schéma et les requêtes

Rien de bloquant. Je ne contourne rien en silence : voici ce que j'ai repéré et ce que je propose côté application.

1. **`Trainings` : cohérence entre `date_end` et `is_passed` non garantie.** La base accepte une formation « réussie » sans date de fin, ou terminée sans résultat. *Côté application* : le résultat n'est saisissable que si `date_end` est renseignée. *Correctif possible côté base* : `CHECK (is_passed IS NULL OR date_end IS NOT NULL)`.
2. **La requête 6 identifie le statut « Annulée » par son libellé.** Si un administrateur renomme ce statut dans les référentiels, le staffing et la disponibilité cessent de fonctionner sans erreur visible. *Proposition* (Q9) : référencer les statuts système par leur identifiant, via des constantes nommées.
3. **La requête 6 propose aussi les agents déjà participants** à l'opération ciblée (elle n'exclut que les conflits avec les *autres* opérations). *Côté application* : ajout d'un `NOT EXISTS` sur les participants actuels, sinon le bouton « Ajouter » proposerait un doublon.
4. **La requête 6 ne propose personne si l'opération n'a aucune compétence requise** (la jointure sur `Operations_Abilities` est vide). *Côté application* : message « Définissez les compétences requises pour obtenir des suggestions ».
5. **Changement d'équipe et requête 3.** Si `date_leave` et `date_join` valent toutes deux `NOW()` (même transaction), une opération démarrant exactement à cette seconde trouverait deux équipes (bornes inclusives des deux côtés). Cas marginal ; je propose de garder la requête 3 telle quelle et de le signaler en commentaire.
6. **`Teams.team_name` est `UNIQUE`, archives comprises** : le nom d'une équipe archivée ne peut pas être réutilisé. C'est sans doute voulu ; l'application affichera un message clair.
7. **Requêtes préparées et paramètre répété** : la requête 6 utilise `@op` trois fois. Avec `PDO::ATTR_EMULATE_PREPARES = false`, un même marqueur nommé ne peut pas apparaître plusieurs fois. Je l'écrirai avec `:op_target`, `:op_required`, etc. C'est un bon point pédagogique, je le commenterai.
8. Détail cosmétique : `Operations.serial` est en `VARCHAR(30)` et `Threats.serial` en `VARCHAR(50)`.

---

## 3. Architecture

### 3.1 Arborescence

```
Project/
├── public/
│   ├── index.php                 front controller (seul point d'entrée PHP)
│   ├── assets/
│   │   ├── css/app.css           variables :root, thème, composants
│   │   ├── js/app.js             amélioration progressive uniquement
│   │   ├── fonts/                (selon Q11)
│   │   └── img/                  placeholder.svg, icônes
│   └── uploads/{agents,threats,operations}/.gitkeep
├── config/
│   ├── config.example.php        versionné
│   ├── config.php                ignoré par Git
│   └── routes.php                table des routes + droits
├── src/
│   ├── bootstrap.php             composition : config → PDO → repositories → services → contrôleurs
│   ├── helpers.php               e(), csrf_field(), url(), old() — chargé par Composer "files"
│   ├── Core/
│   │   ├── Router.php            méthode + motif /agents/{id} → [Contrôleur, méthode]
│   │   ├── Request.php           accès typé à GET/POST/FILES
│   │   ├── Response.php          redirect(), statuts HTTP
│   │   ├── Database.php          fabrique PDO (méthode create(array $config): PDO) — PAS un Singleton
│   │   ├── Session.php           démarrage sécurisé, flash messages
│   │   ├── Csrf.php              jeton par session, vérification hash_equals
│   │   ├── Auth.php              connexion, utilisateur courant, contrôles de rôle
│   │   ├── View.php              rendu vue + layout, tampon de sortie
│   │   ├── Validator.php         règles simples (required, maxLength, date, in…)
│   │   └── Exception/            HttpException (403/404/405), CsrfException, BusinessRuleException
│   ├── Controller/
│   │   ├── AbstractController.php   render(), redirect(), flash(), requireAdmin()…
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── AgentController.php
│   │   ├── TrainingController.php
│   │   ├── AgentAbilityController.php
│   │   ├── TeamController.php
│   │   ├── OperationController.php
│   │   ├── ThreatController.php
│   │   ├── MediaController.php
│   │   └── Admin/{UserController, TierController, ArchetypeController,
│   │             FormationController, ReferenceController}.php
│   ├── Model/                    entités : Agent, Team, User, Operation, Threat, Training,
│   │                             Membership, Media, Tier, Ability, Archetype, Formation,
│   │                             Status, Mission, Location, Role
│   ├── Repository/
│   │   ├── RepositoryInterface.php
│   │   ├── AbstractRepository.php   porte le PDO (injecté), findById/findAll génériques
│   │   └── AgentRepository, TeamRepository, MembershipRepository, TrainingRepository,
│   │       AbilityRepository, OperationRepository, ThreatRepository, MediaRepository,
│   │       UserRepository, TierRepository, ArchetypeRepository, FormationRepository,
│   │       ReferenceRepository (Status, Missions, Locations, Abilities)
│   └── Service/
│       ├── MembershipService.php    changement d'équipe, retrait, archivage d'équipe
│       ├── AgentService.php         archivage d'agent
│       ├── OperationService.php     création/mise à jour (missions, lieux…), affectation
│       │                            d'équipe, ajout de participant, suppression
│       ├── AvailabilityService.php  vérification des chevauchements (requête 6, partie NOT EXISTS)
│       ├── StaffingService.php      suggestions (requête 6 complète) + avertissement de tier
│       ├── MediaService.php         upload, validation finfo/getimagesize, suppression fichier+ligne
│       └── SerialGenerator.php      numéros OP-AAAA-NNN / THR-NNNN (selon Q8)
├── views/
│   ├── layouts/main.php, layouts/auth.php
│   ├── partials/                flash, field (label + erreur + aria-describedby), portrait
│   │                            (image ou SVG de remplacement), status-badge, tier-badge,
│   │                            hud-frame, redacted, pagination
│   ├── errors/{403,404,405,419,500}.php
│   └── auth/, dashboard/, agents/, teams/, operations/, threats/, admin/
├── tests/smoke.sh               parcours curl (selon Q15)
├── composer.json
├── .gitignore
├── README.md, PLAN.md, PROGRESS.md
```

### 3.2 Cycle d'une requête

```
navigateur
   │  GET /agents/5
   ▼
public/index.php ──► src/bootstrap.php
   │                   ├─ config/config.php
   │                   ├─ Database::create($config['db'])   ← PDO créé UNE fois
   │                   ├─ new AgentRepository($pdo), …     ← injection par constructeur
   │                   ├─ new MembershipService($pdo, $membershipRepo, …)
   │                   └─ fabriques de contrôleurs (closures)
   ├─ Session::start()
   ├─ si POST : Csrf::verify()            ← contrôle centralisé, aucune route POST n'y échappe
   ├─ Router::dispatch($method, $path)
   │      └─ route trouvée → contrôle d'accès déclaré (guest / auth / admin)
   ▼
AgentController::show(5)
   ├─ contrôle fin éventuel (ex. « manager de cette équipe ? ») via Auth
   ├─ appelle repositories / services
   └─ View::render('agents/show', [...])  → layouts/main.php
```

**Injection de dépendances sans conteneur magique.** `bootstrap.php` est la *racine de composition* : c'est le seul endroit qui fait des `new`. Il expose un tableau de fabriques `NomContrôleur::class => fn() => new AgentController($view, $auth, $agentRepo, …)`. Le routeur reçoit ce tableau et instancie uniquement le contrôleur appelé. Les apprenants voient explicitement qui dépend de quoi. Aucun contrôleur ne reçoit le tableau de fabriques (pas de *Service Locator*).

### 3.3 Repositories

- `RepositoryInterface` : `findById(int $id): ?object`, `findAll(): array`, `insert(object $entity): int`, `update(object $entity): void`.
- La suppression et l'archivage sont dans deux interfaces séparées (question Q10) :
  - `DeletableRepositoryInterface::delete(int $id)` pour les référentiels, les menaces et les opérations ;
  - `ArchivableRepositoryInterface::archive(int $id)` pour les agents, les équipes et les utilisateurs.
  
  Ainsi `AgentRepository` n'a pas de `delete()` qui lèverait une exception : c'est une illustration concrète du principe de ségrégation des interfaces et de celui de substitution de Liskov.
- `AbstractRepository` : reçoit `PDO` au constructeur, déclare `protected const TABLE` et `PRIMARY_KEY`, implémente `findById` et `findAll` génériques et une méthode `hydrate(array $row)` abstraite. Les noms de table et de colonne viennent de **constantes de classe**, jamais d'une saisie : c'est la seule façon sûre d'insérer un identifiant dans du SQL (on ne peut pas lier un nom de table en paramètre).
- **Entités** pour les agrégats manipulés en CRUD (constructeur à promotion de propriétés, `fromRow()` statique). **Tableaux associatifs** pour les requêtes de lecture multi-tables (historiques, staffing, tableau de bord), documentés par un commentaire `@return list<array{...}>`. Je recommande ce compromis (Q13) pour éviter de multiplier les DTO.
- Les requêtes du fichier `agent_manager_v3_requetes.sql` sont reprises telles quelles, avec un commentaire `// Requête N du fichier de requêtes` et les adaptations du §2 signalées.

### 3.4 Services et transactions

Chaque méthode de service qui touche plusieurs tables suit le même gabarit, que les apprenants retrouveront partout :

```php
$this->pdo->beginTransaction();
try {
    // … plusieurs requêtes …
    $this->pdo->commit();
} catch (\Throwable $e) {
    $this->pdo->rollBack();
    throw $e;  // ou traduction en BusinessRuleException
}
```

Les violations de contraintes sont traduites en messages métier, jamais affichées brutes : erreur `1062` sur `uq_memberships_active_agent` → « Cet agent appartient déjà à une équipe » ; erreur `1451` (clé étrangère) → « Cette valeur est utilisée, elle ne peut pas être supprimée ».

### 3.5 Formulaires, messages, erreurs

- **Post/Redirect/Get** : en cas de succès, un message flash est posé puis une redirection est faite. En cas d'erreur de validation, le formulaire est ré-affiché directement (HTTP 422) avec les valeurs saisies et les erreurs près des champs (`aria-describedby`, `aria-invalid`). Je n'ai pas besoin de stocker les anciennes valeurs en session.
- Dates : champs `datetime-local`, convertis en `Y-m-d H:i:s`. Fuseau `Europe/Paris` défini dans la config.
- Gestionnaire d'exceptions global : page 500 sobre, détail affiché uniquement si `debug = true` dans la config.
- Requête POST vide alors que `CONTENT_LENGTH > 0` (dépassement de `post_max_size`) : message « fichier trop volumineux » au lieu d'une erreur CSRF incompréhensible.

### 3.6 Sécurité

| Exigence | Mise en œuvre |
|---|---|
| Mots de passe | `password_hash(PASSWORD_DEFAULT)` / `password_verify()` ; `password_needs_rehash()` à la connexion. |
| Session | `use_strict_mode`, cookie `HttpOnly` + `SameSite=Lax`, `session_regenerate_id(true)` à la connexion ; déconnexion en **POST** qui vide `$_SESSION`, supprime le cookie et appelle `session_destroy()`. |
| Compte archivé | Refusé à la connexion avec un message générique, **et** l'utilisateur est rechargé à chaque requête : un compte archivé pendant qu'il est connecté est déconnecté. |
| CSRF | Jeton unique par session, champ caché `_csrf` ajouté par `csrf_field()`, vérifié dans le front controller pour **toute** requête POST. |
| GET | Aucune action modifiante : archivage, suppression, retrait et déconnexion passent tous par des formulaires POST. |
| Droits | Chaque route déclare son niveau (`guest`, `auth`, `admin`) dans `routes.php`. Les droits fins (manager de l'équipe, déclarant de l'opération) sont vérifiés dans le contrôleur par `Auth::canManageTeam()` / `Auth::canEditOperation()`. Une page 403 s'affiche, quels que soient les boutons visibles. |
| SQL | Requêtes préparées partout, `ATTR_EMULATE_PREPARES = false`, `ERRMODE_EXCEPTION`, `charset=utf8mb4`. |
| Sorties | `e()` = `htmlspecialchars($v, ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')` sur toute donnée affichée. |
| Uploads | Voir §6. |

---

## 4. Rôles et droits

Je reprends la proposition du cahier des charges, avec les précisions attendues dans les questions Q2 à Q7.

| Domaine | Administrateur | Manager |
|---|---|---|
| Consultation (tout) | oui | oui |
| Agents : créer / modifier | oui | oui |
| Agents : archiver | oui | non |
| Formations suivies, compétences d'expérience | oui | oui |
| Portraits d'agents et de menaces | oui | oui |
| Équipes : créer, renommer, changer de manager, archiver | oui | non |
| Membres d'une équipe | toutes | **ses** équipes (`Teams.Id_User`), voir Q2 |
| Menaces : créer / modifier | oui | oui |
| Menaces : supprimer | oui (si non liée, voir Q7) | non |
| Opérations : déclarer | oui | oui (devient `Operations.Id_User`) |
| Opérations : modifier, équipes, participants, compétences requises, illustrations | toutes | celles qu'il a déclarées |
| Opérations : supprimer | voir Q6 | voir Q6 |
| Utilisateurs, référentiels | oui | non |

Garde-fous complémentaires : un administrateur ne peut pas archiver son propre compte. On ne peut pas archiver un utilisateur qui gère encore des équipes actives : il faut d'abord réaffecter ces équipes.

---

## 5. Routes

Légende des droits : **Invité** (non connecté) · **Connecté** (tout rôle) · **Admin** · **Admin+Mgr** (les deux rôles) · **Gestionnaire** (Admin, ou Manager de l'équipe concernée) · **Déclarant** (Admin, ou Manager ayant déclaré l'opération).

### Authentification et accueil
| Méthode | URL | Action | Droits |
|---|---|---|---|
| GET | `/login` | formulaire de connexion | Invité |
| POST | `/login` | connexion | Invité |
| POST | `/logout` | déconnexion | Connecté |
| GET | `/` | tableau de bord | Connecté |

### Agents, formations, compétences
| Méthode | URL | Action | Droits |
|---|---|---|---|
| GET | `/agents` | liste filtrable `?team=&tier=&archetype=&ability=` | Connecté |
| GET / POST | `/agents/new`, `/agents` | création | Admin+Mgr |
| GET | `/agents/{id}` | dossier de l'agent (archivé compris) | Connecté |
| GET / POST | `/agents/{id}/edit`, `/agents/{id}` | modification (impossible si archivé) | Admin+Mgr |
| POST | `/agents/{id}/archive` | archivage | Admin |
| POST | `/agents/{id}/team` | changer d'équipe depuis la fiche | voir Q2 |
| POST | `/agents/{id}/trainings` | nouvelle session de formation | Admin+Mgr |
| GET / POST | `/trainings/{id}/edit`, `/trainings/{id}` | modifier (résultat, dates) | Admin+Mgr |
| POST | `/trainings/{id}/delete` | supprimer une saisie erronée | Admin |
| GET | `/agents/{id}/abilities` | compétences d'expérience (seulement celles non acquises autrement) | Admin+Mgr |
| POST | `/agents/{id}/abilities` | ajouter | Admin+Mgr |
| POST | `/agents/{id}/abilities/{abilityId}/delete` | retirer | Admin+Mgr |
| POST | `/agents/{id}/medias` | uploader un portrait | Admin+Mgr |

### Équipes
| Méthode | URL | Action | Droits |
|---|---|---|---|
| GET | `/teams`, `/teams/{id}` | liste, fiche (membres actuels et anciens) | Connecté |
| GET / POST | `/teams/new`, `/teams` | création (nom, manager) | Admin |
| GET / POST | `/teams/{id}/edit`, `/teams/{id}` | renommer, changer de manager | Admin |
| POST | `/teams/{id}/archive` | archiver + clore les appartenances | Admin |
| POST | `/teams/{id}/members` | ajouter un agent (transfert éventuel) | Gestionnaire (Q2) |
| POST | `/teams/{id}/members/{agentId}/remove` | retirer un membre | Gestionnaire |

### Opérations
| Méthode | URL | Action | Droits |
|---|---|---|---|
| GET | `/operations` | liste `?status=` | Connecté |
| GET / POST | `/operations/new`, `/operations` | déclaration (missions ≥ 1, lieux ≥ 1, menaces, compétences) | Admin+Mgr |
| GET | `/operations/{id}` | ordre de mission + staffing + avertissements de tier | Connecté |
| GET / POST | `/operations/{id}/edit`, `/operations/{id}` | modification | Déclarant |
| POST | `/operations/{id}/delete` | suppression (médias et fichiers d'abord) | Q6 |
| POST | `/operations/{id}/teams` | affecter une équipe + copier ses membres | Déclarant |
| POST | `/operations/{id}/teams/{teamId}/remove` | retirer l'équipe | Déclarant (Q5) |
| POST | `/operations/{id}/agents` | ajouter un participant (manuel ou staffing) | Déclarant |
| POST | `/operations/{id}/agents/{agentId}/remove` | retirer un participant | Déclarant |
| POST | `/operations/{id}/medias` | uploader une illustration | Déclarant |

### Menaces et médias
| Méthode | URL | Action | Droits |
|---|---|---|---|
| GET | `/threats`, `/threats/{id}` | liste, fiche (portrait, tier, opérations liées) | Connecté |
| GET / POST | `/threats/new`, `/threats` | création | Admin+Mgr |
| GET / POST | `/threats/{id}/edit`, `/threats/{id}` | modification | Admin+Mgr |
| POST | `/threats/{id}/delete` | suppression si non liée | Admin (Q7) |
| POST | `/threats/{id}/medias` | uploader un portrait | Admin+Mgr |
| POST | `/medias/{id}/delete` | supprimer fichier + ligne | droits d'écriture sur l'entité propriétaire |

### Administration (Admin uniquement)
| Méthode | URL | Action |
|---|---|---|
| GET | `/admin` | accueil de l'administration |
| GET / POST | `/admin/users`, `/admin/users/new`, `/admin/users/{id}/edit`, `/admin/users/{id}` | liste, création, modification (rôle, nouveau mot de passe facultatif) |
| POST | `/admin/users/{id}/archive` | archivage (garde-fous du §4) |
| GET / POST | `/admin/{ref}`, `/admin/{ref}/new`, `/admin/{ref}/{id}/edit`, `/admin/{ref}/{id}` | CRUD d'un référentiel |
| POST | `/admin/{ref}/{id}/delete` | suppression si la valeur n'est pas utilisée |

`{ref}` ∈ `tiers` (libellé + niveau), `abilities`, `archetypes` (+ cases à cocher des compétences), `formations` (+ compétences délivrées), `statuses`, `missions`, `locations`. Les référentiels « libellé seul » partagent un `ReferenceController` configuré par un descripteur ; tiers, archétypes et formations ont leur propre contrôleur. Ces routes sont déclarées explicitement, une boucle dans `routes.php` les génère.

Pages d'erreur : 403 (droits), 404, 405 (méthode), 419 (jeton CSRF invalide ou session expirée), 500.

---

## 6. Règles métier : où elles vivront

| # | Règle | Emplacement prévu |
|---|---|---|
| 1 | Suppression logique, archivés exclus des listes et des sélecteurs, visibles dans les historiques | `archive()` des repositories ; `WHERE archived_at IS NULL` dans `findActive()` ; historiques sans filtre + rendu caviardé |
| 2 | Une équipe à la fois, changement en transaction, message clair si la contrainte est violée | `MembershipService::moveAgent()` ; traduction de l'erreur 1062 |
| 3 | Archivage d'équipe : clôture des appartenances, plus de nouveaux membres ni de nouvelles opérations | `MembershipService::archiveTeam()` ; gardes dans `moveAgent()` et `OperationService::assignTeam()` |
| 4 | Formations : NULL / 1 / 0, plusieurs tentatives | `TrainingController` + validation (résultat seulement si date de fin) |
| 5 | Compétences : vue `agent_abilities`, saisie limitée aux compétences non acquises | `AbilityRepository::findForAgent()`, `findAssignableExperience()` |
| 6 | Opération : ≥ 1 mission, ≥ 1 lieu, `date_end > date_beg`, création en transaction | `OperationService::create()/update()` + validation HTML (`required`) et serveur |
| 7 | Affectation d'équipe : ligne `Operations_Teams` + copie des membres, en transaction | `OperationService::assignTeam()` |
| 8 | Disponibilité vérifiée avant tout ajout de participant | `AvailabilityService::findConflicts()` appelé par `OperationService::addAgent()` et `assignTeam()` |
| 9 | Staffing : en service, disponibles, toutes les compétences requises | `StaffingService::suggest()` (requête 6 adaptée) |
| 10 | Avertissement si tier d'un participant < tier max des menaces | `StaffingService::tierWarnings()` ; affichage non bloquant dans la fiche opération |

### Médias (§9 du cahier des charges)

`MediaService::upload(array $file, MediaOwner $owner)`, où `MediaOwner` est un petit objet `{type: agent|threat|operation, id}` :

1. vérifie le code `UPLOAD_ERR_*`, `is_uploaded_file()` et la taille (≤ 2 Mo) ;
2. lit le type réel avec `finfo_file(…, FILEINFO_MIME_TYPE)` et le compare à une liste blanche `image/jpeg → jpg`, `image/png → png`, `image/webp → webp` ; l'extension vient **de cette table**, jamais du nom envoyé ;
3. contrôle `getimagesize()` (fichier réellement décodable, dimensions non nulles) ;
4. génère le nom `bin2hex(random_bytes(16)) . '.' . $ext`, puis `move_uploaded_file()` vers `public/uploads/{agents|threats|operations}/` ;
5. insère la ligne `Medias` (`url` = `uploads/agents/xxxx.webp`) ; si l'insertion échoue, le fichier est supprimé.

Suppression : la ligne est supprimée d'abord, puis le fichier après le `commit`. Une opération ou une menace se supprime en trois temps : ses médias, puis l'entité, dans une transaction, et les fichiers sont effacés après validation.

Image principale : `MAX(Id_Media)` (requête 8). Une galerie de vignettes avec boutons de suppression s'affiche sous le portrait. Sans image, le partial `portrait` produit un **SVG inline** : silhouette ou réticule, mention « NO VISUAL // CLASSIFIED », `role="img"` et texte alternatif. Les attributs `alt` suivent les formulations demandées.

---

## 7. Interface et accessibilité

- **Tokens CSS** sur `:root` : `--bg` (#0a0f0d, vert-noir), `--panel`, `--line`, `--text`, `--muted`, `--accent` (vert tactique ~#3ddc84), `--warn` (ambre), `--danger` (rouge). Je vérifierai chaque couple texte/fond au calcul de contraste (≥ 4,5:1 pour le texte, ≥ 3:1 pour les composants) et je consignerai les valeurs dans `PROGRESS.md`.
- **Typographie** : Rajdhani pour les titres, JetBrains Mono pour les données, avec piles de repli (`"Rajdhani", "Barlow Condensed", "Arial Narrow", sans-serif` et `"JetBrains Mono", "Consolas", monospace`). Hébergement : voir Q11.
- **Composants** : cadres HUD à coins en équerre (pseudo-éléments), fond quadrillé en `background-image`, bandeau « TOP SECRET // EYES ONLY », numéros de dossier, tampons CLASSIFIED / ARCHIVED (texte réel, pas d'image), caviardage des agents archivés (texte réel lisible par les lecteurs d'écran avec la mention « archivé »), portraits en `filter: grayscale()` + calque bichrome + réticule, voyants de statut **toujours accompagnés d'un libellé**.
- **Animations** : apparition des panneaux, balayage des en-têtes. Toutes neutralisées sous `@media (prefers-reduced-motion: reduce)`.
- **Accessibilité** : `lang="fr"`, lien d'évitement, `<title>` unique par page (« Barry Allen — Dossier agent — Agent Manager »), un seul `h1`, landmarks (`header`, `nav`, `main`, `footer`), tableaux avec `caption` et `th scope`, focus `outline` épais et contrasté, `aria-current` dans la navigation, erreurs reliées par `aria-describedby` avec un résumé des erreurs en tête de formulaire, cibles tactiles ≥ 44 px.
- **Responsive** : grille CSS, tableaux repliés en cartes sous 640 px, navigation repliable qui fonctionne sans JS (`<details>`).
- **JavaScript** (`app.js`, quelques dizaines de lignes) : confirmation avant archivage ou suppression, aperçu de l'image avant upload, envoi automatique des filtres. Tout fonctionne sans JS.

---

## 8. Découpage en étapes

À la fin de **chaque** étape : `php -l` (PHP 8.3) sur les fichiers modifiés, serveur `php -S` lancé et pages appelées avec `curl` (connexion comprise, cookie et jeton CSRF extraits), commit explicite, mise à jour de `PROGRESS.md`.

| # | Étape | Contenu | Vérification curl |
|---|---|---|---|
| 1 | Socle | `git init`, `.gitignore`, `composer.json`, `Database`, `Router`, `Request/Response`, `View`, `Session`, `Csrf`, bootstrap, layout, CSS de base (tokens, grille, HUD), pages d'erreur | `/` → 302 vers `/login`, route inconnue → 404, POST sans jeton → 419 |
| 2 | Authentification et rôles | `UserRepository`, `Auth`, login/logout, garde de routes, contrôle par requête du compte archivé | connexion des 3 comptes, mauvais mot de passe, POST `/logout`, `/admin` → 403 pour un Manager |
| 3 | Référentiels | Interfaces de repository, `AbstractRepository`, CRUD admin des tiers, compétences, archétypes (+ compétences), formations (+ compétences), statuts, missions, lieux ; gestion des utilisateurs | création, modification, suppression refusée si la valeur est utilisée |
| 4 | Agents | Liste filtrable, création/modification, archivage, dossier (sans les sections des étapes suivantes) | filtres, 422 avec valeurs conservées, archivage |
| 5 | Équipes et appartenances | `MembershipService`, fiche équipe, membres, transfert, archivage d'équipe | **Barry Allen déplacé**, **équipe archivée et appartenances closes** |
| 6 | Formations et compétences | Sessions de formation, compétences d'expérience, provenance via `agent_abilities` | compétences proposées filtrées, échec puis réussite |
| 7 | Menaces | CRUD, fiche, opérations liées, numéro de série | — |
| 8 | Opérations et staffing | `OperationService`, `AvailabilityService`, `StaffingService`, fiche ordre de mission | **création avec missions et lieux**, **affectation d'équipe et copie des membres**, **« Cendres » → Kara Danvers seule**, conflit de disponibilité bloqué, avertissement de tier |
| 9 | Médias | `MediaService`, galeries, SVG de remplacement | **portrait uploadé qui remplace le visuel par défaut**, faux JPEG refusé (fichier texte renommé), > 2 Mo refusé |
| 10 | Tableau de bord | Les 4 panneaux | contenu attendu avec les données de démo |
| 11 | Finitions graphiques et accessibilité | Passes contraste, clavier et `prefers-reduced-motion`, responsive, README final | parcours complet `tests/smoke.sh`, **action interdite refusée à un Manager** |

---

## 9. Questions

Chaque question propose une **recommandation** en gras.

**Q1 — Accès à la base.** `root` est protégé par mot de passe. Peux-tu me donner des identifiants, ou préfères-tu créer toi-même un utilisateur dédié ?
```sql
CREATE USER 'agent_manager'@'localhost' IDENTIFIED BY '…';
GRANT ALL PRIVILEGES ON agent_manager_v3.* TO 'agent_manager'@'localhost';
```
La base `agent_manager_v3` est-elle déjà importée ? Les tests modifient les données (Barry déplacé, équipe archivée…). **Recommandation : m'autoriser à ré-importer `agent_manager_v3.sql` (qui commence par `DROP DATABASE`) au début de chaque campagne de tests**, pour repartir d'un état connu.

**Q2 — Qui peut déplacer un agent d'une équipe à l'autre ?** Exemple : Barry est dans la Justice League (Coulson) et doit passer chez les Avengers (Hill).
**Recommandation : le manager de l'équipe de destination peut « recruter » n'importe quel agent en service (son appartenance actuelle est close automatiquement) ; le manager de l'équipe actuelle peut retirer un membre ; l'Admin peut tout faire.** Alternative plus stricte : il faut gérer les deux équipes.

**Q3 — Archiver un agent.** **Recommandation : l'archivage clôt aussi son appartenance en cours (même transaction, par cohérence avec la règle 3) ; ses participations aux opérations restent intactes (historique), mais un avertissement s'affiche s'il figurait sur une opération planifiée ou en cours.**

**Q4 — Affectation d'une équipe dont certains membres sont indisponibles.** **Recommandation : l'équipe est affectée, les membres disponibles sont copiés, les membres en conflit sont écartés et listés dans un message explicite (règle 8 appliquée agent par agent).** Alternative : tout refuser tant qu'un seul membre est en conflit.

**Q5 — Retirer une équipe d'une opération.** **Recommandation : seule la ligne `Operations_Teams` est supprimée ; les participants restent, à ajuster à la main** (la règle 7 les rend indépendants après la copie). Alternative : retirer aussi les participants qui sont encore membres de cette équipe.

**Q6 — Suppression d'une opération.** **Recommandation : Admin et Manager déclarant, avec confirmation.** Alternative : Admin seulement.

**Q7 — Suppression d'une menace.** Les menaces n'ont pas d'`archived_at`. **Recommandation : Admin seulement, et uniquement si elle n'est liée à aucune opération (la clé étrangère est en RESTRICT) ; ses médias sont supprimés d'abord.**

**Q8 — Numéros de série.** **Recommandation : générés automatiquement et non modifiables : `OP-{année de date_beg}-{NNN}` et `THR-{NNNN}`, calculés dans la transaction de création (l'index UNIQUE protège des collisions).** Alternative : saisie libre validée.

**Q9 — Statuts et tiers référencés dans le code.** **Recommandation : constantes d'identifiant (`Status::PLANNED = 1`, `IN_PROGRESS = 2`, `CANCELLED = 5`) au lieu des libellés, et « tier God » = tier de `level` maximal.** La requête 6 devient `other.Id_Status <> :cancelled` au lieu de `s.status <> 'Annulée'`. Les statuts système ne peuvent pas être supprimés (leurs libellés restent modifiables).

**Q10 — `RepositoryInterface`.** Le cahier des charges parle d'un « contrat CRUD ». **Recommandation : `RepositoryInterface` (lecture, insertion, mise à jour) + `DeletableRepositoryInterface` + `ArchivableRepositoryInterface`**, pour éviter un `AgentRepository::delete()` qui lèverait une exception. Alternative : une interface unique avec `delete()`.

**Q11 — Polices.** **Recommandation : fichiers `woff2` (licence OFL) hébergés dans `public/assets/fonts/`** : application utilisable hors ligne, pas d'appel à Google (RGPD). Cela suppose que je télécharge une seule fois Rajdhani et JetBrains Mono. Alternative : lien Google Fonts.

**Q12 — Tableau de bord, « agents disponibles (sans opération en cours) ».** **Recommandation : agents en service qui ne participent à aucune opération de statut « En cours ».** Alternative : aucune opération non annulée dont la période couvre l'instant présent.

**Q13 — Résultats des requêtes de lecture.** **Recommandation : entités pour le CRUD, tableaux associatifs typés en PHPDoc pour les requêtes de reporting** (historiques, staffing, tableau de bord). Alternative : un DTO par requête.

**Q14 — Formations.** **Recommandation : résultat saisissable seulement si la date de fin l'est ; suppression d'une session réservée à l'Admin (correction de saisie).**

**Q15 — Script de recette.** **Recommandation : versionner `tests/smoke.sh` (bash + curl)**, qui rejoue les parcours du §13 du cahier des charges. Les apprenants pourront le relancer, et il ne s'agit pas d'une dépendance Composer.

---

---

## 10. Décisions (validation du 10/11/2026)

| Question | Décision |
|---|---|
| Q1 | Base locale accessible en `root` ; ré-import de `agent_manager_v3.sql` autorisé avant chaque campagne de tests. |
| Q2 | **Un Manager ne déplace un agent que s'il gère les deux équipes.** Un Manager peut intégrer dans son équipe un agent **sans équipe**. Si l'agent appartient à une équipe gérée par un autre manager, ce dernier doit d'abord le **libérer** (retrait), puis le premier peut l'intégrer. L'Admin peut tout faire. |
| Q3 | Recommandation retenue : l'archivage d'un agent clôt son appartenance en cours ; participations conservées, avertissement si opération planifiée ou en cours. |
| Q4 | **Les membres en conflit sont écartés** et listés dans le message ; une équipe peut partir incomplète sur une opération. |
| Q5 | Recommandation retenue : retirer une équipe ne retire pas les participants. |
| Q6 | Recommandation retenue : suppression par l'Admin et le Manager déclarant, avec confirmation. |
| Q7 | Recommandation retenue : suppression d'une menace par l'Admin, si elle n'est liée à aucune opération. |
| Q8 | Recommandation retenue : numéros `OP-AAAA-NNN` et `THR-NNNN` générés automatiquement. |
| Q9 | **Statuts et tiers référencés par leur libellé**, centralisés dans des constantes (`Status::CANCELLED = 'Annulée'`, `Tier::GOD = 'God'`…), comme dans la requête 6. Conséquence : les libellés utilisés par le code (`Planifiée`, `En cours`, `Annulée`, `God`) sont **verrouillés** dans l'écran des référentiels (ni renommage ni suppression), sinon le staffing casserait sans erreur visible. |
| Q10 | `RepositoryInterface` + `DeletableRepositoryInterface` + `ArchivableRepositoryInterface`. |
| Q11 | Polices hébergées dans `public/assets/fonts/` (repli : Google Fonts si le téléchargement est impossible). |
| Q12 à Q15 | Recommandations retenues. |
