#!/usr/bin/env bash
#
# Záloha defrag.racing
#   1) DB dump + .env  -> B2 "defrag-backups"  (datované, rotované, write-only klíč)
#   2) Média (mirror)  -> B2 "defrag-media"    (inkrementálně, read+write klíč)
#
# Spouštěno cronem z current/scripts/backup.sh.
# Žádná tajemství zde nejsou - creds se čtou z .env za běhu.

set -euo pipefail

# ---- Konfigurace ----
ENV_FILE="/var/www/defrag-racing-project/production/current/.env"
APP_DIR="/var/www/defrag-racing-project/production/current"
BASEQ3_DIR="/var/www/defrag-racing-project/production/deploy/baseq3"
BACKUP_DIR="/root/backups"
STAMP="$(date +%F-%H%M)"

read_env() { grep -E "^$1=" "$ENV_FILE" | head -n1 | cut -d '=' -f2- | tr -d "\"'"; }

# ---- DB creds ----
DB_DATABASE="$(read_env DB_DATABASE)"
DB_USERNAME="$(read_env DB_USERNAME)"
DB_PASSWORD="$(read_env DB_PASSWORD)"
# ---- B2 backups (write-only) ----
B2_BACKUP_KEY_ID="$(read_env B2_BACKUP_KEY_ID)"
B2_BACKUP_APP_KEY="$(read_env B2_BACKUP_APP_KEY)"
B2_BACKUP_BUCKET="$(read_env B2_BACKUP_BUCKET)"; B2_BACKUP_BUCKET="${B2_BACKUP_BUCKET:-defrag-backups}"
# ---- B2 media (read+write) ----
B2_MEDIA_KEY_ID="$(read_env B2_MEDIA_KEY_ID)"
B2_MEDIA_APP_KEY="$(read_env B2_MEDIA_APP_KEY)"
B2_MEDIA_BUCKET="$(read_env B2_MEDIA_BUCKET)"; B2_MEDIA_BUCKET="${B2_MEDIA_BUCKET:-defrag-media}"

for v in DB_DATABASE DB_USERNAME B2_BACKUP_KEY_ID B2_BACKUP_APP_KEY; do
  [ -z "${!v}" ] && { echo "CHYBA: $v nenalezeno v $ENV_FILE" >&2; exit 1; }
done

mkdir -p "$BACKUP_DIR"
DB_FILE="$BACKUP_DIR/db-$STAMP.sql.gz"
FILES_FILE="$BACKUP_DIR/files-$STAMP.tar.gz"
echo "[$(date)] === Start zálohy $STAMP ==="

# ===== 1) DB + .env -> defrag-backups (datované) =====
mysqldump --single-transaction --quick --routines --triggers --no-tablespaces \
  -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$DB_FILE"
tar czhf "$FILES_FILE" -C "$APP_DIR" .env      # -h dereferencuje symlink .env
gzip -t "$DB_FILE"; gzip -t "$FILES_FILE"
echo "[$(date)] DB+env dump OK ($(du -h "$DB_FILE" | cut -f1))"

export RCLONE_CONFIG_B2BACKUP_TYPE=b2
export RCLONE_CONFIG_B2BACKUP_ACCOUNT="$B2_BACKUP_KEY_ID"
export RCLONE_CONFIG_B2BACKUP_KEY="$B2_BACKUP_APP_KEY"
rclone copy "$DB_FILE"    b2backup:"$B2_BACKUP_BUCKET"/ --no-check-dest
rclone copy "$FILES_FILE" b2backup:"$B2_BACKUP_BUCKET"/ --no-check-dest
echo "[$(date)] DB+env upload na B2 OK"

# Po úspěšném uploadu lokální dump nedržíme - vše je na B2.
rm -f "$DB_FILE" "$FILES_FILE"

# ===== 2) Média mirror -> defrag-media (inkrementálně, jen přidává/přepisuje) =====
if [ -n "$B2_MEDIA_KEY_ID" ] && [ -n "$B2_MEDIA_APP_KEY" ]; then
  export RCLONE_CONFIG_B2MEDIA_TYPE=b2
  export RCLONE_CONFIG_B2MEDIA_ACCOUNT="$B2_MEDIA_KEY_ID"
  export RCLONE_CONFIG_B2MEDIA_KEY="$B2_MEDIA_APP_KEY"

  # 2a) uživatelská média + modely + náhledy (storage/app/public, ~13 GB)
  rclone copy "$APP_DIR/storage/app/public/" b2media:"$B2_MEDIA_BUCKET"/public/ \
    --fast-list --transfers 8 --exclude "temp_*/**" --exclude "*.tmp"

  # 2b) base Quake assety (pak0-pak8, gitignored, ~664 MB)
  if [ -d "$BASEQ3_DIR" ]; then
    rclone copy "$BASEQ3_DIR/" b2media:"$B2_MEDIA_BUCKET"/baseq3/ \
      --fast-list --transfers 8
  fi
  echo "[$(date)] Média mirror na B2 OK"
else
  echo "[$(date)] Média přeskočena (B2_MEDIA_* není v .env)"
fi

echo "[$(date)] === Záloha $STAMP DOKONČENA ==="
