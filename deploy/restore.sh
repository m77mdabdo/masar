#!/usr/bin/env bash
#
# Restore from a dump — and then clear the cache, which is the whole point.
#
# A raw SQL import runs no application code, so nothing flushes the caches the
# previous contents left behind. That is how a populated database ended up being
# served through an empty cached snapshot with no main navigation and no market
# ticker, for hours, with every test passing. CLAUDE.md §9 carries the rule; this
# script is the rule made executable.
#
#   usage:  deploy/restore.sh /var/backups/masar/masar-....sql.gz [--force]
#
set -Eeuo pipefail

APP_DIR="${MASAR_APP_DIR:-/var/www/masar}"
PHP="${MASAR_PHP:-php}"
DUMP="${1:-}"
FORCE="${2:-}"

die() { printf 'xxx %s\n' "$*" >&2; exit 1; }
log() { printf '==> %s\n' "$*"; }

[[ -n "$DUMP" ]] || die "usage: deploy/restore.sh <dump.sql.gz> [--force]"
[[ -f "$DUMP" ]] || die "no such dump: $DUMP"

cd "$APP_DIR" || die "no app directory at $APP_DIR"

env_get() { sed -n "s/^$1=//p" .env | tail -1 | tr -d '"'"'"'\r'; }
DB_NAME="$(env_get DB_DATABASE)"; DB_USER="$(env_get DB_USERNAME)"
DB_PASS="$(env_get DB_PASSWORD)"; DB_HOST="$(env_get DB_HOST)"; DB_PORT="$(env_get DB_PORT)"
[[ -n "$DB_NAME" ]] || die "DB_DATABASE not readable from .env"

if [[ "$FORCE" != "--force" ]]; then
  read -r -p "Restore $DUMP over database '$DB_NAME'? This replaces it. [y/N] " reply
  [[ "$reply" == "y" || "$reply" == "Y" ]] || die "aborted"
fi

gzip -t "$DUMP" 2>/dev/null || die "dump fails its integrity check — refusing to restore from it"

log "putting the site into maintenance"
"$PHP" artisan down --retry=30 || true

log "restoring $DB_NAME"
gunzip -c "$DUMP" | MYSQL_PWD="$DB_PASS" mysql \
  --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --user="$DB_USER" \
  --default-character-set=utf8mb4 "$DB_NAME"

# ---------------------------------------------------------------------------
# The line this script exists for.
#
# Everything above ran outside the application. Every cached value still
# describes the database as it was a moment ago — and an empty or stale snapshot
# in front of good data renders as a site with pieces missing and raises nothing.
# ---------------------------------------------------------------------------
log "clearing the application cache (a restore runs no application code, so nothing else does this)"
"$PHP" artisan cache:clear

# Denormalised counters are not in the cache and not rebuilt by an import.
log "rebuilding denormalised counters"
"$PHP" artisan masar:recount || true

"$PHP" artisan up

log "verifying the restored site rather than assuming it"
"$PHP" artisan masar:smoke || die "restore completed but the smoke check failed — do not leave it in this state"

log "restored from $(basename "$DUMP")"
