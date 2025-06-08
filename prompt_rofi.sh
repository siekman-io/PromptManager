#!/usr/bin/env bash
#==============================================================================
# Prompt Manager - Rofi Desktop versie - by Jouke Siekman https://siekman.io
#==============================================================================

set -euo pipefail

# ---- Config apart inlezen ----
CONF="${PROMPTMGR_CONF:-$HOME/.ssh/promptmgr_db.conf}"
if [ ! -f "$CONF" ]; then
    echo "❌ Database config ($CONF) niet gevonden!" >&2
    exit 2
fi
# shellcheck source=/dev/null
. "$CONF"

ROFI_OPTS_WIDE="-dmenu -i -no-custom -theme-str window{width:900px;}"
ROFI_OPTS_SINGLE="-dmenu -theme-str window{width:440px;}"

if command -v pbcopy >/dev/null 2>&1; then
    CLIP="pbcopy"
elif command -v wl-copy >/dev/null 2>&1; then
    CLIP="wl-copy"
elif command -v xclip >/dev/null 2>&1; then
    CLIP="xclip -selection clipboard"
else
    echo "❌ Kan geen clipboard tool vinden (pbcopy, wl-copy, xclip vereist)." >&2
    exit 1
fi

RED="\e[31m"; GRN="\e[32m"; YEL="\e[33m"; BLU="\e[34m"; NC="\e[0m"

usage() {
    echo -e "${GRN}Prompt Manager (Desktop/Rofi)${NC}"
    echo -e "${GRN}Created by Jouke Siekman (https://siekman.io)${NC}"
    echo -e "Gebruik: $0 [--add | --list | --select | --delete | --help]"
    echo -e "Opties:"
    echo -e "  --add       Prompt toevoegen"
    echo -e "  --list      Lijst tonen"
    echo -e "  --select    Prompt kiezen en kopiëren (standaard)"
    echo -e "  --delete    Prompt verwijderen"
    echo -e "  --help      Deze helptekst"
}

mysql_cmd() {
    mariadb --skip-ssl -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
        -D "$DB_NAME" -s -N -e "$1"
}

add_prompt() {
    local title omschrijving prompt subcategory ai_platform esc_prompt esc_omschrijving tmpfile

    title="$(echo | rofi $ROFI_OPTS_WIDE -p "Titel voor prompt")"
    [ -z "$title" ] && echo -e "${RED}❌ Titel mag niet leeg zijn.${NC}" && exit 1
    omschrijving="$(echo | rofi $ROFI_OPTS_WIDE -p "Omschrijving/details (optioneel)")"
    subcategory="$(echo | rofi $ROFI_OPTS_WIDE -p "Subcategorie (optioneel)")"
    ai_platform="$(echo | rofi $ROFI_OPTS_WIDE -p "AI platform (bijv. ChatGPT, Claude, Midjourney)")"

    tmpfile=$(mktemp /tmp/promptmgr.XXXXXX)
    echo "# Schrijf hieronder je prompt (gebruik {{var}} voor variabelen)." > "$tmpfile"
    echo "# Verwijder deze regels voordat je opslaat." >> "$tmpfile"
    if [ -n "${EDITOR:-}" ]; then
        "$EDITOR" "$tmpfile"
    elif command -v nano >/dev/null 2>&1; then
        nano "$tmpfile"
    else
        vi "$tmpfile"
    fi
    prompt="$(grep -v '^#' "$tmpfile" | sed '/^[[:space:]]*$/d')"
    rm -f "$tmpfile"
    [ -z "$prompt" ] && echo -e "${RED}❌ Prompt mag niet leeg zijn.${NC}" && exit 1
    prompt="$(printf '%b' "$prompt")"
    esc_prompt=$(printf '%s' "$prompt" | sed "s/'/''/g")
    esc_omschrijving=$(printf '%s' "$omschrijving" | sed "s/'/''/g")
    mysql_cmd "INSERT INTO $DB_TABLE (title, omschrijving, prompt_body, subcategory, ai_platform) VALUES ('$title', '$esc_omschrijving', '$esc_prompt', '$subcategory', '$ai_platform');"
    echo -e "${GRN}✔ Prompt toegevoegd.${NC}"
}

list_prompts() {
    printf "%-4s %-25s %-24s %-14s %-12s %-20s %-20s\n" "ID" "Titel" "Omschrijving" "Subcat" "Platform" "Toegevoegd" "Laatst gebruikt"
    mysql_cmd "SELECT id, title, omschrijving, subcategory, ai_platform, date_added, last_used FROM $DB_TABLE;" | \
        awk -F'\t' '{printf "%-4s %-25s %-24s %-14s %-12s %-20s %-20s\n", $1, $2, ($3?substr($3,1,22):"-"), $4, $5, $6, $7}'
}

delete_prompt() {
    local row id
    row="$(
        mysql_cmd "SELECT id, title, omschrijving, subcategory, ai_platform FROM $DB_TABLE ORDER BY id DESC;" \
        | awk -F'\t' '{printf "%-4s %-22s [%-12s] (%-10s) ~ %s\n", $1, $2, $4, $5, ($3?substr($3,1,40):"-")}' \
        | rofi $ROFI_OPTS_WIDE -p "Prompt verwijderen"
    )"
    [ -z "$row" ] && exit 0
    id="$(echo "$row" | awk '{print $1}')"
    mysql_cmd "DELETE FROM $DB_TABLE WHERE id=$id;"
    echo -e "${YEL}✔ Prompt verwijderd.${NC}"
}

select_prompt() {
    local row id prompt omschrijving filled

    row="$(
        mysql_cmd "SELECT id, title, omschrijving, subcategory, ai_platform FROM $DB_TABLE ORDER BY subcategory, title;" \
        | awk -F'\t' '{printf "%-4s %-22s [%-12s] (%-10s) ~ %s\n", $1, $2, $4, $5, ($3?substr($3,1,40):"-")}' \
        | rofi $ROFI_OPTS_WIDE -p "Kies prompt"
    )"
    [ -z "$row" ] && exit 0
    id="$(echo "$row" | awk '{print $1}')"
    prompt="$(mysql_cmd "SELECT prompt_body FROM $DB_TABLE WHERE id=$id;")"

    # Vind alle unieke variabelen in {{var}}
    mapfile -t vars < <(grep -oP '{{\K[^}]+' <<< "$prompt" | sort -u)
    declare -A values

    if [[ ${#vars[@]} -eq 0 ]]; then
        echo -e "${YEL}Geen variabelen gevonden in deze prompt.${NC}"
    else
        for var in "${vars[@]}"; do
            values["$var"]="$(echo | rofi $ROFI_OPTS_SINGLE -p "Waarde voor '$var'")"
        done
    fi

    filled="$prompt"
    for var in "${vars[@]}"; do
        filled="${filled//\{\{$var\}\}/${values[$var]}}"
    done
    filled="$(printf '%b' "$filled")"
    echo -n "$filled" | eval $CLIP
    mysql_cmd "UPDATE $DB_TABLE SET last_used=NOW() WHERE id=$id;"
    echo -e "${BLU}✔ Prompt op clipboard gezet en gebruiksdatum bijgewerkt.${NC}"
}

case "${1:-}" in
    --add) add_prompt ;;
    --list) list_prompts ;;
    --delete) delete_prompt ;;
    --help) usage ;;
    ""|--select) select_prompt ;;
    *)
        echo -e "${RED}Onbekende optie:${NC} $1"; usage; exit 1 ;;
esac

exit 0

