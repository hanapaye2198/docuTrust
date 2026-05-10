#!/usr/bin/env bash

set -euo pipefail

APP_BASE_PATH="${APP_BASE_PATH:-/var/www/docutrust}"
ARTIFACT_PATH="${ARTIFACT_PATH:?ARTIFACT_PATH must be set}"
RELEASE_SHA="${RELEASE_SHA:?RELEASE_SHA must be set}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-$WEB_USER}"

RELEASES_PATH="$APP_BASE_PATH/releases"
SHARED_PATH="$APP_BASE_PATH/shared"
RELEASE_PATH="$RELEASES_PATH/$RELEASE_SHA"
CURRENT_PATH="$APP_BASE_PATH/current"

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
  echo "Run deployment as root or configure passwordless sudo for the deploy user." >&2
  exit 1
}

fix_laravel_permissions() {
  mkdir -p "$SHARED_PATH/storage/logs" "$RELEASE_PATH/bootstrap/cache"

  run_privileged chown -R "$WEB_USER:$WEB_GROUP" "$SHARED_PATH/storage" "$RELEASE_PATH/bootstrap/cache"
  run_privileged find "$SHARED_PATH/storage" "$RELEASE_PATH/bootstrap/cache" -type d -exec chmod 2775 {} \;
  run_privileged find "$SHARED_PATH/storage" "$RELEASE_PATH/bootstrap/cache" -type f -exec chmod 664 {} \;
}

mkdir -p "$RELEASES_PATH" "$SHARED_PATH/storage" "$SHARED_PATH/bootstrap/cache"

if [ -d "$RELEASE_PATH" ]; then
  rm -rf "$RELEASE_PATH"
fi

mkdir -p "$RELEASE_PATH"
tar -xzf "$ARTIFACT_PATH" -C "$RELEASE_PATH"

mkdir -p \
  "$SHARED_PATH/storage/app" \
  "$SHARED_PATH/storage/app/public" \
  "$SHARED_PATH/storage/framework/cache" \
  "$SHARED_PATH/storage/framework/sessions" \
  "$SHARED_PATH/storage/framework/views" \
  "$SHARED_PATH/storage/logs"

if [ ! -f "$SHARED_PATH/.env" ]; then
  cp "$RELEASE_PATH/.env.example" "$SHARED_PATH/.env"
fi

rm -rf "$RELEASE_PATH/storage"
ln -sfn "$SHARED_PATH/storage" "$RELEASE_PATH/storage"
ln -sfn "$SHARED_PATH/.env" "$RELEASE_PATH/.env"

mkdir -p "$RELEASE_PATH/public"
rm -rf "$RELEASE_PATH/public/storage"
ln -sfn "$SHARED_PATH/storage/app/public" "$RELEASE_PATH/public/storage"

export APP_ENV=production
export WEB_USER WEB_GROUP

fix_laravel_permissions
bash "$RELEASE_PATH/scripts/post-deploy.sh" "$RELEASE_PATH" "$PHP_BIN"
fix_laravel_permissions

ln -sfn "$RELEASE_PATH" "$CURRENT_PATH"

if command -v systemctl >/dev/null 2>&1; then
  run_privileged systemctl reload php8.4-fpm || true
  run_privileged systemctl restart docutrust-queue.service || true
  run_privileged systemctl restart docutrust-blockchain.service || true
fi

rm -f "$ARTIFACT_PATH"

CURRENT_TARGET="$(readlink -f "$CURRENT_PATH" 2>/dev/null || true)"

mapfile -t OLD_RELEASES < <(
  find "$RELEASES_PATH" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
    | sort -nr \
    | awk -v keep="$KEEP_RELEASES" 'NR > keep { $1=""; sub(/^ /, ""); print }'
)

for old_release in "${OLD_RELEASES[@]}"; do
  if [ -n "$CURRENT_TARGET" ] && [ "$old_release" = "$CURRENT_TARGET" ]; then
    continue
  fi

  rm -rf "$old_release"
done
