#!/usr/bin/env bash
#
# First-run provisioning for a bare Ubuntu 24.04 box. Idempotent.
# Run as root:  bash deploy/provision.sh
#
set -Eeuo pipefail

APP_USER="${MASAR_USER:-masar}"
APP_DIR="${MASAR_APP_DIR:-/var/www/masar}"

log() { printf '\033[1;32m==>\033[0m %s\n' "$*"; }
die() { printf '\033[1;31mxxx\033[0m %s\n' "$*" >&2; exit 1; }

[[ "$(id -u)" == "0" ]] || die "run as root"
. /etc/os-release
[[ "${VERSION_ID:-}" == "24.04" ]] || die "expected Ubuntu 24.04, found ${VERSION_ID:-unknown}"

log "base packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq software-properties-common curl git unzip ufw

# PHP 8.4 is not in Ubuntu 24.04's own repos; ondrej/php carries it.
# 8.3 is NOT an option: spatie/laravel-activitylog requires ^8.4 and
# composer.json pins the platform to exactly 8.4.1.
log "php 8.4"
add-apt-repository -y ppa:ondrej/php >/dev/null
apt-get update -qq
apt-get install -y -qq \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-redis php8.4-gd php8.4-intl \
  php8.4-mbstring php8.4-xml php8.4-zip php8.4-curl php8.4-bcmath php8.4-opcache

php8.4 -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' \
  || die "php is older than 8.4.1"

log "mysql 8, redis, nginx, supervisor"
# MySQL, not MariaDB — MariaDB has no native JSON type and the schema needs it.
apt-get install -y -qq mysql-server redis-server nginx supervisor

log "image optimiser binaries"
apt-get install -y -qq jpegoptim optipng pngquant gifsicle webp

log "composer"
if ! command -v composer >/dev/null; then
  curl -sS https://getcomposer.org/installer | php8.4 -- --install-dir=/usr/local/bin --filename=composer
fi

log "application user and directories"
id -u "$APP_USER" >/dev/null 2>&1 || adduser --system --group --home "$APP_DIR" --shell /bin/bash "$APP_USER"
mkdir -p "$APP_DIR" /var/log/php /var/backups/masar /run/php
chown -R "$APP_USER:$APP_USER" "$APP_DIR" /var/backups/masar
chown -R www-data:www-data /var/log/php

log "firewall: 22, 80, 443 and nothing else"
ufw --force reset >/dev/null
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ufw status verbose

log "secrets"
# Never in the repo, never in a transcript. Read from a 600 file on the box and
# appended to .env by the operator — this script only checks they are present.
SECRETS="${MASAR_SECRETS:-/root/masar-secrets.env}"
if [[ -f "$SECRETS" ]]; then
  perms="$(stat -c '%a' "$SECRETS")"
  [[ "$perms" == "600" ]] || die "$SECRETS is mode $perms — must be 600"
  log "secrets file present and 600"
else
  log "no $SECRETS yet — media will stay on the local disk until R2 keys are in place"
fi

log "service configs"
install -m 644 "$APP_DIR/deploy/php-fpm/masar.conf"        /etc/php/8.4/fpm/pool.d/masar.conf
rm -f /etc/php/8.4/fpm/pool.d/www.conf
install -m 644 "$APP_DIR/deploy/nginx/masar.conf"          /etc/nginx/sites-available/masar
ln -sf /etc/nginx/sites-available/masar /etc/nginx/sites-enabled/masar
rm -f /etc/nginx/sites-enabled/default
install -m 644 "$APP_DIR/deploy/supervisor/masar-horizon.conf" /etc/supervisor/conf.d/masar-horizon.conf

nginx -t || die "nginx config invalid"

log "redis: cache tags need a real eviction policy, not noeviction on a full box"
sed -i 's/^# *maxmemory-policy .*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf || true

systemctl enable --now php8.4-fpm redis-server nginx supervisor
systemctl restart php8.4-fpm nginx
supervisorctl reread && supervisorctl update

log "nightly backup at 03:00"
cat > /etc/cron.d/masar-backup <<CRON
0 3 * * * $APP_USER cd $APP_DIR && bash deploy/backup.sh >> /var/log/masar-backup.log 2>&1
CRON
chmod 644 /etc/cron.d/masar-backup

log "provisioned. next: clone the repo into $APP_DIR, write .env, run deploy/deploy.sh"
