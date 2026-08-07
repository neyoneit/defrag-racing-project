#!/usr/bin/env bash
#
# Průběžné zrcadlení serverdem na Backblaze B2.
#
# Dema se zrcadlila jen jednou denně, protože visela jako třetí sekce
# nočního backup.sh. Demo nahrané ráno tak leželo skoro celý den v jediné
# kopii - kdyby ten disk odešel, přišli bychom o všechno z toho dne.
# Tenhle skript běží každých 15 minut a to okno tím zkracuje na minuty.
#
# Proč to jde levně:
#   --max-age    projde jen dema mladší než pár hodin, takže se neřeší
#                celý archiv. Traverza stromu přes SFTP stojí ~14 s.
#   --no-traverse  nevypisuje celý cílový bucket (120k objektů) jen kvůli
#                pár novým souborům - u každého kandidáta se zeptá zvlášť.
#
# Noční backup.sh dělá pořád plnou kopii bez --max-age, takže je to
# zároveň pojistka na cokoliv, co tenhle běh minul.
#
# copy, ne sync: smazání na storage nikdy nesmaže nic v B2.
# Čte se přes SFTP účet dlbrowser, na storage VPS se nic neinstaluje.
#
# Cron:
#   */15 * * * * /var/www/defrag-racing-project/production/current/scripts/serverdemos-mirror.sh >> /root/serverdemos-mirror.log 2>&1

set -euo pipefail

ENV_FILE="/var/www/defrag-racing-project/production/current/.env"
LOCK_FILE="/run/serverdemos-mirror.lock"

# Kolik zpátky se díváme. Výrazně víc než perioda cronu, aby vynechaný
# nebo pomalý běh nenechal díru.
MAX_AGE="${MAX_AGE:-6h}"

# CATCH_UP=1 jednorázově vypne obě zrychlení pravidelného běhu.
#
# --max-age filtruje podle času souboru, jenže ingest i přebalovací skript
# archivu schválně nastaví čas původního dema. Archiv tedy vznikne dnes, ale
# tváří se jako soubor starý týdny a do okna nikdy nespadne. Přesně proto
# 116 tisíc dem přebalených 3.8.2026 v B2 chybí, zatímco jejich syrové
# předlohy tam pořád leží - a bez tohohle režimu by se tam nedostala nikdy.
#
# --no-traverse se vyplácí, dokud je čerstvých souborů pár: zeptá se cíle na
# každý zvlášť místo aby ho vypisoval celý. Při dohánění stovek tisíc
# souborů je to obráceně, tam je jeden výpis mnohem levnější.
if [ "${CATCH_UP:-0}" = "1" ]; then
  SELECT_ARGS=()
  MODE_NOTE="catch-up, bez omezení stáří"
else
  SELECT_ARGS=(--max-age "$MAX_AGE" --no-traverse)
  MODE_NOTE="max-age $MAX_AGE"
fi

# Druhý běh nemá co dělat, když ten první ještě jede - jen by si sedly
# na stejné soubory. -n = raději hned skončit než čekat.
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "[$(date)] Předchozí běh ještě běží, přeskakuji"
  exit 0
fi

read_env() { grep -E "^$1=" "$ENV_FILE" | head -n1 | cut -d '=' -f2- | tr -d "\"'"; }

B2_SERVERDEMOS_KEY_ID="$(read_env B2_SERVERDEMOS_KEY_ID)"
B2_SERVERDEMOS_APP_KEY="$(read_env B2_SERVERDEMOS_APP_KEY)"
B2_SERVERDEMOS_BUCKET="$(read_env B2_SERVERDEMOS_BUCKET)"; B2_SERVERDEMOS_BUCKET="${B2_SERVERDEMOS_BUCKET:-defrag-serverdemos}"
SD_HOST="$(read_env STORAGE_VPS_DL_HOST)"
SD_PORT="$(read_env STORAGE_VPS_DL_PORT)"; SD_PORT="${SD_PORT:-2258}"
SD_USER="$(read_env STORAGE_VPS_DL_USER)"; SD_USER="${SD_USER:-dlbrowser}"
SD_KEY_PATH="$(read_env STORAGE_VPS_DL_KEY_PATH)"

