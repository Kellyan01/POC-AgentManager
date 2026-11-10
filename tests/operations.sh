#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Tests de l'étape 8 : opérations, disponibilité, staffing, avertissement de tier.
# Prérequis : base fraîchement importée, serveur sur $BASE.
# Usage : bash tests/operations.sh
# ---------------------------------------------------------------------------
cd "$(dirname "$0")/.." || exit 1
source tests/lib.sh
MYSQL="${MYSQL:-mysql}"
sql() { "$MYSQL" -uroot -p"${DB_PASSWORD:-root}" -N --default-character-set=utf8mb4 agent_manager_v3 -e "$1" 2>/dev/null | tr '\t' ' '; }

login admin nick.fury@agentmanager.test >/dev/null
login hill maria.hill@agentmanager.test >/dev/null
login coulson phil.coulson@agentmanager.test >/dev/null

echo "== Liste"
P=$(get hill /operations)
contains "4 opérations" "4 opération(s)" "$P"
contains "voyant + libellé" 'status--active">En cours' "$P"
P=$(get hill '/operations?status=2')
contains "filtre En cours : Crépuscule" "Crépuscule" "$P"
absent "filtre En cours : pas Cendres" "Cendres" "$P"

echo "== Staffing sur Cendres : seule Kara Danvers"
STAFF=$(get hill /operations/4 | sed -n '/op-staffing/,/<\/section>/p')
contains "suggestion : Kara Danvers" "Kara Danvers" "$STAFF"
check "une seule suggestion" "1" "$(grep -c 'href="/agents/' <<<"$STAFF")"
contains "compétences requises possédées" "Pilotage, Vol" "$STAFF"
absent "Hill (non déclarante) : pas de bouton Ajouter" "aux participants</span>" "$STAFF"
contains "Coulson (déclarant) : bouton Ajouter" "Kara Danvers aux participants" "$(get coulson /operations/4)"

echo "== Action interdite à un Manager"
check "Hill modifie Cendres (déclarée par Coulson) → 403" "403" "$(status hill /operations/4/edit)"
check "Hill ajoute un agent à Cendres → 403" "403 " "$(post hill /operations/4/agents --data-urlencode agent=2)"
check "aucun participant ajouté (BDD)" "0" "$(sql 'SELECT COUNT(*) FROM Operations_Agents WHERE Id_Operation=4')"
check "Coulson ajoute Kara depuis le staffing" "303 $BASE/operations/4" "$(post coulson /operations/4/agents --data-urlencode agent=2)"
P=$(get coulson /operations/4)
contains "Kara participante" "Kara Danvers ajouté aux participants" "$P"
contains "plus de suggestion ensuite" "Aucun agent disponible" "$P"

echo "== Équipe à l'époque (requête 3) et avertissement de tier"
contains "Aube Rouge : Barry chez les Avengers à l'époque" "Avengers" "$(get hill /operations/1 | grep -A8 'Barry Allen')"
contains "Chevalier Noir : Barry en Justice League" "Justice League" "$(get hill /operations/2 | grep -A8 'Barry Allen')"
P=$(get hill /agents/5)
contains "dossier Barry : Aube Rouge" "Aube Rouge" "$P"
contains "dossier Barry : Chevalier Noir" "Chevalier Noir" "$P"
P=$(get hill /operations/3)
contains "Crépuscule : avertissement (tier God)" "2 participant(s) ont un tier inférieur à celui de la menace la plus dangereuse (tier God)" "$P"
check "Crépuscule : mention sur 2 lignes" "2" "$(grep -c 'warning-text">Tier inférieur' <<<"$P")"

echo "== Créer une opération avec missions et lieux"
check "sans mission ni lieu → 422" "422 " "$(post hill /operations --data-urlencode 'code_name=Tempête' --data-urlencode 'date_beg=2026-12-02T08:00' --data-urlencode status=1)"
B=$(last_body)
contains "erreur missions" "Cochez au moins un type de mission" "$B"
contains "erreur lieux" "Cochez au moins un lieu" "$B"
contains "nom de code conservé" 'value="Tempête"' "$B"
contains "fieldset relié à son erreur" 'aria-describedby="f-missions-error"' "$B"
check "fin avant début → 422" "422 " "$(post hill /operations --data-urlencode 'code_name=Tempête' --data-urlencode 'date_beg=2026-12-02T08:00' --data-urlencode 'date_end=2026-12-01T08:00' --data-urlencode status=1 --data-urlencode 'missions[]=1' --data-urlencode 'locations[]=2')"
check "nom de code déjà pris → 422" "422 " "$(post hill /operations --data-urlencode 'code_name=Cendres' --data-urlencode 'date_beg=2026-12-02T08:00' --data-urlencode status=1 --data-urlencode 'missions[]=1' --data-urlencode 'locations[]=2')"
R=$(post hill /operations --data-urlencode 'code_name=Tempête' --data-urlencode 'date_beg=2026-12-02T08:00' --data-urlencode 'date_end=2026-12-05T20:00' --data-urlencode status=1 --data-urlencode 'brief=Intercepter un convoi.' --data-urlencode 'missions[]=1' --data-urlencode 'missions[]=4' --data-urlencode 'locations[]=2' --data-urlencode 'threats[]=1' --data-urlencode 'abilities[]=1')
OP=$(grep -o '[0-9]*$' <<<"$R")
check "création → 303" "303" "${R%% *}"
P=$(get hill "/operations/$OP")
contains "numéro OP-2026-010" "OP-2026-010" "$P"
contains "déclarante : Maria Hill" "Maria Hill" "$P"
contains "mission Neutralisation" "Neutralisation" "$P"
contains "lieu Metropolis" "Metropolis" "$P"
check "liaisons en base (2 missions, 1 lieu)" "2 1" "$(sql "SELECT (SELECT COUNT(*) FROM Operations_Missions WHERE Id_Operation=$OP), (SELECT COUNT(*) FROM Operations_Locations WHERE Id_Operation=$OP)")"

