#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Fonctions utilitaires pour tester l'application avec curl.
# Chargées par tests/smoke.sh (« source tests/lib.sh »).
#
# Chaque « profil » (admin, coulson, hill...) a son propre fichier de cookies :
# on peut donc simuler plusieurs utilisateurs connectés en même temps.
# ---------------------------------------------------------------------------

BASE="${BASE:-http://localhost:8000}"
JARS="$(mktemp -d)"
PASS=0
FAIL=0

# Extrait le premier jeton CSRF d'une page HTML lue sur l'entrée standard.
csrf_from() {
    grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"$//'
}

# get PROFIL CHEMIN : affiche le HTML de la page.
get() {
    curl -s -c "$JARS/$1" -b "$JARS/$1" "$BASE$2"
}

# status PROFIL CHEMIN : affiche le code HTTP d'un GET.
status() {
    curl -s -o /dev/null -w '%{http_code}' -c "$JARS/$1" -b "$JARS/$1" "$BASE$2"
}

# token PROFIL : jeton CSRF courant du profil (lu sur la page de connexion ou d'accueil).
token() {
    local page
    page=$(get "$1" /)
    if ! grep -q 'name="_csrf"' <<<"$page"; then
        page=$(get "$1" /login)
    fi
    csrf_from <<<"$page"
}

# post PROFIL CHEMIN [--data-urlencode "cle=valeur" ...] : POST avec jeton CSRF.
# Affiche « CODE URL_DE_REDIRECTION ».
post() {
    local profile="$1" path="$2"
    shift 2
    local csrf
    csrf=$(token "$profile")
    curl -s -o "$JARS/last_body" -w '%{http_code} %{redirect_url}' -c "$JARS/$profile" -b "$JARS/$profile" \
        --data-urlencode "_csrf=$csrf" "$@" "$BASE$path"
}

# Corps de la dernière réponse à un post().
last_body() {
    cat "$JARS/last_body"
}

# login PROFIL EMAIL : connexion avec le mot de passe de démo.
login() {
    post "$1" /login --data-urlencode "email=$2" --data-urlencode 'password=Agent2026!'
}

# check DESCRIPTION ATTENDU OBTENU : compare et comptabilise.
check() {
    if [[ "$2" == "$3" ]]; then
        PASS=$((PASS + 1))
        printf '  \033[32mOK\033[0m   %s\n' "$1"
    else
        FAIL=$((FAIL + 1))
        printf '  \033[31mKO\033[0m   %s (attendu : %s, obtenu : %s)\n' "$1" "$2" "$3"
    fi
}

# Décode les entités produites par htmlspecialchars() pour comparer du texte lisible.
decode() {
    sed "s/&#039;/'/g; s/&quot;/\"/g; s/&lt;/</g; s/&gt;/>/g; s/&amp;/\\&/g"
}

# contains DESCRIPTION TEXTE HTML : vérifie qu'un texte figure dans le HTML.
contains() {
    if decode <<<"$3" | grep -qF -- "$2"; then check "$1" yes yes; else check "$1" yes no; fi
}

# absent DESCRIPTION TEXTE HTML : vérifie qu'un texte NE figure PAS dans le HTML.
absent() {
    if decode <<<"$3" | grep -qF -- "$2"; then check "$1" yes no; else check "$1" yes yes; fi
}

# summary : bilan et code de sortie.
summary() {
    echo
    echo "Bilan : $PASS réussi(s), $FAIL échec(s)."
    rm -rf "$JARS"
    [[ $FAIL -eq 0 ]]
}
