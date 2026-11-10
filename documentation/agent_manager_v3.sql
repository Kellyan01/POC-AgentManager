-- =====================================================================
--  Agent Manager v3 — création de la base + jeu de données
--  MySQL 8.0.16+ / MariaDB 10.5+ — InnoDB, utf8mb4
--  ATTENTION : la première ligne supprime la base si elle existe déjà
--
--  Règles de suppression appliquées :
--    - FK vers Agents et Users : RESTRICT (suppression logique via archived_at)
--    - FK d'une table de liaison vers son « propriétaire »
--      (Operations, Archetypes, Formations) : CASCADE
--    - FK vers un référentiel (Roles, Tiers, Status, Abilities, Teams...) :
--      RESTRICT (on ne supprime pas une valeur encore utilisée)
-- =====================================================================

DROP DATABASE IF EXISTS agent_manager_v3;
CREATE DATABASE agent_manager_v3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agent_manager_v3;
SET NAMES utf8mb4;

-- =====================================================================
--  RÉFÉRENTIELS
-- =====================================================================

CREATE TABLE Roles (
    Id_Role INT AUTO_INCREMENT PRIMARY KEY,
    role    VARCHAR(50) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE Tiers (
    Id_Tier INT AUTO_INCREMENT PRIMARY KEY,
    tier    VARCHAR(10) NOT NULL UNIQUE,
    level   INT         NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE Abilities (
    Id_Ability INT AUTO_INCREMENT PRIMARY KEY,
    ability    VARCHAR(50) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE Archetypes (
    Id_Archetype INT AUTO_INCREMENT PRIMARY KEY,
    archetype    VARCHAR(50) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE Formations (
    Id_Formation INT AUTO_INCREMENT PRIMARY KEY,
    formation    VARCHAR(100) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE Status (
    Id_Status INT AUTO_INCREMENT PRIMARY KEY,
    status    VARCHAR(30) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE Missions (
    Id_Mission INT AUTO_INCREMENT PRIMARY KEY,
    mission    VARCHAR(50) NOT NULL UNIQUE
) ENGINE = InnoDB;

CREATE TABLE Locations (
    Id_Location INT AUTO_INCREMENT PRIMARY KEY,
    location    VARCHAR(100) NOT NULL UNIQUE
) ENGINE = InnoDB;

-- =====================================================================
--  UTILISATEURS ET ÉQUIPES
-- =====================================================================

CREATE TABLE Users (
    Id_User     INT AUTO_INCREMENT PRIMARY KEY,
    firstname   VARCHAR(50)  NOT NULL,
    lastname    VARCHAR(50)  NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME     NULL,           -- NULL = compte actif
    Id_Role     INT          NOT NULL,
    CONSTRAINT fk_users_roles FOREIGN KEY (Id_Role)
        REFERENCES Roles (Id_Role) ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE Teams (
    Id_Team    INT AUTO_INCREMENT PRIMARY KEY,
    team_name  VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME    NULL,            -- NULL = équipe active
    Id_User    INT         NOT NULL,         -- le manager de l'équipe
    CONSTRAINT fk_teams_users FOREIGN KEY (Id_User)
        REFERENCES Users (Id_User) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- =====================================================================
--  AGENTS
-- =====================================================================

CREATE TABLE Agents (
    Id_Agent     INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(50) NOT NULL,
    dob          DATE        NULL,
    created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at  DATETIME    NULL,           -- NULL = agent en service
    Id_Archetype INT         NOT NULL,
    Id_Tier      INT         NOT NULL,
    CONSTRAINT fk_agents_archetypes FOREIGN KEY (Id_Archetype)
        REFERENCES Archetypes (Id_Archetype) ON DELETE RESTRICT,
    CONSTRAINT fk_agents_tiers FOREIGN KEY (Id_Tier)
        REFERENCES Tiers (Id_Tier) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Historique des appartenances aux équipes
CREATE TABLE Memberships (
    Id_Membership INT AUTO_INCREMENT PRIMARY KEY,
    date_join     DATETIME NOT NULL,
    date_leave    DATETIME NULL,             -- NULL = appartenance en cours
    Id_Agent      INT      NOT NULL,
    Id_Team       INT      NOT NULL,
    -- Colonne générée : vaut Id_Agent si l'appartenance est en cours, NULL sinon.
    -- Un index UNIQUE accepte plusieurs NULL : les appartenances terminées ne se
    -- gênent pas, mais deux appartenances en cours pour le même agent sont refusées.
    -- => « une seule équipe à la fois » garanti par la base elle-même.
    active_agent  INT AS (IF(date_leave IS NULL, Id_Agent, NULL)) STORED,
    CONSTRAINT uq_memberships_active_agent UNIQUE (active_agent),
    CONSTRAINT chk_memberships_dates CHECK (date_leave IS NULL OR date_leave >= date_join),
    -- RESTRICT obligatoire ici : MySQL interdit CASCADE / SET NULL sur une colonne
    -- utilisée par une colonne générée STORED
    CONSTRAINT fk_memberships_agents FOREIGN KEY (Id_Agent)
        REFERENCES Agents (Id_Agent) ON DELETE RESTRICT,
    CONSTRAINT fk_memberships_teams FOREIGN KEY (Id_Team)
        REFERENCES Teams (Id_Team) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Historique des sessions de formation (un agent peut repasser une formation)
CREATE TABLE Trainings (
    Id_Training  INT AUTO_INCREMENT PRIMARY KEY,
    date_beg     DATETIME   NOT NULL,
    date_end     DATETIME   NULL,            -- NULL = formation en cours
    is_passed    TINYINT(1) NULL,            -- NULL = en cours, 1 = réussie, 0 = échouée
    Id_Formation INT        NOT NULL,
    Id_Agent     INT        NOT NULL,
    CONSTRAINT chk_trainings_dates CHECK (date_end IS NULL OR date_end >= date_beg),
    CONSTRAINT fk_trainings_formations FOREIGN KEY (Id_Formation)
        REFERENCES Formations (Id_Formation) ON DELETE RESTRICT,
    CONSTRAINT fk_trainings_agents FOREIGN KEY (Id_Agent)
        REFERENCES Agents (Id_Agent) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- =====================================================================
--  COMPÉTENCES : trois sources
-- =====================================================================

-- 1) Compétences apportées par l'archétype
CREATE TABLE Archetypes_Abilities (
    Id_Archetype INT NOT NULL,
    Id_Ability   INT NOT NULL,
    PRIMARY KEY (Id_Archetype, Id_Ability),
    CONSTRAINT fk_aa_archetypes FOREIGN KEY (Id_Archetype)
        REFERENCES Archetypes (Id_Archetype) ON DELETE CASCADE,
    CONSTRAINT fk_aa_abilities FOREIGN KEY (Id_Ability)
        REFERENCES Abilities (Id_Ability) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- 2) Compétences délivrées par une formation (si elle est réussie)
CREATE TABLE Formations_Abilities (
    Id_Ability   INT NOT NULL,
    Id_Formation INT NOT NULL,
    PRIMARY KEY (Id_Ability, Id_Formation),
    CONSTRAINT fk_fa_abilities FOREIGN KEY (Id_Ability)
        REFERENCES Abilities (Id_Ability) ON DELETE RESTRICT,
    CONSTRAINT fk_fa_formations FOREIGN KEY (Id_Formation)
        REFERENCES Formations (Id_Formation) ON DELETE CASCADE
) ENGINE = InnoDB;

-- 3) Compétences acquises par l'expérience, hors archétype et hors formation
CREATE TABLE Abilities_Agents (
    Id_Agent   INT NOT NULL,
    Id_Ability INT NOT NULL,
    PRIMARY KEY (Id_Agent, Id_Ability),
    CONSTRAINT fk_ab_agents FOREIGN KEY (Id_Agent)
        REFERENCES Agents (Id_Agent) ON DELETE RESTRICT,
    CONSTRAINT fk_ab_abilities FOREIGN KEY (Id_Ability)
        REFERENCES Abilities (Id_Ability) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- =====================================================================
--  MENACES ET OPÉRATIONS
-- =====================================================================

CREATE TABLE Threats (
    Id_Threat    INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(50) NOT NULL,
    serial       VARCHAR(50) NOT NULL UNIQUE,
    created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    informations TEXT        NULL,
    Id_Tier      INT         NOT NULL,
    CONSTRAINT fk_threats_tiers FOREIGN KEY (Id_Tier)
        REFERENCES Tiers (Id_Tier) ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE Operations (
    Id_Operation INT AUTO_INCREMENT PRIMARY KEY,
    serial       VARCHAR(30) NOT NULL UNIQUE,
    code_name    VARCHAR(50) NOT NULL UNIQUE,
    date_beg     DATETIME    NOT NULL,
    date_end     DATETIME    NULL,           -- NULL = opération en cours, fin inconnue
    brief        TEXT        NULL,
    Id_Status    INT         NOT NULL,
    Id_User      INT         NOT NULL,       -- l'utilisateur qui a déclaré l'opération
    CONSTRAINT chk_operations_dates CHECK (date_end IS NULL OR date_end >= date_beg),
    CONSTRAINT fk_operations_status FOREIGN KEY (Id_Status)
        REFERENCES Status (Id_Status) ON DELETE RESTRICT,
    CONSTRAINT fk_operations_users FOREIGN KEY (Id_User)
        REFERENCES Users (Id_User) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Équipes affectées à l'opération (ce qui est planifié)
CREATE TABLE Operations_Teams (
    Id_Team      INT NOT NULL,
    Id_Operation INT NOT NULL,
    PRIMARY KEY (Id_Team, Id_Operation),
    CONSTRAINT fk_ot_teams FOREIGN KEY (Id_Team)
        REFERENCES Teams (Id_Team) ON DELETE RESTRICT,
    CONSTRAINT fk_ot_operations FOREIGN KEY (Id_Operation)
        REFERENCES Operations (Id_Operation) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Agents ayant réellement participé (ce qui s'est passé)
CREATE TABLE Operations_Agents (
    Id_Agent     INT NOT NULL,
    Id_Operation INT NOT NULL,
    PRIMARY KEY (Id_Agent, Id_Operation),
    CONSTRAINT fk_oag_agents FOREIGN KEY (Id_Agent)
        REFERENCES Agents (Id_Agent) ON DELETE RESTRICT,
    CONSTRAINT fk_oag_operations FOREIGN KEY (Id_Operation)
        REFERENCES Operations (Id_Operation) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE Operations_Missions (
    Id_Operation INT NOT NULL,
    Id_Mission   INT NOT NULL,
    PRIMARY KEY (Id_Operation, Id_Mission),
    CONSTRAINT fk_om_operations FOREIGN KEY (Id_Operation)
        REFERENCES Operations (Id_Operation) ON DELETE CASCADE,
    CONSTRAINT fk_om_missions FOREIGN KEY (Id_Mission)
        REFERENCES Missions (Id_Mission) ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE Operations_Locations (
    Id_Operation INT NOT NULL,
    Id_Location  INT NOT NULL,
    PRIMARY KEY (Id_Operation, Id_Location),
    CONSTRAINT fk_ol_operations FOREIGN KEY (Id_Operation)
        REFERENCES Operations (Id_Operation) ON DELETE CASCADE,
    CONSTRAINT fk_ol_locations FOREIGN KEY (Id_Location)
        REFERENCES Locations (Id_Location) ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE TABLE Operations_Threats (
    Id_Operation INT NOT NULL,
    Id_Threat    INT NOT NULL,
    PRIMARY KEY (Id_Operation, Id_Threat),
    CONSTRAINT fk_oth_operations FOREIGN KEY (Id_Operation)
        REFERENCES Operations (Id_Operation) ON DELETE CASCADE,
    CONSTRAINT fk_oth_threats FOREIGN KEY (Id_Threat)
        REFERENCES Threats (Id_Threat) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- Compétences requises pour l'opération
CREATE TABLE Operations_Abilities (
    Id_Ability   INT NOT NULL,
    Id_Operation INT NOT NULL,
    PRIMARY KEY (Id_Ability, Id_Operation),
    CONSTRAINT fk_oab_abilities FOREIGN KEY (Id_Ability)
        REFERENCES Abilities (Id_Ability) ON DELETE RESTRICT,
    CONSTRAINT fk_oab_operations FOREIGN KEY (Id_Operation)
        REFERENCES Operations (Id_Operation) ON DELETE CASCADE
) ENGINE = InnoDB;

-- =====================================================================
--  MÉDIAS : portraits (agents, menaces) et illustrations (opérations)
-- =====================================================================

-- Une ligne = une image, rattachée à UNE SEULE entité parmi les trois.
-- url : chemin relatif du fichier uploadé (ex. uploads/agents/3f9a....webp)
-- Une entité peut avoir plusieurs images (cardinalité 0,n) : par convention,
-- l'image principale est la plus récente (Id_Media le plus élevé).
CREATE TABLE Medias (
    Id_Media     INT AUTO_INCREMENT PRIMARY KEY,
    url          VARCHAR(255) NOT NULL UNIQUE,
    Id_Operation INT          NULL,
    Id_Threat    INT          NULL,
    Id_Agent     INT          NULL,
    -- Exactement une des trois clés étrangères doit être renseignée
    CONSTRAINT chk_medias_one_owner CHECK (
        (Id_Operation IS NOT NULL) + (Id_Threat IS NOT NULL) + (Id_Agent IS NOT NULL) = 1
    ),
    -- RESTRICT partout : MySQL interdit CASCADE / SET NULL sur une colonne utilisée
    -- dans un CHECK. Et c'est souhaitable : un CASCADE supprimerait la ligne mais
    -- laisserait le fichier orphelin sur le disque. L'application doit donc supprimer
    -- le fichier ET la ligne avant de supprimer une opération.
    CONSTRAINT fk_medias_operations FOREIGN KEY (Id_Operation)
        REFERENCES Operations (Id_Operation) ON DELETE RESTRICT,
    CONSTRAINT fk_medias_threats FOREIGN KEY (Id_Threat)
        REFERENCES Threats (Id_Threat) ON DELETE RESTRICT,
    CONSTRAINT fk_medias_agents FOREIGN KEY (Id_Agent)
        REFERENCES Agents (Id_Agent) ON DELETE RESTRICT
) ENGINE = InnoDB;

-- =====================================================================
--  VUES
-- =====================================================================

-- Équipe actuelle de chaque agent (évite de répéter « date_leave IS NULL » partout)
CREATE VIEW current_memberships AS
SELECT m.Id_Membership, m.date_join,
       a.Id_Agent, a.name AS agent,
       t.Id_Team, t.team_name
FROM Memberships m
JOIN Agents a ON a.Id_Agent = m.Id_Agent
JOIN Teams  t ON t.Id_Team  = m.Id_Team
WHERE m.date_leave IS NULL;

-- Toutes les compétences d'un agent, avec leur provenance.
-- Une même compétence peut apparaître deux fois si elle a deux sources :
-- utiliser DISTINCT pour la liste « à plat ».
CREATE VIEW agent_abilities AS
SELECT s.Id_Agent, ag.name AS agent, s.Id_Ability, ab.ability, s.source
FROM (
    SELECT a.Id_Agent, aa.Id_Ability, 'Archétype' AS source
    FROM Agents a
    JOIN Archetypes_Abilities aa ON aa.Id_Archetype = a.Id_Archetype

    UNION

    SELECT t.Id_Agent, fa.Id_Ability, 'Formation'
    FROM Trainings t
    JOIN Formations_Abilities fa ON fa.Id_Formation = t.Id_Formation
    WHERE t.is_passed = 1

    UNION

    SELECT Id_Agent, Id_Ability, 'Expérience'
    FROM Abilities_Agents
) AS s
JOIN Agents    ag ON ag.Id_Agent   = s.Id_Agent
JOIN Abilities ab ON ab.Id_Ability = s.Id_Ability;

-- =====================================================================
--  JEU DE DONNÉES
--  Les id sont fixés explicitement pour que les clés étrangères restent
--  lisibles (les commentaires indiquent à quoi elles correspondent).
-- =====================================================================

INSERT INTO Roles (Id_Role, role) VALUES
    (1, 'Administrateur'),
    (2, 'Manager');

-- Mot de passe de tous les comptes de démo : Agent2026!
INSERT INTO Users (Id_User, firstname, lastname, email, password, created_at, archived_at, Id_Role) VALUES
    (1, 'Nick',  'Fury',    'nick.fury@agentmanager.test',    '$2y$10$TbvhANXrdBvjDfV1Vaud4eLcwYxb2ZQfTT.nE5GFNFcwJ2PvN7jfW', '2024-01-02 09:00:00', NULL, 1),
    (2, 'Phil',  'Coulson', 'phil.coulson@agentmanager.test', '$2y$10$TbvhANXrdBvjDfV1Vaud4eLcwYxb2ZQfTT.nE5GFNFcwJ2PvN7jfW', '2024-01-05 09:00:00', NULL, 2),
    (3, 'Maria', 'Hill',    'maria.hill@agentmanager.test',   '$2y$10$TbvhANXrdBvjDfV1Vaud4eLcwYxb2ZQfTT.nE5GFNFcwJ2PvN7jfW', '2024-01-05 09:30:00', NULL, 2);

INSERT INTO Tiers (Id_Tier, tier, level) VALUES
    (1, 'God', 7), (2, 'A', 6), (3, 'B', 5), (4, 'C', 4), (5, 'D', 3), (6, 'E', 2), (7, 'F', 1);

INSERT INTO Abilities (Id_Ability, ability) VALUES
    (1,  'Vol'),               -- SuperDeplacement::vol()
    (2,  'Super vitesse'),     -- SuperVitesse::superVitesse()
    (3,  'Super force'),       -- SuperForce::superforce()
    (4,  'Grappin'),           -- Accessoire::grappin()
    (5,  'Lancer d''objet'),   -- Accessoire::lancerObjet()
    (6,  'Jeter un sort'),     -- Sort::jeterSort()
    (7,  'Sortilège'),         -- Sort::sortilege()
    (8,  'Pilotage'),          -- compétences « métier » apportées par
    (9,  'Infiltration'),      -- les formations ou l'expérience
    (10, 'Premiers secours'),
    (11, 'Négociation');

INSERT INTO Archetypes (Id_Archetype, archetype) VALUES
    (1, 'Superman'), (2, 'Thor'), (3, 'Flash'), (4, 'Spider-Man'),
    (5, 'Batman'), (6, 'Hulk'), (7, 'Constantine');

INSERT INTO Archetypes_Abilities (Id_Archetype, Id_Ability) VALUES
    (1, 1), (1, 2), (1, 3),   -- Superman : vol, super vitesse, super force
    (2, 3),                   -- Thor : super force
    (3, 2),                   -- Flash : super vitesse
    (4, 3), (4, 4), (4, 5),   -- Spider-Man : super force, grappin, lancer d'objet
    (5, 4), (5, 5),           -- Batman : grappin, lancer d'objet
    (6, 3),                   -- Hulk : super force
    (7, 6), (7, 7);           -- Constantine : jeter un sort, sortilège

INSERT INTO Formations (Id_Formation, formation) VALUES
    (1, 'Pilotage de Quinjet'),
    (2, 'Infiltration avancée'),
    (3, 'Secourisme de terrain'),
    (4, 'Négociation de crise');

INSERT INTO Formations_Abilities (Id_Ability, Id_Formation) VALUES
    (8, 1), (9, 2), (10, 3), (11, 4);

INSERT INTO Status (Id_Status, status) VALUES
    (1, 'Planifiée'), (2, 'En cours'), (3, 'Réussie'), (4, 'Échouée'), (5, 'Annulée');

INSERT INTO Missions (Id_Mission, mission) VALUES
    (1, 'Reconnaissance'), (2, 'Extraction'), (3, 'Protection'), (4, 'Neutralisation'), (5, 'Enquête');

INSERT INTO Locations (Id_Location, location) VALUES
    (1, 'Gotham City'), (2, 'Metropolis'), (3, 'New York'), (4, 'Asgard'), (5, 'Paris');

INSERT INTO Teams (Id_Team, team_name, created_at, Id_User) VALUES
    (1, 'Justice League', '2024-02-01 10:00:00', 2),   -- managée par Phil Coulson
    (2, 'Avengers',       '2024-02-01 11:00:00', 3),   -- managée par Maria Hill
    (3, 'Dark Squad',     '2025-06-15 14:00:00', 3);   -- managée par Maria Hill

INSERT INTO Agents (Id_Agent, name, dob, created_at, archived_at, Id_Archetype, Id_Tier) VALUES
    (1,  'Clark Kent',       '1986-06-18', '2024-02-10 09:00:00', NULL, 1, 1),
    (2,  'Kara Danvers',     '1999-09-22', '2025-01-20 09:00:00', NULL, 1, 2),
    (3,  'Thor Odinson',     NULL,         '2024-02-10 09:00:00', NULL, 2, 1),  -- ~1500 ans : hors plage DATE
    (4,  'Jane Foster',      '1988-03-02', '2024-03-01 09:00:00', '2025-06-30 18:00:00', 2, 3),  -- archivée
    (5,  'Barry Allen',      '1992-03-14', '2024-02-12 09:00:00', NULL, 3, 2),
    (6,  'Wally West',       '2001-08-07', '2025-09-01 09:00:00', NULL, 3, 4),
    (7,  'Peter Parker',     '2001-08-10', '2024-02-15 09:00:00', NULL, 4, 3),
    (8,  'Miles Morales',    '2008-02-01', '2025-06-20 09:00:00', NULL, 4, 5),
    (9,  'Bruce Wayne',      '1980-02-19', '2024-02-10 09:00:00', NULL, 5, 2),
    (10, 'Bruce Banner',     '1979-12-18', '2024-02-15 09:00:00', NULL, 6, 2),
    (11, 'John Constantine', '1975-05-10', '2025-06-20 09:00:00', NULL, 7, 3);

-- Historique des équipes
INSERT INTO Memberships (date_join, date_leave, Id_Agent, Id_Team) VALUES
    ('2024-02-15 09:00:00', NULL,                  1,  1),   -- Clark : Justice League
    ('2024-02-15 09:00:00', NULL,                  9,  1),   -- Bruce Wayne : Justice League
    ('2024-02-20 09:00:00', '2025-09-01 18:00:00', 5,  2),   -- Barry : d'abord Avengers...
    ('2025-09-02 09:00:00', NULL,                  5,  1),   -- ... puis Justice League
    ('2024-02-20 09:00:00', NULL,                  7,  2),   -- Peter : Avengers
    ('2024-02-20 09:00:00', NULL,                  10, 2),   -- Bruce Banner : Avengers
    ('2024-02-20 09:00:00', NULL,                  3,  2),   -- Thor : Avengers
    ('2024-03-05 09:00:00', '2025-06-30 18:00:00', 4,  2),   -- Jane : Avengers, jusqu'à son archivage
    ('2025-06-20 09:00:00', NULL,                  11, 3),   -- Constantine : Dark Squad
    ('2025-06-20 09:00:00', NULL,                  8,  3);   -- Miles : Dark Squad
    -- Kara Danvers et Wally West : sans équipe (disponibles)

-- Historique des formations
INSERT INTO Trainings (date_beg, date_end, is_passed, Id_Formation, Id_Agent) VALUES
    ('2025-03-03 09:00:00', '2025-03-14 17:00:00', 0,    1, 7),   -- Peter rate le pilotage...
    ('2025-10-06 09:00:00', '2025-10-17 17:00:00', 1,    1, 7),   -- ... et le réussit au second essai
    ('2024-05-13 09:00:00', '2024-05-24 17:00:00', 1,    2, 9),   -- Bruce Wayne : infiltration
    ('2025-07-01 09:00:00', '2025-07-04 17:00:00', 1,    4, 11),  -- Constantine : négociation
    ('2026-02-02 09:00:00', '2026-02-13 17:00:00', 1,    1, 2),   -- Kara : pilotage
    ('2026-10-19 09:00:00', NULL,                  NULL, 3, 6);   -- Wally : secourisme en cours

-- Compétences acquises par l'expérience
INSERT INTO Abilities_Agents (Id_Agent, Id_Ability) VALUES
    (1, 11),   -- Clark : négociation (des années de journalisme)
    (5, 10);   -- Barry : premiers secours (police scientifique)

INSERT INTO Threats (Id_Threat, name, serial, created_at, informations, Id_Tier) VALUES
    (1, 'Darkseid',         'THR-0001', '2024-02-01 08:00:00', 'Souverain d''Apokolips. Cherche l''équation d''anti-vie.', 1),
    (2, 'Thanos',           'THR-0002', '2024-02-01 08:00:00', 'Titan fou. Collectionne les Pierres d''Infinité.', 1),
    (3, 'Le Joker',         'THR-0003', '2024-03-12 08:00:00', 'Imprévisible. Ne jamais négocier seul.', 3),
    (4, 'Docteur Octopus',  'THR-0004', '2025-04-01 08:00:00', 'Quatre bras mécaniques. Scientifique de haut niveau.', 4);

INSERT INTO Operations (Id_Operation, serial, code_name, date_beg, date_end, brief, Id_Status, Id_User) VALUES
    (1, 'OP-2025-001', 'Aube Rouge',     '2025-04-10 06:00:00', '2025-04-12 22:00:00',
        'Neutraliser le Docteur Octopus avant son attaque sur le réseau électrique.', 3, 3),
    (2, 'OP-2025-014', 'Chevalier Noir', '2025-11-01 20:00:00', '2025-11-03 04:00:00',
        'Enquêter sur une série d''enlèvements à Gotham.', 3, 2),
    (3, 'OP-2026-007', 'Crépuscule',     '2026-11-05 05:00:00', NULL,
        'Protéger New York et Asgard des forces de Thanos.', 2, 3),
    (4, 'OP-2026-009', 'Cendres',        '2026-12-01 08:00:00', '2026-12-03 20:00:00',
        'Reconnaissance aérienne de Metropolis : activité inhabituelle d''Apokolips.', 1, 2);

INSERT INTO Operations_Teams (Id_Team, Id_Operation) VALUES
    (2, 1),   -- Aube Rouge : Avengers
    (1, 2),   -- Chevalier Noir : Justice League
    (2, 3);   -- Crépuscule : Avengers
    -- Cendres : pas encore d'équipe affectée

-- Participation réelle : copie de l'équipe au lancement, puis ajustements
INSERT INTO Operations_Agents (Id_Agent, Id_Operation) VALUES
    (5, 1), (7, 1), (10, 1),   -- Aube Rouge : Barry était encore chez les Avengers
    (1, 2), (9, 2), (5, 2),    -- Chevalier Noir : Barry, désormais Justice League
    (7, 3), (10, 3), (3, 3);   -- Crépuscule

INSERT INTO Operations_Missions (Id_Operation, Id_Mission) VALUES
    (1, 4), (2, 5), (2, 4), (3, 3), (4, 1);

INSERT INTO Operations_Locations (Id_Operation, Id_Location) VALUES
    (1, 3), (2, 1), (3, 3), (3, 4), (4, 2);

INSERT INTO Operations_Threats (Id_Operation, Id_Threat) VALUES
    (1, 4), (2, 3), (3, 2), (4, 1);

INSERT INTO Operations_Abilities (Id_Ability, Id_Operation) VALUES
    (3, 1),           -- Aube Rouge : super force
    (4, 2),           -- Chevalier Noir : grappin
    (3, 3), (1, 3),   -- Crépuscule : super force, vol
    (1, 4), (8, 4);   -- Cendres : vol, pilotage

-- Médias : aucun fichier fourni dans le jeu de démo.
-- L'application affiche un visuel de remplacement tant qu'aucune image n'est liée.
