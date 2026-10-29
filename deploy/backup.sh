#!/usr/bin/env bash
#
# Back up the database and the local media disk.
#
# An untested backup is not a backup, so this writes a manifest next to every
# dump recording what it contains and restore.sh verifies against it.
#
#   usage:  deploy/backup.sh [destination-dir]
#
# Disk safety (2026-10-29: two runs filled a dev disk with 3.2 GB media
# archives and left a truncated one behind):
#   - every file is written as .partial and renamed only once complete, and a
#     failed run removes its own partials — a truncated archive can never be
#     mistaken for a backup;
#   - free space is checked before each write, keeping MASAR_BACKUP_MIN_FREE_MB
#     (default 1024) spare. Too little for the dump: stop before writing. Too
#     little for media: back up the database, skip media and say so, unless
#     MASAR_BACKUP_REQUIRE_MEDIA=1;
#   - unchanged media is not archived again: the manifest records a fingerprint
#     of the media files and reuses the newest complete archive that matches;
#   - retention prunes complete sets only, deletes a media archive only when no
#     remaining manifest refers to it, and never prunes the newest set.
#
set -Eeuo pipefail

APP_DIR="${MASAR_APP_DIR:-/var/www/masar}"
DEST="${1:-${MASAR_BACKUP_DIR:-/var/backups/masar}}"
KEEP_DAYS="${MASAR_BACKUP_KEEP_DAYS:-14}"
MIN_FREE_MB="${MASAR_BACKUP_MIN_FREE_MB:-1024}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

die() { printf 'xxx %s\n' "$*" >&2; exit 1; }
log() { printf '==> %s\n' "$*"; }
warn() { printf '!!! %s\n' "$*" >&2; }

PARTIALS=()
# Removes only this run's unfinished files; never a completed backup. Returns
# 0 so a successful run is not reported as failed by its own trap.
cleanup() {
  for f in "${PARTIALS[@]:-}"; do
    if [[ -n "$f" && -f "$f" ]]; then rm -f -- "$f"; fi
  done
  return 0
}
trap cleanup EXIT

free_mb() { df -Pk "$DEST" | awk 'NR==2 { print int($4 / 1024) }'; }
# Room for $1 MB more while keeping MIN_FREE_MB spare.
has_room() { (( $(free_mb) - $1 >= MIN_FREE_MB )); }

cd "$APP_DIR" || die "no app directory at $APP_DIR"
mkdir -p "$DEST"

# Credentials come from .env, never from the command line — an argument lands in
# the process list where any user on the box can read it.
env_get() { sed -n "s/^$1=//p" .env | tail -1 | tr -d '"'"'"'\r'; }
DB_NAME="$(env_get DB_DATABASE)"; DB_USER="$(env_get DB_USERNAME)"
DB_PASS="$(env_get DB_PASSWORD)"; DB_HOST="$(env_get DB_HOST)"; DB_PORT="$(env_get DB_PORT)"
[[ -n "$DB_NAME" ]] || die "DB_DATABASE not readable from .env"

SQL="$DEST/masar-$STAMP.sql.gz"

# The dump needs about three times the last one, and never less than 64 MB.
LAST_SQL="$(ls -t "$DEST"/masar-*.sql.gz 2>/dev/null | head -1 || true)"
NEED_SQL_MB=64
if [[ -n "$LAST_SQL" ]]; then
  NEED_SQL_MB=$(( ($(wc -c < "$LAST_SQL") * 3) / 1048576 + 64 ))
fi
has_room "$NEED_SQL_MB" || die "not enough space in $DEST for the dump: $(free_mb) MB free, need $NEED_SQL_MB MB plus $MIN_FREE_MB MB spare"

log "dumping $DB_NAME"
PARTIALS+=("$SQL.partial")
MYSQL_PWD="$DB_PASS" mysqldump \
  --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --user="$DB_USER" \
  --single-transaction --quick --routines --events --default-character-set=utf8mb4 \
  "$DB_NAME" | gzip -9 > "$SQL.partial"

# A dump that cannot be decompressed is not a backup either.
gzip -t "$SQL.partial" || die "dump failed its integrity check: $SQL"
mv -- "$SQL.partial" "$SQL"

# What the media disk holds: path, size and mtime of every file. Equal
# fingerprints mean an archive taken then still restores today's files.
media_fingerprint() {
  if stat --version >/dev/null 2>&1; then
    find storage/app/public -type f -exec stat -c '%n %s %Y' {} + 2>/dev/null
  else
    find storage/app/public -type f -exec stat -f '%N %z %m' {} + 2>/dev/null
  fi | LC_ALL=C sort | { sha256sum 2>/dev/null || shasum -a 256; } | cut -d' ' -f1
}

