#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Script de recette d'Agent Manager : rejoue les parcours du cahier des charges.
#
# ATTENTION : la base agent_manager_v3 est ré-importée plusieurs fois
# (DROP DATABASE). Ne pas lancer sur une base contenant des données à garder.
#
# Prérequis : serveur lancé (php -S localhost:8000 -t public), client mysql,
#             curl, et PHP avec l'extension dom pour la vérification d'accessibilité.
#
# Usage :  bash tests/smoke.sh
# Variables facultatives :
#   BASE=http://localhost:8000   MYSQL=mysql   DB_USER=root   DB_PASSWORD=root
#   SQL_FILE=../agent_manager_v3.sql          PHP=php
# ---------------------------------------------------------------------------
cd "$(dirname "$0")/.." || exit 1
source tests/lib.sh
export BASE MYSQL DB_USER DB_PASSWORD SQL_FILE
sql() { "${MYSQL:-mysql}" -u"${DB_USER:-root}" -p"${DB_PASSWORD:-root}" -N --default-character-set=utf8mb4 agent_manager_v3 -e "$1" 2>/dev/null | tr '\t' ' '; }

reset_db

echo "== Socle et authentification"
check "/ sans session → redirection vers /login" "303" "$(status anon /)"
check "route inconnue → 404" "404" "$(status anon /nimporte/quoi)"
check "GET /logout → 405 (aucune action en GET)" "405" "$(status anon /logout)"
check "POST sans jeton CSRF → 403" "403" "$(curl -s -o /dev/null -w '%{http_code}' -X POST -d 'email=x' "$BASE/login")"
check "mauvais mot de passe → 422" "422 " "$(post intrus /login --data-urlencode email=nick.fury@agentmanager.test --data-urlencode password=faux)"
check "connexion de Nick Fury (Administrateur)" "303 $BASE/" "$(login admin nick.fury@agentmanager.test)"
check "connexion de Phil Coulson (Manager)" "303 $BASE/" "$(login coulson phil.coulson@agentmanager.test)"
check "connexion de Maria Hill (Manager)" "303 $BASE/" "$(login hill maria.hill@agentmanager.test)"
check "Manager sur /admin → 403" "403" "$(status hill /admin)"
check "Manager archive un agent → 403" "403 " "$(post hill /agents/5/archive)"
check "compte archivé déconnecté à la requête suivante" "303" "$(sql 'UPDATE Users SET archived_at = NOW() WHERE Id_User = 2'; status coulson /)"
check "compte archivé refusé à la connexion" "422 " "$(login coulson2 phil.coulson@agentmanager.test)"
sql 'UPDATE Users SET archived_at = NULL WHERE Id_User = 2' >/dev/null
login coulson phil.coulson@agentmanager.test >/dev/null
login tmp maria.hill@agentmanager.test >/dev/null
check "déconnexion (POST)" "303 $BASE/login" "$(post tmp /logout)"
check "session détruite après déconnexion" "303" "$(status tmp /)"

echo "== Parcours : déplacer Barry Allen (Justice League → Avengers)"
check "Hill ne peut pas prendre Barry à Coulson" "303 $BASE/agents/5" "$(post hill /agents/5/team --data-urlencode team=2)"
contains "message : libération préalable" "son manager doit d'abord le libérer" "$(get hill /agents/5)"
check "Coulson libère Barry" "303 $BASE/teams/1" "$(post coulson /teams/1/members/5/remove)"
check "Hill intègre Barry aux Avengers" "303 $BASE/teams/2" "$(post hill /teams/2/members --data-urlencode agent=5)"
check "une seule appartenance en cours, chez les Avengers" "2" "$(sql 'SELECT Id_Team FROM Memberships WHERE Id_Agent = 5 AND date_leave IS NULL')"
contains "historique complet sur le dossier" "Justice League" "$(get hill /agents/5)"

echo "== Parcours : archiver une équipe (Dark Squad)"
check "appartenances en cours avant" "2" "$(sql 'SELECT COUNT(*) FROM Memberships WHERE Id_Team = 3 AND date_leave IS NULL')"
check "l'Administrateur archive Dark Squad" "303 $BASE/teams/3" "$(post admin /teams/3/archive)"
contains "message de clôture" "2 appartenance(s) en cours ont été closes" "$(get admin /teams/3)"
check "appartenances closes en base" "0" "$(sql 'SELECT COUNT(*) FROM Memberships WHERE Id_Team = 3 AND date_leave IS NULL')"
absent "équipe absente de la liste" "Dark Squad" "$(get hill /teams)"

PASS_CORE=$PASS
FAIL_CORE=$FAIL

echo
echo "################ tests/operations.sh ################"
reset_db
bash tests/operations.sh
OPS=$?

echo
echo "################ tests/medias.sh ################"
reset_db
bash tests/medias.sh
MEDIAS=$?

echo
echo "################ Accessibilité structurelle ################"
reset_db
login admin nick.fury@agentmanager.test >/dev/null
PAGES="$JARS/pages"
mkdir -p "$PAGES"
curl -s "$BASE/login" > "$PAGES/login.html"
for path in / /agents /agents/new /agents/5 /agents/4 /agents/5/abilities /agents/5/trainings/new /trainings/1/edit \
            /teams /teams/1 /teams/new /operations /operations/new /operations/3 /operations/3/edit \
            /threats /threats/1 /threats/new /admin /admin/users /admin/users/new /admin/tiers /admin/archetypes /admin/statuses; do
    name=$(sed 's#^/##; s#/#_#g' <<<"$path")
    get admin "$path" > "$PAGES/${name:-dashboard}.html"
done
if command -v cygpath >/dev/null; then PAGES_ARG=$(cygpath -m "$PAGES"); else PAGES_ARG="$PAGES"; fi
"${PHP:-php}" tests/a11y_check.php "$PAGES_ARG"/*.html
A11Y=$?

reset_db

echo
echo "======================================================"
echo "Socle et parcours : $PASS_CORE réussi(s), $FAIL_CORE échec(s)"
echo "Opérations        : $([[ $OPS -eq 0 ]] && echo OK || echo ÉCHEC)"
echo "Médias            : $([[ $MEDIAS -eq 0 ]] && echo OK || echo ÉCHEC)"
echo "Accessibilité     : $([[ $A11Y -eq 0 ]] && echo OK || echo ÉCHEC)"
echo "Base de données ré-importée dans son état initial."
rm -rf "$JARS"
[[ $FAIL_CORE -eq 0 && $OPS -eq 0 && $MEDIAS -eq 0 && $A11Y -eq 0 ]]