for v in B2_SERVERDEMOS_KEY_ID B2_SERVERDEMOS_APP_KEY SD_HOST SD_KEY_PATH; do
  if [ -z "${!v}" ]; then
    echo "[$(date)] CHYBA: $v nenalezeno v $ENV_FILE" >&2
    exit 1
  fi
done

export RCLONE_CONFIG_SDSFTP_TYPE=sftp
export RCLONE_CONFIG_SDSFTP_HOST="$SD_HOST"
export RCLONE_CONFIG_SDSFTP_PORT="$SD_PORT"
export RCLONE_CONFIG_SDSFTP_USER="$SD_USER"
export RCLONE_CONFIG_SDSFTP_KEY_FILE="$SD_KEY_PATH"
# Bez tohohle si rclone klíč hostitele vůbec neověřuje a jen na to upozorní v
# logu - spojení by tak šlo podvrhnout a dema poslat jinam. Soubor se plní
# ručně z ověřeného otisku, takže neexistující nebo prázdný known_hosts je
# důvod k selhání, ne k tichému pokračování.
export RCLONE_CONFIG_SDSFTP_KNOWN_HOSTS_FILE="${HOME:-/root}/.ssh/known_hosts"
export RCLONE_CONFIG_SDB2_TYPE=b2
export RCLONE_CONFIG_SDB2_ACCOUNT="$B2_SERVERDEMOS_KEY_ID"
export RCLONE_CONFIG_SDB2_KEY="$B2_SERVERDEMOS_APP_KEY"

# --stats-one-line drží log čitelný: jeden řádek za běh místo průběžného
# překreslování, které v souboru vypadá jako smetí.
# --exclude *.part: ingest balí demo do dočasného .7z.part a teprve hotový
# archiv přejmenuje. Nahrát rozdělaný kus by znamenalo mít ho v B2 navždy,
# protože copy nikdy nic nemaže.
RUN_LOG="$(mktemp)"
trap 'rm -f "$RUN_LOG"' EXIT

# --immutable je tu kvůli tomu, že jméno dema není unikátní. Recordsystem
# pojmenovává soubor mapa[čas][mdd], takže dvě různé jízdy jednoho hráče se
# stejným časem na stejné mapě dostanou totožné jméno. Bez tohohle přepínače
# by rclone objekt v B2 tiše přepsal a starší jízda by zmizela - přesně to se
# stalo u czsk2009-zerg[24672][12212], kde se syrový upload a uložený archiv
# lišily obsahem. Teď se takový soubor nenahraje a vypíše se jako ERROR;
# všechny ostatní projdou normálně a nic se nikde nemaže, copy jen zapisuje.
#
# Návratový kód na to nestačí, rclone jím kolizi neodliší od výpadku sítě,
# proto se rozhoduje podle konkrétní hlášky níž.
set +e
rclone copy sdsftp:/var/lib/serverdemos sdb2:"$B2_SERVERDEMOS_BUCKET"/serverdemos/ \
  --exclude "*.part" \
  --exclude "*.dm_68" \
  --immutable \
  ${SELECT_ARGS[@]+"${SELECT_ARGS[@]}"} \
  --transfers 4 \
  --sftp-concurrency 8 \
  --stats-one-line \
  --stats 0 2>&1 | tee "$RUN_LOG"
rc=${PIPESTATUS[0]}
set -e

# Kotva na začátek řádku tu byla špatně: rclone píše do souboru s datem
# vepředu ("2026/08/07 23:45:06 ERROR : cesta: ..."), takže `^ERROR : ` nesedlo
# nikdy a seznam kolizí byl vždycky prázdný. Nic se proto nepřejmenovalo a
# každý běh s kolizí se navíc tvářil jako skutečné selhání - od 5.8.2026 jich
# takhle v logu bylo 135. Hledá se tedy kdekoliv na řádku.
mapfile -t KOLIZE < <(
  grep -oP 'ERROR\s*:\s*\K.*(?=: Source and destination exist but do not match: immutable file modified$)' \
    "$RUN_LOG" | sort -u
)