MEDIA=""; MEDIA_FP=""; MEDIA_NOTE="none"
if [[ -d storage/app/public && "${MASAR_BACKUP_SKIP_MEDIA:-0}" != "1" ]]; then
  MEDIA_FP="$(media_fingerprint)"
  # The newest complete set with the same fingerprint and its archive on disk.
  for m in $(ls -t "$DEST"/masar-*.manifest 2>/dev/null); do
    prev="$(sed -n 's/^media=//p' "$m")"
    if [[ "$(sed -n 's/^media_fingerprint=//p' "$m")" == "$MEDIA_FP" && -n "$prev" && "$prev" != "none" && -f "$DEST/$prev" ]]; then
      MEDIA="$DEST/$prev"; MEDIA_NOTE="reused"
      log "media unchanged since $prev: not archived again"
      break
    fi
  done
  if [[ -z "$MEDIA" ]]; then
    NEED_MEDIA_MB=$(( $(du -sk storage/app/public | cut -f1) / 1024 + 64 ))
    if has_room "$NEED_MEDIA_MB"; then
      MEDIA="$DEST/masar-media-$STAMP.tar.gz"
      log "archiving the local media disk"
      PARTIALS+=("$MEDIA.partial")
      tar -czf "$MEDIA.partial" -C storage/app public
      gzip -t "$MEDIA.partial" || die "media archive failed its integrity check"
      mv -- "$MEDIA.partial" "$MEDIA"; MEDIA_NOTE="new"
    elif [[ "${MASAR_BACKUP_REQUIRE_MEDIA:-0}" == "1" ]]; then
      die "not enough space for the media archive: $(free_mb) MB free, need $NEED_MEDIA_MB MB plus $MIN_FREE_MB MB spare"
    else
      warn "skipping media: $(free_mb) MB free, need $NEED_MEDIA_MB MB plus $MIN_FREE_MB MB spare. The database dump is complete."
      MEDIA_NOTE="skipped-no-space"
    fi
  fi
fi

TABLES="$(MYSQL_PWD="$DB_PASS" mysql --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" \
  --user="$DB_USER" --silent --skip-column-names \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")"

MANIFEST_FILE="$DEST/masar-$STAMP.manifest"
PARTIALS+=("$MANIFEST_FILE.partial")
cat > "$MANIFEST_FILE.partial" <<MANIFEST
taken_at=$STAMP
database=$DB_NAME
tables=$TABLES
sql=$(basename "$SQL")
sql_bytes=$(wc -c < "$SQL" | tr -d ' ')
media=$(basename "${MEDIA:-none}")
media_status=$MEDIA_NOTE
media_fingerprint=$MEDIA_FP
git=$(git rev-parse --short HEAD 2>/dev/null || echo unknown)
MANIFEST

# Exact row counts, one line per table, so verify-backup.sh can prove a
# restore holds what was dumped. COUNT(*), not information_schema.table_rows,
# which is an estimate for InnoDB.
log "recording row counts"
for t in $(MYSQL_PWD="$DB_PASS" mysql --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --user="$DB_USER" \
    --silent --skip-column-names -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_type='BASE TABLE' ORDER BY table_name"); do
  n="$(MYSQL_PWD="$DB_PASS" mysql --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --user="$DB_USER" \
    --silent --skip-column-names -e "SELECT COUNT(*) FROM \`$DB_NAME\`.\`$t\`")"
  printf 'rows.%s=%s\n' "$t" "$n" >> "$MANIFEST_FILE.partial"
done
mv -- "$MANIFEST_FILE.partial" "$MANIFEST_FILE"

# Prune complete sets only, never the newest, and a media archive only once
# no remaining manifest refers to it. Partials a crashed run left behind go
# after a day.
log "pruning complete backups older than $KEEP_DAYS days"
NEWEST="$(ls -t "$DEST"/masar-*.manifest 2>/dev/null | head -1)"
for m in $(find "$DEST" -name 'masar-*.manifest' -type f -mtime "+$KEEP_DAYS"); do
  [[ "$m" == "$NEWEST" ]] && continue
  sql="$(sed -n 's/^sql=//p' "$m")"
  [[ -n "$sql" ]] && rm -f -- "$DEST/$sql"
  rm -f -- "$m"
done
for a in "$DEST"/masar-media-*.tar.gz; do
  [[ -f "$a" ]] || continue
  grep -qx "media=$(basename "$a")" "$DEST"/masar-*.manifest 2>/dev/null || { log "removing unreferenced $(basename "$a")"; rm -f -- "$a"; }
done
find "$DEST" -name 'masar-*.partial' -type f -mtime +1 -delete

log "done: $SQL ($TABLES tables), media: $MEDIA_NOTE, $(free_mb) MB free"
