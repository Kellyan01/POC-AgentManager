#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Tests de l'étape 9 : upload et suppression des médias, suppression des
# opérations et des menaces (fichiers compris).
# Prérequis : base fraîchement importée, serveur sur $BASE.
# Usage : bash tests/medias.sh
# ---------------------------------------------------------------------------
cd "$(dirname "$0")/.." || exit 1
source tests/lib.sh
MYSQL="${MYSQL:-mysql}"
sql() { "$MYSQL" -uroot -p"${DB_PASSWORD:-root}" -N --default-character-set=utf8mb4 agent_manager_v3 -e "$1" 2>/dev/null | tr '\t' ' '; }

# Petites images de test (6 x 8 pixels), générées avec GD puis encodées en base64.
FIX="$JARS/fixtures"
mkdir -p "$FIX"
base64 -d > "$FIX/portrait.jpg" <<'B64'
/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgACAAGAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8Av0UUVxn58f/Z
B64
base64 -d > "$FIX/portrait.png" <<'B64'
iVBORw0KGgoAAAANSUhEUgAAAAYAAAAICAIAAABVpBlvAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFElEQVQImWPUOFHBgAqYGDAA/YUA1/gBeDIEFWgAAAAASUVORK5CYII=
B64
base64 -d > "$FIX/portrait.webp" <<'B64'
UklGRjgAAABXRUJQVlA4ICwAAABwAQCdASoGAAgAAUAmJaACdAF1AAD+4CV1xc2RFH/27P//bs//9uz/q4AAAA==
B64
printf 'Ceci est un simple texte.\n' > "$FIX/fake.jpg"
# Polyglotte : en-tête JPEG suivi de code PHP (finfo peut y voir un JPEG, getimagesize non).
{ printf '\xff\xd8\xff\xe0\x00\x10JFIF\x00'; printf '<?php echo "pwned"; ?>'; } > "$FIX/polyglot.jpg"
head -c 2621440 /dev/urandom > "$FIX/big.jpg"   # 2,5 Mo

# upload PROFIL CHEMIN FICHIER [TYPE_ANNONCE] [NOM_ANNONCE] : envoi multipart avec jeton CSRF.
upload() {
    local csrf file="$3"
    # Sous Windows (Git Bash), curl.exe attend un chemin Windows : /tmp/... → C:/Users/.../Temp/...
    command -v cygpath >/dev/null && file=$(cygpath -m "$3")
    csrf=$(token "$1")
    curl -s -o "$JARS/last_body" -w '%{http_code} %{redirect_url}' -c "$JARS/$1" -b "$JARS/$1" \
        -F "_csrf=$csrf" -F "image=@$file;type=${4:-image/jpeg};filename=${5:-$(basename "$3")}" "$BASE$2"
}
aside() { sed -n '/<aside aria-label="Portrait">/,/<\/aside>/p'; }

login admin nick.fury@agentmanager.test >/dev/null
login hill maria.hill@agentmanager.test >/dev/null
login coulson phil.coulson@agentmanager.test >/dev/null

echo "== Portrait d'agent : remplace le visuel par défaut"
contains "avant : visuel de remplacement" "Portrait de Barry Allen : aucun visuel disponible" "$(get hill /agents/5 | aside)"
check "upload JPEG (Hill)" "303 $BASE/agents/5" "$(upload hill /agents/5/medias "$FIX/portrait.jpg")"
P=$(get hill /agents/5)
contains "flash d'upload" "Image enregistrée" "$P"
JPG=$(grep -o 'uploads/agents/[a-f0-9]\{32\}\.jpg' <<<"$P" | head -1)
check "nom aléatoire + extension .jpg" "1" "$(grep -c . <<<"$JPG")"
contains "image affichée avec son alt" "src=\"/$JPG\" alt=\"Portrait de Barry Allen\"" "$P"
absent "après : plus de visuel de remplacement" "aucun visuel disponible" "$(aside <<<"$P")"
check "fichier présent sur le disque" "yes" "$([[ -f "public/$JPG" ]] && echo yes || echo no)"
check "image servie" "200" "$(status hill "/$JPG")"
check "ligne Medias (agent 5)" "1" "$(sql "SELECT COUNT(*) FROM Medias WHERE Id_Agent=5 AND url='$JPG'")"
contains "vignette dans la liste des agents" "src=\"/$JPG\"" "$(get hill /agents)"

echo "== Image principale = la plus récente, galerie pour les autres"
check "upload PNG nommé evil.php" "303 $BASE/agents/5" "$(upload hill /agents/5/medias "$FIX/portrait.png" image/png evil.php)"
P=$(get hill /agents/5)
PNG=$(grep -o 'uploads/agents/[a-f0-9]\{32\}\.png' <<<"$P" | head -1)
check "extension issue du type réel (.png, pas .php)" "1" "$(grep -c . <<<"$PNG")"
check "aucun fichier .php dans uploads" "0" "$(find public/uploads -name '*.php' | wc -l | tr -d ' ')"
contains "PNG devenu principal" "src=\"/$PNG\" alt=\"Portrait de Barry Allen\"" "$P"
contains "JPEG dans la galerie" "src=\"/$JPG\" alt=\"Portrait de Barry Allen (archive 1)\"" "$P"

