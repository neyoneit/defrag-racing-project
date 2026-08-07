#!/usr/bin/env bash
#
# Záloha defrag.racing
#   1) DB dump + .env  -> B2 "defrag-backups"  (datované, rotované, write-only klíč)
#   2) Média (mirror)  -> B2 "defrag-media"    (inkrementálně, read+write klíč)
#   3) Serverdemos     -> B2 "defrag-serverdemos" (plná kontrola; průběžně to
#                         dělá serverdemos-mirror.sh každých 15 minut)
#
# Spouštěno cronem z current/scripts/backup.sh.
# Žádná tajemství zde nejsou - creds se čtou z .env za běhu.
#
# Každá sekce běží samostatně: pod `set -e` stačilo, aby selhal dump databáze,
# a na média ani na dema už vůbec nedošlo - tichý výpadek přesně té zálohy,
# kterou nikdo nekontroluje. Teď se selhání zaznamená, ostatní sekce doběhnou
# a skript skončí nenulově, takže je to poznat i z cronu.

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
sekce_db() {
# Dump se uklidí i když sekce spadne. Dřív shodilo selhání celý skript a
# nedodělaný dump si někdo všiml; teď sekce padá samostatně, takže by se
# tady každou noc tiše hromadil další. Trap patří podprocesu, ve kterém
# sekce běží, takže sáhne i na pád přes errexit.
trap 'rm -f "$DB_FILE" "$FILES_FILE"' EXIT

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

# Po úspěšném uploadu lokální dump nedržíme - vše je na B2. (Trap výše by to
# udělal stejně, tohle jen nedrží soubory po dobu zbytku sekce.)
rm -f "$DB_FILE" "$FILES_FILE"
}

# ===== 2) Média mirror -> defrag-media (inkrementálně, jen přidává/přepisuje) =====
sekce_media() {
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
}

# ===== 3) Serverdemos mirror (storage VPS -> defrag-serverdemos) =====
sekce_serverdemos() {
# Tahle sekce měla vlastní `rclone copy` s `|| true` a hláškou "OK" pod ním,
# takže selhání spolkla úplně všechna - vypršelý klíč i odstavený storage by
# vypadaly stejně jako úspěch.
#
# A hlavně jí chyběla polovina práce. Kolizi jmen (mapa[čas][mdd] není
# unikátní) umí vyřešit přejmenováním na [2] jen serverdemos-mirror.sh, jenže
# ten jede s --max-age 6h a archivu se schválně nastavuje čas původního dema,
# takže archiv starého dema do jeho okna nikdy nespadne. Kolize na takovém
# souboru tedy neřešil nikdo: noční běh ji každou noc odmítl, napsal ERROR,
# napsal OK a šel dál. V logu k 7.8.2026 tak stálo pět dem, která ležela na
# storage v jediné kopii a v B2 nebyla pod žádným jménem.
#
# Proto se tu nekopíruje ručně, ale zavolá se ten samý skript v režimu
# CATCH_UP=1, tedy plný průchod bez omezení stáří. Získáme tím přejmenování
# kolizí, zálohu nezabalených syrových dem i poctivé hlášení chyb, a nemáme
# zrcadlení napsané dvakrát. Svůj zámek si drží sám, takže se to nepopere se
# souběžným patnáctiminutovým během.
MIRROR="$APP_DIR/scripts/serverdemos-mirror.sh"

if [ ! -x "$MIRROR" ]; then
  echo "[$(date)] Serverdemos přeskočeny (chybí $MIRROR)" >&2
  return 1
fi

CATCH_UP=1 "$MIRROR"
}

# ---- Spuštění ----
SELHALO=()

# Sekce běží v podprocesu, který si errexit zapíná sám, a volá se MIMO `if` a
# mimo AND-OR seznam. Kdyby se volala uvnitř podmínky, bash by errexit v celé
# funkci potlačil a sekce by po selhaném dumpu vesele pokračovala balením a
# uploadem prázdna. Takhle první chyba ukončí jen tu jednu sekci.
spust() {
  local nazev="$1" funkce="$2" navrat=0

  set +e
  ( set -e; "$funkce" )
  navrat=$?
  set -e

  if [ "$navrat" -ne 0 ]; then
    echo "[$(date)] !!! sekce '$nazev' SELHALA (kód $navrat), pokračuji dál" >&2
    SELHALO+=("$nazev")
  fi
}

spust "DB + .env" sekce_db
spust "média" sekce_media
spust "serverdemos" sekce_serverdemos

if [ ${#SELHALO[@]} -gt 0 ]; then
  echo "[$(date)] === Záloha $STAMP DOKONČENA S CHYBAMI: ${SELHALO[*]} ==="
  exit 1
fi

echo "[$(date)] === Záloha $STAMP DOKONČENA ==="