echo "== Affecter une équipe et copier ses membres"
check "affecter la Justice League" "303 $BASE/operations/$OP" "$(post hill "/operations/$OP/teams" --data-urlencode team=1)"
contains "3 membres copiés" "3 membre(s) ajouté(s) aux participants : Barry Allen, Bruce Wayne, Clark Kent" "$(get hill "/operations/$OP")"
check "3 participants en base" "3" "$(sql "SELECT COUNT(*) FROM Operations_Agents WHERE Id_Operation=$OP")"
check "affecter les Avengers (tous engagés sur Crépuscule)" "303 $BASE/operations/$OP" "$(post hill "/operations/$OP/teams" --data-urlencode team=2)"
P=$(get hill "/operations/$OP")
contains "aucun membre ajouté" "Aucun membre ajouté aux participants" "$P"
contains "membre écarté et signalé" "Peter Parker n'a pas été ajouté : déjà engagé sur Crépuscule" "$P"
check "équipe incomplète : toujours 3 participants" "3" "$(sql "SELECT COUNT(*) FROM Operations_Agents WHERE Id_Operation=$OP")"
check "Avengers bien affectés (BDD)" "1" "$(sql "SELECT COUNT(*) FROM Operations_Teams WHERE Id_Operation=$OP AND Id_Team=2")"
check "affecter deux fois la même équipe" "303 $BASE/operations/$OP" "$(post hill "/operations/$OP/teams" --data-urlencode team=1)"
contains "message : déjà affectée" "déjà affectée" "$(get hill "/operations/$OP")"

echo "== Disponibilité (règle 8)"
check "Kara (Cendres 01-03/12) vers Tempête (02-05/12)" "303 $BASE/operations/$OP" "$(post hill "/operations/$OP/agents" --data-urlencode agent=2)"
contains "conflit bloqué avec message explicite" "Kara Danvers est indisponible : déjà engagé sur Cendres, dont les dates chevauchent celles de Tempête" "$(get hill "/operations/$OP")"
check "Coulson annule Cendres" "303 $BASE/operations/4" "$(post coulson /operations/4 --data-urlencode 'code_name=Cendres' --data-urlencode 'date_beg=2026-12-01T08:00' --data-urlencode 'date_end=2026-12-03T20:00' --data-urlencode status=5 --data-urlencode 'missions[]=1' --data-urlencode 'locations[]=2' --data-urlencode 'threats[]=1' --data-urlencode 'abilities[]=1' --data-urlencode 'abilities[]=8')"
check "opération annulée ignorée : Kara ajoutée" "303 $BASE/operations/$OP" "$(post hill "/operations/$OP/agents" --data-urlencode agent=2)"
contains "flash ajout Kara" "Kara Danvers ajouté aux participants" "$(get hill "/operations/$OP")"
check "agent archivé (Jane) refusé" "303 $BASE/operations/$OP" "$(post hill "/operations/$OP/agents" --data-urlencode agent=4)"
contains "message agent archivé" "Jane Foster est archivé" "$(get hill "/operations/$OP")"

echo "== Retrait d'équipe et de participant"
check "retirer la Justice League" "303 $BASE/operations/$OP" "$(post hill "/operations/$OP/teams/1/remove")"
check "participants conservés (4)" "4" "$(sql "SELECT COUNT(*) FROM Operations_Agents WHERE Id_Operation=$OP")"
check "retirer Clark Kent" "303 $BASE/operations/$OP" "$(post hill "/operations/$OP/agents/1/remove")"
check "3 participants" "3" "$(sql "SELECT COUNT(*) FROM Operations_Agents WHERE Id_Operation=$OP")"
check "archiver Dark Squad" "303 $BASE/teams/3" "$(post admin /teams/3/archive)"
check "affecter Dark Squad archivée" "303 $BASE/operations/$OP" "$(post admin "/operations/$OP/teams" --data-urlencode team=3)"
contains "message équipe archivée" "est archivée : elle ne peut plus être affectée" "$(get admin "/operations/$OP")"

summary
