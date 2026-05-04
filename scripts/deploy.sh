#!/usr/bin/env bash

set -euo pipefail

APP_BASE_PATH="${APP_BASE_PATH:-/var/www/docutrust}"
ARTIFACT_PATH="${ARTIFACT_PATH:?ARTIFACT_PATH must be set}"
RELEASE_SHA="${RELEASE_SHA:?RELEASE_SHA must be set}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"

RELEASES_PATH="$APP_BASE_PATH/releases"
SHARED_PATH="$APP_BASE_PATH/shared"
RELEASE_PATH="$RELEASES_PATH/$RELEASE_SHA"
CURRENT_PATH="$APP_BASE_PATH/current"

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

bash "$RELEASE_PATH/scripts/post-deploy.sh" "$RELEASE_PATH" "$PHP_BIN"

ln -sfn "$RELEASE_PATH" "$CURRENT_PATH"

if command -v systemctl >/dev/null 2>&1; then
  systemctl reload php8.4-fpm || true
  systemctl restart docutrust-queue.service || true
  systemctl restart docutrust-blockchain.service || true
fi

rm -f "$ARTIFACT_PATH"

find "$RELEASES_PATH" -mindepth 1 -maxdepth 1 -type d | sort | head -n -"${KEEP_RELEASES}" 2>/dev/null | xargs -r rm -rf
