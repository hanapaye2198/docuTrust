#!/usr/bin/env bash

set -euo pipefail

APP_BASE_PATH="${APP_BASE_PATH:-/var/www/docutrust}"
ENV_FILE="${ENV_FILE:-$APP_BASE_PATH/shared/.env}"

if [ ! -f "$ENV_FILE" ]; then
  echo "Missing env file: $ENV_FILE" >&2
  exit 1
fi

if ! command -v redis-cli >/dev/null 2>&1; then
  echo "redis-cli is required" >&2
  exit 1
fi

read_env() {
  local key="$1"
  local value

  value="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d= -f2- || true)"
  value="${value%\"}"
  value="${value#\"}"
  printf '%s' "$value"
}

REDIS_HOST="${REDIS_HOST:-$(read_env REDIS_HOST)}"
REDIS_PORT="${REDIS_PORT:-$(read_env REDIS_PORT)}"
REDIS_PASSWORD="${REDIS_PASSWORD:-$(read_env REDIS_PASSWORD)}"
REDIS_DB="${REDIS_DB:-$(read_env REDIS_DB)}"
REDIS_PREFIX="${REDIS_PREFIX:-$(read_env REDIS_PREFIX)}"

DOC_QUEUE="${DOC_QUEUE:-$(read_env DOCUTRUST_QUEUE_DOCUMENTS)}"
NOTIFY_QUEUE="${NOTIFY_QUEUE:-$(read_env DOCUTRUST_QUEUE_NOTIFICATIONS)}"
EINV_QUEUE="${EINV_QUEUE:-$(read_env DOCUTRUST_QUEUE_EINVOICES)}"
DEFAULT_QUEUE="${DEFAULT_QUEUE:-$(read_env REDIS_QUEUE)}"

REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"
REDIS_DB="${REDIS_DB:-0}"
DEFAULT_QUEUE="${DEFAULT_QUEUE:-default}"
DOC_QUEUE="${DOC_QUEUE:-documents}"
NOTIFY_QUEUE="${NOTIFY_QUEUE:-notifications}"
EINV_QUEUE="${EINV_QUEUE:-einvoices}"

REDIS_ARGS=( -h "$REDIS_HOST" -p "$REDIS_PORT" -n "$REDIS_DB" )

if [ -n "$REDIS_PASSWORD" ] && [ "$REDIS_PASSWORD" != "null" ]; then
  REDIS_ARGS+=( -a "$REDIS_PASSWORD" )
fi

queue_length() {
  local queue_name="$1"
  local key="${REDIS_PREFIX}queues:${queue_name}"
  redis-cli "${REDIS_ARGS[@]}" LLEN "$key"
}

printf '%-18s %s\n' "queue" "depth"
printf '%-18s %s\n' "default" "$(queue_length "$DEFAULT_QUEUE")"
printf '%-18s %s\n' "documents" "$(queue_length "$DOC_QUEUE")"
printf '%-18s %s\n' "notifications" "$(queue_length "$NOTIFY_QUEUE")"
printf '%-18s %s\n' "einvoices" "$(queue_length "$EINV_QUEUE")"
