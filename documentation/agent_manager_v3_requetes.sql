-- =====================================================================
--  Agent Manager v3 — requêtes d'exemple
--  À exécuter après agent_manager_v3.sql
-- =====================================================================

USE agent_manager_v3;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1. Équipe actuelle de chaque agent en service (y compris sans équipe)
-- ---------------------------------------------------------------------
SELECT a.name AS agent, COALESCE(cm.team_name, '— sans équipe —') AS equipe
FROM Agents a
LEFT JOIN current_memberships cm ON cm.Id_Agent = a.Id_Agent
WHERE a.archived_at IS NULL
ORDER BY equipe, agent;

-- ---------------------------------------------------------------------
--  2. Parcours d'un agent dans les équipes
-- ---------------------------------------------------------------------
SELECT t.team_name AS equipe, m.date_join AS arrivee, m.date_leave AS depart
FROM Memberships m
JOIN Teams t ON t.Id_Team = m.Id_Team
WHERE m.Id_Agent = (SELECT Id_Agent FROM Agents WHERE name = 'Barry Allen')
ORDER BY m.date_join;

-- ---------------------------------------------------------------------
--  3. Dans quelle équipe était chaque participant AU MOMENT de l'opération ?
--     C'est la question à laquelle l'historisation permet de répondre.
-- ---------------------------------------------------------------------
SELECT o.code_name AS operation, a.name AS agent, t.team_name AS equipe_a_l_epoque
FROM Operations_Agents oa
JOIN Operations o ON o.Id_Operation = oa.Id_Operation
JOIN Agents     a ON a.Id_Agent     = oa.Id_Agent
LEFT JOIN Memberships m
       ON m.Id_Agent = oa.Id_Agent
      AND m.date_join <= o.date_beg
      AND (m.date_leave IS NULL OR m.date_leave >= o.date_beg)
LEFT JOIN Teams t ON t.Id_Team = m.Id_Team
ORDER BY o.date_beg, a.name;

-- ---------------------------------------------------------------------
--  4. Compétences d'un agent avec leur provenance
-- ---------------------------------------------------------------------
SELECT ability, source
FROM agent_abilities
WHERE agent = 'Peter Parker'
ORDER BY ability;

-- ---------------------------------------------------------------------
--  5. Historique des formations d'un agent (échecs compris)
-- ---------------------------------------------------------------------
SELECT f.formation, t.date_beg, t.date_end,
       CASE
           WHEN t.is_passed IS NULL THEN 'En cours'
           WHEN t.is_passed = 1     THEN 'Réussie'
           ELSE 'Échouée'
       END AS resultat
FROM Trainings t
JOIN Formations f ON f.Id_Formation = t.Id_Formation
WHERE t.Id_Agent = (SELECT Id_Agent FROM Agents WHERE name = 'Peter Parker')
ORDER BY t.date_beg;

-- ---------------------------------------------------------------------
--  6. STAFFING : agents en service, disponibles, qui possèdent TOUTES
--     les compétences requises par une opération.
--     (division relationnelle : on compte les compétences requises possédées
--      et on compare au nombre total de compétences requises)
-- ---------------------------------------------------------------------
SET @op = (SELECT Id_Operation FROM Operations WHERE code_name = 'Cendres');

SELECT a.name AS agent, GROUP_CONCAT(DISTINCT aa.ability ORDER BY aa.ability) AS competences_requises_possedees
FROM Agents a
JOIN agent_abilities aa      ON aa.Id_Agent   = a.Id_Agent
JOIN Operations_Abilities oa ON oa.Id_Ability = aa.Id_Ability
                            AND oa.Id_Operation = @op
WHERE a.archived_at IS NULL
  -- disponible : aucune autre opération (non annulée) qui chevauche les dates
  AND NOT EXISTS (
      SELECT 1
      FROM Operations_Agents oag
      JOIN Operations other  ON other.Id_Operation = oag.Id_Operation
      JOIN Operations target ON target.Id_Operation = @op
      JOIN Status s          ON s.Id_Status = other.Id_Status
      WHERE oag.Id_Agent = a.Id_Agent
        AND other.Id_Operation <> target.Id_Operation
        AND s.status <> 'Annulée'
        AND other.date_beg < COALESCE(target.date_end, '9999-12-31')
        AND COALESCE(other.date_end, '9999-12-31') > target.date_beg
  )
GROUP BY a.Id_Agent, a.name
HAVING COUNT(DISTINCT aa.Id_Ability) = (
    SELECT COUNT(*) FROM Operations_Abilities WHERE Id_Operation = @op
);

-- ---------------------------------------------------------------------
--  7. Test de la contrainte « une équipe à la fois »
--     Clark Kent est déjà dans la Justice League (appartenance en cours) :
--     cette insertion doit échouer avec une erreur de doublon sur active_agent.
-- ---------------------------------------------------------------------
-- INSERT INTO Memberships (date_join, Id_Agent, Id_Team) VALUES (NOW(), 1, 2);

-- Pour le changer d'équipe, on clôt d'abord l'appartenance en cours :
-- UPDATE Memberships SET date_leave = NOW() WHERE Id_Agent = 1 AND date_leave IS NULL;
-- INSERT INTO Memberships (date_join, Id_Agent, Id_Team) VALUES (NOW(), 1, 2);

-- ---------------------------------------------------------------------
--  8. Image principale de chaque agent en service (la plus récente)
--     NULL = pas d'image : l'application affiche un visuel de remplacement
-- ---------------------------------------------------------------------
SELECT a.name AS agent, m.url AS portrait
FROM Agents a
LEFT JOIN Medias m ON m.Id_Media = (
    SELECT MAX(Id_Media) FROM Medias WHERE Id_Agent = a.Id_Agent
)
WHERE a.archived_at IS NULL
ORDER BY a.name;
