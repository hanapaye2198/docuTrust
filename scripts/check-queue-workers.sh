#!/usr/bin/env bash

set -euo pipefail

UNITS=(
  "docutrust-queue@default"
  "docutrust-queue@documents"
  "docutrust-queue@notifications"
  "docutrust-queue@einvoices"
  "docutrust-blockchain"
)

if ! command -v systemctl >/dev/null 2>&1; then
  echo "systemctl is required" >&2
  exit 1
fi

for unit in "${UNITS[@]}"; do
  systemctl --no-pager --full status "$unit" || true
  echo
done