echo "== Fichiers refusés"
check "faux JPEG (texte)" "303 $BASE/agents/5" "$(upload hill /agents/5/medias "$FIX/fake.jpg")"
contains "message format" "Format non autorisé" "$(get hill /agents/5)"
check "polyglotte JPEG + PHP" "303 $BASE/agents/5" "$(upload hill /agents/5/medias "$FIX/polyglot.jpg")"
P=$(get hill /agents/5)
if grep -qF "Format non autorisé" <<<"$P" || grep -qF "pas une image valide" <<<"$(decode <<<"$P")"; then check "polyglotte refusé" yes yes; else check "polyglotte refusé" yes no; fi
check "fichier de 2,5 Mo" "303 $BASE/agents/5" "$(upload hill /agents/5/medias "$FIX/big.jpg")"
contains "message taille" "Image trop volumineuse : 2 Mo maximum" "$(get hill /agents/5)"
CS=$(token hill)
check "envoi sans fichier" "303 $BASE/agents/5" "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$JARS/hill" -c "$JARS/hill" -F "_csrf=$CS" "$BASE/agents/5/medias")"
contains "message fichier manquant" "Choisissez une image" "$(get hill /agents/5)"
check "toujours 2 images pour Barry" "2" "$(sql 'SELECT COUNT(*) FROM Medias WHERE Id_Agent=5')"
check "agent archivé (Jane) : upload refusé" "303 $BASE/agents/4" "$(upload hill /agents/4/medias "$FIX/portrait.jpg")"
JPGFILE="$FIX/portrait.jpg"; command -v cygpath >/dev/null && JPGFILE=$(cygpath -m "$JPGFILE")
check "upload sans jeton CSRF → 403" "403" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JARS/hill" -F "image=@$JPGFILE;type=image/jpeg" "$BASE/agents/5/medias")"

echo "== Suppression d'un média"
PNGID=$(sql "SELECT Id_Media FROM Medias WHERE url='$PNG'")
check "supprimer l'image principale" "303 $BASE/agents/5" "$(post hill "/medias/$PNGID/delete")"
check "ligne supprimée" "0" "$(sql "SELECT COUNT(*) FROM Medias WHERE Id_Media=$PNGID")"
check "fichier supprimé du disque" "no" "$([[ -f "public/$PNG" ]] && echo yes || echo no)"
contains "le JPEG redevient principal" "src=\"/$JPG\" alt=\"Portrait de Barry Allen\"" "$(get hill /agents/5)"

echo "== Menaces et opérations"
check "portrait WebP de Darkseid" "303 $BASE/threats/1" "$(upload hill /threats/1/medias "$FIX/portrait.webp" image/webp)"
contains "portrait de menace affiché" "alt=\"Portrait de Darkseid\"" "$(get hill /threats/1)"
check "Hill illustre Cendres (déclarée par Coulson) → 403" "403 " "$(upload hill /operations/4/medias "$FIX/portrait.jpg")"
check "Coulson illustre Cendres" "303 $BASE/operations/4" "$(upload coulson /operations/4/medias "$FIX/portrait.jpg")"
contains "alt de l'illustration" "alt=\"Illustration de l'opération Cendres\"" "$(get coulson /operations/4)"

echo "== Supprimer une opération : médias et fichiers d'abord"
OPFILE=$(sql 'SELECT url FROM Medias WHERE Id_Operation=4')
check "Hill supprime Cendres → 403" "403 " "$(post hill /operations/4/delete)"
check "Coulson supprime Cendres" "303 $BASE/operations" "$(post coulson /operations/4/delete)"
check "opération supprimée" "0" "$(sql 'SELECT COUNT(*) FROM Operations WHERE Id_Operation=4')"
check "médias supprimés" "0" "$(sql 'SELECT COUNT(*) FROM Medias WHERE Id_Operation=4')"
check "liaisons supprimées (CASCADE)" "0" "$(sql 'SELECT COUNT(*) FROM Operations_Abilities WHERE Id_Operation=4')"
check "fichier de l'illustration supprimé" "no" "$([[ -f "public/$OPFILE" ]] && echo yes || echo no)"

echo "== Supprimer une menace (Admin, si aucune opération ne la vise)"
check "Darkseid n'est plus visée (Cendres supprimée)" "0" "$(sql 'SELECT COUNT(*) FROM Operations_Threats WHERE Id_Threat=1')"
check "Thanos (visé par Crépuscule) : refus" "303 $BASE/threats/2" "$(post admin /threats/2/delete)"
contains "message de refus" "visée par au moins une opération" "$(get admin /threats/2)"
TFILE=$(sql 'SELECT url FROM Medias WHERE Id_Threat=1')
check "Manager → 403" "403 " "$(post hill /threats/1/delete)"
check "Admin supprime Darkseid (avec portrait)" "303 $BASE/threats" "$(post admin /threats/1/delete)"
check "menace et médias supprimés" "0 0" "$(sql 'SELECT (SELECT COUNT(*) FROM Threats WHERE Id_Threat=1), (SELECT COUNT(*) FROM Medias WHERE Id_Threat=1)')"
check "fichier du portrait supprimé" "no" "$([[ -f "public/$TFILE" ]] && echo yes || echo no)"

summary
