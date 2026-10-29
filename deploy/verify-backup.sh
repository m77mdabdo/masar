#!/usr/bin/env bash
#
# Prove a backup restores: import it into a scratch database and compare
# every table's row count with the manifest backup.sh wrote beside it.
#
# Never touches the application's own database. The target must be named,
# must differ from DB_DATABASE in .env, and is dropped afterwards unless
# --keep is given.
#
#   usage:  deploy/verify-backup.sh <masar-STAMP.sql.gz> <scratch_db_name> [--keep]
#
set -Eeuo pipefail
APP_DIR="${MASAR_APP_DIR:-/var/www/masar}"
DUMP="${1:-}"; SCRATCH="${2:-}"; KEEP="${3:-}"
die() { printf 'xxx %s\n' "$*" >&2; exit 1; }
log() { printf '==> %s\n' "$*"; }

[[ -n "$DUMP" && -n "$SCRATCH" ]] || die "usage: deploy/verify-backup.sh <dump.sql.gz> <scratch_db> [--keep]"
[[ -f "$DUMP" ]] || die "no such dump: $DUMP"
MANIFEST="${DUMP%.sql.gz}.manifest"
[[ -f "$MANIFEST" ]] || die "no manifest beside the dump: $MANIFEST"
[[ "$SCRATCH" =~ ^[A-Za-z0-9_]+$ ]] || die "scratch database name must be [A-Za-z0-9_]"

cd "$APP_DIR" || die "no app directory at $APP_DIR"
env_get() { sed -n "s/^$1=//p" .env | tail -1 | tr -d '"'"'"'\r'; }
DB_NAME="$(env_get DB_DATABASE)"; DB_USER="$(env_get DB_USERNAME)"
DB_PASS="$(env_get DB_PASSWORD)"; DB_HOST="$(env_get DB_HOST)"; DB_PORT="$(env_get DB_PORT)"
[[ "$SCRATCH" != "$DB_NAME" ]] || die "refusing: $SCRATCH is the application's own database"

sql() { MYSQL_PWD="$DB_PASS" mysql --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --user="$DB_USER" --silent --skip-column-names "$@"; }

gzip -t "$DUMP" || die "dump fails its integrity check"
log "restoring into scratch database $SCRATCH"
sql -e "DROP DATABASE IF EXISTS \`$SCRATCH\`; CREATE DATABASE \`$SCRATCH\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
trap '[[ "$KEEP" == "--keep" ]] || sql -e "DROP DATABASE IF EXISTS \`$SCRATCH\`"' EXIT
gunzip -c "$DUMP" | sql --default-character-set=utf8mb4 "$SCRATCH"

log "comparing row counts with $(basename "$MANIFEST")"
expected=0; mismatched=0; total=0
while IFS='=' read -r key want; do
  [[ "$key" == rows.* ]] || continue
  t="${key#rows.}"; expected=$((expected + 1))
  have="$(sql -e "SELECT COUNT(*) FROM \`$SCRATCH\`.\`$t\`" 2>/dev/null || echo missing)"
  total=$((total + ${have//missing/0}))
  if [[ "$have" != "$want" ]]; then
    printf 'xxx %s: manifest %s, restored %s\n' "$t" "$want" "$have"; mismatched=$((mismatched + 1))
  fi
done < "$MANIFEST"

[[ "$expected" -gt 0 ]] || die "manifest has no row counts (taken by an older backup.sh?)"
[[ "$mismatched" -eq 0 ]] || die "$mismatched of $expected tables differ — this backup does not restore what was dumped"
log "verified: $expected tables, $total rows, all match the manifest"
