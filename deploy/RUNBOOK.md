# MASAR — operations runbook

For whoever runs the server. Every command runs from `/var/www/masar` as the
deploy user unless it says otherwise. Production deploys need the owner's go-ahead.

## Deploy

    deploy/deploy.sh <git-ref>

Fail-fast (`set -Eeuo pipefail`) and safe to re-run. It:
1. checks `.env` (APP_DEBUG must be false);
2. fetches and resets to the ref;
3. refuses without the committed asset build;
4. installs Composer dependencies;
5. puts the site in maintenance, migrates (`--isolated`) and brings it back up;
6. caches routes, views and events — not config (see DEFERRED.md);
7. syncs the translation review register;
8. clears the cache;
9. terminates Horizon so Supervisor restarts it on the new code;
10. validates nginx before reloading it;
11. ends with `masar:smoke`, which reads the rendered site.

## Why config is not cached

`config:cache` stops `.env` being read at runtime, so `env()` returns null outside
`config/`. `bootstrap/app.php` reads `TRUSTED_PROXIES` that way: with config cached,
trusted proxies would silently disappear and every reader behind Cloudflare would
share one rate-limit bucket. A changed `.env` would also do nothing until the next
cache. Routes, views and events are cached; config is not, and a test holds that.

## Cloudflare

- Point the domain at Cloudflare with the proxy on.
- Firewall the origin so only Cloudflare's ranges (https://www.cloudflare.com/ips/)
  reach ports 80/443.
- Then set `TRUSTED_PROXIES=*`. Without the firewall, list the ranges instead of `*`.
- Check: `ProductionConfigTest` proves one reader's burst does not throttle
  another arriving through the same proxy.
- Enable HSTS in `deploy/nginx/security-headers.conf` once TLS is verified on the
  real domain.

## Roll back a deploy

1. `deploy/deploy.sh <previous-ref>`: code and assets go back together.
2. If the bad release ran migrations: `php artisan migrate:rollback --step=N`,
   only for migrations whose `down()` is safe. Every migration since
   2026-10-28 has a working `down()`.
   - The twelve `widen_locale_*` migrations refuse to roll back while any row
     holds a locale longer than two characters (`ur-pk`, `zh-hans`). They
     refuse on purpose; narrowing would truncate those rows to `ur` and `zh`.
     Roll those back only by restoring a backup.
3. `php artisan cache:clear`, then `php artisan masar:decay`.

## Backups

- **Nightly:** cron runs `deploy/backup.sh` (installed by `provision.sh`). It
  writes a dump, a media archive when the media disk is local, and a manifest
  with exact row counts per table.
- **Verify a backup without touching production:**

      deploy/verify-backup.sh /var/backups/masar/masar-<STAMP>.sql.gz masar_restore_check

  It restores into the named scratch database, compares every table with the
  manifest, and drops the scratch database. It refuses the application's own
  database by name. Run it after the first backup on a new server, and
  monthly after that.
- **Restore for real:** `deploy/restore.sh <dump.sql.gz>`. It asks for
  confirmation, enters maintenance, imports, clears the cache (a raw import
  runs no application code), recounts, comes back up and runs the smoke check.

## Processes

Supervisor (`deploy/supervisor/masar-horizon.conf`) runs two processes:
- Horizon, the queue workers;
- `schedule:work`, the scheduler.

The Horizon dashboard is `super_admin` only, in every environment.

- **Check:** `supervisorctl status`
- **Restart after a config change:** `supervisorctl restart masar-horizon masar-scheduler`

## Editions and translation review

- **Which editions are public:** `MASAR_LOCALES_ENABLED` in `.env`. All six by
  default; Arabic is always on. Run `php artisan cache:clear` after changing it.
- **Interface review status:** `php artisan masar:translations:sync`
- **Record a review:** `php artisan masar:translations:approve <locale> <file> --reviewer=<email>`
  - The reviewer must be a real account with `translation.review`.
  - A file that changes afterwards returns to pending by itself.
- **Article translations:** these go through the newsroom workflow in the
  admin (Translate into… → edit → Sign off translation → publish). No outside
  translation service is configured, by ruling.

## Health

- `php artisan masar:decay`: queue, flows and forms, read from the rendered site.
- `php artisan masar:decay --with-seed`: also a fresh migrate and seed into a
  scratch database, about 15 minutes. Run it before a release with migrations.