# Přejmenovává se na storage, ne až v B2. Odtud si to zbytek řetězu vezme
# sám: serverdemos:index běží po čtvrthodinách, uvidí nové jméno a založí
# druhý řádek v server_demos, protože parser čte [2] jako tutéž mapu, čas i
# hráče. Příští běh zrcadlení pak archiv nahraje bez kolize. Žádný ruční
# zápis do databáze a žádná zvláštní větev nikde jinde.
for rel in ${KOLIZE[@]+"${KOLIZE[@]}"}; do
  if [[ ! "$rel" =~ ^(.*)(\.dm_[0-9]+(\.7z)?)$ ]]; then
    echo "[$(date)] KOLIZE: neznámý tvar jména, přeskakuji: $rel" >&2
    continue
  fi
  stem="${BASH_REMATCH[1]}"
  ext="${BASH_REMATCH[2]}"

  # Volné pořadí se hledá na obou stranách. B2 proto, že archivy ze storage
  # jednou zmizí a trvalou pravdou o tom, co už existuje, zůstane bucket.
  #
  # Rozhoduje VÝPIS, ne návratový kód. U B2 se neexistující cesta chová jako
  # prázdný prefix: rclone vrátí nulu a nevypíše nic, takže test podle kódu
  # hlásil "existuje" pro každé pořadí a cyklus vždycky doběhl na dvacítku.
  # Všechny čtyři kolize z 8.8.2026 tak skončily hláškou o dvaceti kopiích,
  # každá po pětadvaceti sekundách marného ptaní se.
  n=2
  while [ "$n" -le 20 ] \
    && { [ -n "$(rclone lsf "sdsftp:/var/lib/serverdemos/${stem}[${n}]${ext}" 2>/dev/null)" ] \
      || [ -n "$(rclone lsf "sdb2:$B2_SERVERDEMOS_BUCKET/serverdemos/${stem}[${n}]${ext}" 2>/dev/null)" ]; }; do
    n=$((n + 1))
  done

  if [ "$n" -gt 20 ]; then
    echo "[$(date)] KOLIZE: víc než 20 kopií, nechávám být: $rel" >&2
    continue
  fi

  if rclone moveto "sdsftp:/var/lib/serverdemos/$rel" \
                   "sdsftp:/var/lib/serverdemos/${stem}[${n}]${ext}"; then
    echo "[$(date)] KOLIZE: $rel -> ${stem}[${n}]${ext}"
  else
    echo "[$(date)] KOLIZE: přejmenování selhalo, demo zůstává v jedné kopii: $rel" >&2
  fi
done

# Syrová dema se nahrávají jen tehdy, když se nezabalila.
#
# Ingest balí demo hned po příchodu, ale zrcadlení jede po čtvrthodinách a
# dřív nebo později se do toho okna trefí. Pak nahraje syrovou verzi, o pár
# minut později vedle ní přistane archiv a syrová kopie tam zůstane navždy,
# protože copy nikdy nic nemaže. Takhle se v bucketu 4.8.2026 našly čtyři a
# pátý přibyl během hodiny, co se první tři uklízely.
#
# Vypnout je natvrdo ale nejde: když se balení nepovede, je ta syrová verze
# jediná, co existuje, a bez zálohy by visela v jedné kopii na disku, který
# už jednou došel. Rozhoduje proto přesná podmínka - archiv vedle sebe na
# storage. Za normálního provozu je tenhle seznam prázdný, takže to nic
# nestojí.
mapfile -t SYROVA < <(
  rclone lsf sdsftp:/var/lib/serverdemos -R --files-only --include "*.dm_68" 2>/dev/null
)

nezabalenych=0
for raw in ${SYROVA[@]+"${SYROVA[@]}"}; do
  if rclone lsf "sdsftp:/var/lib/serverdemos/${raw}.7z" >/dev/null 2>&1; then
    continue
  fi
  nezabalenych=$((nezabalenych + 1))
  echo "[$(date)] NEZABALENO, zálohuji syrové: $raw" >&2
  rclone copyto "sdsftp:/var/lib/serverdemos/$raw" \
                "sdb2:$B2_SERVERDEMOS_BUCKET/serverdemos/$raw" \
    || echo "[$(date)] záloha syrového selhala: $raw" >&2
done

# Nenulový kód bez jediné kolize je skutečná chyba a musí být vidět.
if [ "$rc" -ne 0 ] && [ "${#KOLIZE[@]}" -eq 0 ]; then
  echo "[$(date)] Serverdemos mirror ($MODE_NOTE) SELHAL, rclone rc=$rc" >&2
  exit "$rc"
fi

echo "[$(date)] Serverdemos mirror ($MODE_NOTE) OK, kolizí: ${#KOLIZE[@]}, nezabalených: $nezabalenych"
