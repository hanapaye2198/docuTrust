#!/usr/bin/env bash

set -euo pipefail

RELEASE_PATH="${1:?release path is required}"
PHP_BIN="${2:-/usr/bin/php}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-$WEB_USER}"
RUN_SEEDERS="${RUN_SEEDERS:-true}"
SEEDER_CLASS="${SEEDER_CLASS:-Database\\Seeders\\DatabaseSeeder}"

cd "$RELEASE_PATH"

STORAGE_PATH="$(readlink -f storage 2>/dev/null || printf '%s' "$RELEASE_PATH/storage")"
CACHE_PATH="$RELEASE_PATH/bootstrap/cache"

run_privileged() {
  if [ "$(id -u)" -eq 0 ]; then
    "$@"
    return
  fi

  if command -v sudo >/dev/null 2>&1; then
    sudo "$@"
    return
  fi

  echo "Unable to run privileged command: $*" >&2
  echo "Run post-deploy as root or configure passwordless sudo for the deploy user." >&2
  exit 1
}

should_run_seeders() {
  case "${RUN_SEEDERS,,}" in
    1|true|yes|on) return 0 ;;
    *) return 1 ;;
  esac
}

# Ensure autoloader is up-to-date (classmap must include seeders)
if [ -f composer.phar ]; then
  "$PHP_BIN" composer.phar dump-autoload --optimize --no-interaction 2>/dev/null || true
elif command -v composer >/dev/null 2>&1; then
  composer dump-autoload --optimize --no-interaction 2>/dev/null || true
fi

"$PHP_BIN" artisan migrate --force

if should_run_seeders; then
  # Normalize double-backslashes that may arrive from shell escaping
  NORMALIZED_SEEDER="${SEEDER_CLASS//\\\\/\\}"
  "$PHP_BIN" artisan db:seed --force --class="$NORMALIZED_SEEDER"
fi

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

run_privileged chown -R "$WEB_USER:$WEB_GROUP" "$STORAGE_PATH" "$CACHE_PATH"
run_privileged find "$STORAGE_PATH" "$CACHE_PATH" -type d -exec chmod 2775 {} \;
run_privileged find "$STORAGE_PATH" "$CACHE_PATH" -type f -exec chmod 664 {} \;
