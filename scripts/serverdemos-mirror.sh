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
export RCLONE_CONFIG_SDB2_TYPE=b2
export RCLONE_CONFIG_SDB2_ACCOUNT="$B2_SERVERDEMOS_KEY_ID"
export RCLONE_CONFIG_SDB2_KEY="$B2_SERVERDEMOS_APP_KEY"

# --stats-one-line drží log čitelný: jeden řádek za běh místo průběžného
# překreslování, které v souboru vypadá jako smetí.
rclone copy sdsftp:/var/lib/serverdemos sdb2:"$B2_SERVERDEMOS_BUCKET"/serverdemos/ \
  --max-age "$MAX_AGE" \
  --no-traverse \
  --transfers 4 \
  --sftp-concurrency 8 \
  --stats-one-line \
  --stats 0

echo "[$(date)] Serverdemos mirror (max-age $MAX_AGE) OK"
