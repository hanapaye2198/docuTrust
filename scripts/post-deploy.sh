#!/usr/bin/env bash

set -euo pipefail

RELEASE_PATH="${1:?release path is required}"
PHP_BIN="${2:-/usr/bin/php}"

cd "$RELEASE_PATH"

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
