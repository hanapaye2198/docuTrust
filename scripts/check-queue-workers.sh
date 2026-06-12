#!/usr/bin/env bash

set -euo pipefail

if ! command -v systemctl >/dev/null 2>&1; then
  echo "systemctl is required" >&2
  exit 1
fi

mapfile -t QUEUE_UNITS < <(
  systemctl list-units 'docutrust-queue@*' --all --no-legend --no-pager 2>/dev/null \
    | awk '{print $1}' \
    | sort
)

UNITS=("${QUEUE_UNITS[@]}" "docutrust-blockchain" "docutrust-reverb")

for unit in "${UNITS[@]}"; do
  [ -n "$unit" ] || continue
  systemctl --no-pager --full status "$unit" || true
  echo
done
