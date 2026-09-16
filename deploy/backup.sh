#!/usr/bin/env bash
#
# Back up the database and the local media disk.
#
# An untested backup is not a backup, so this writes a manifest next to every
# dump recording what it contains and restore.sh verifies against it.
#
#   usage:  deploy/backup.sh [destination-dir]
#
set -Eeuo pipefail

APP_DIR="${MASAR_APP_DIR:-/var/www/masar}"
DEST="${1:-${MASAR_BACKUP_DIR:-/var/backups/masar}}"
KEEP_DAYS="${MASAR_BACKUP_KEEP_DAYS:-14}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

die() { printf 'xxx %s\n' "$*" >&2; exit 1; }
log() { printf '==> %s\n' "$*"; }

cd "$APP_DIR" || die "no app directory at $APP_DIR"
mkdir -p "$DEST"

# Credentials come from .env, never from the command line — an argument lands in
# the process list where any user on the box can read it.
env_get() { sed -n "s/^$1=//p" .env | tail -1 | tr -d '"'"'"'\r'; }
DB_NAME="$(env_get DB_DATABASE)"; DB_USER="$(env_get DB_USERNAME)"
DB_PASS="$(env_get DB_PASSWORD)"; DB_HOST="$(env_get DB_HOST)"; DB_PORT="$(env_get DB_PORT)"
[[ -n "$DB_NAME" ]] || die "DB_DATABASE not readable from .env"

SQL="$DEST/masar-$STAMP.sql.gz"

log "dumping $DB_NAME"
MYSQL_PWD="$DB_PASS" mysqldump \
  --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --user="$DB_USER" \
  --single-transaction --quick --routines --events --default-character-set=utf8mb4 \
  "$DB_NAME" | gzip -9 > "$SQL"

# A dump that cannot be decompressed is not a backup either.
gzip -t "$SQL" || die "dump failed its integrity check: $SQL"

MEDIA=""
if [[ -d storage/app/public ]]; then
  MEDIA="$DEST/masar-media-$STAMP.tar.gz"
  log "archiving the local media disk"
  tar -czf "$MEDIA" -C storage/app public
fi

TABLES="$(MYSQL_PWD="$DB_PASS" mysql --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" \
  --user="$DB_USER" --silent --skip-column-names \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")"

cat > "$DEST/masar-$STAMP.manifest" <<MANIFEST
taken_at=$STAMP
database=$DB_NAME
tables=$TABLES
sql=$(basename "$SQL")
sql_bytes=$(wc -c < "$SQL" | tr -d ' ')
media=$(basename "${MEDIA:-none}")
git=$(git rev-parse --short HEAD 2>/dev/null || echo unknown)
MANIFEST

log "pruning backups older than $KEEP_DAYS days"
find "$DEST" -name 'masar-*' -type f -mtime "+$KEEP_DAYS" -delete

log "done: $SQL ($TABLES tables)"
