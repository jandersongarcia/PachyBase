#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SETUP_SCRIPT="$ROOT_DIR/scripts/setup.sh"

if [[ ! -f "$SETUP_SCRIPT" ]]; then
  echo "Setup script not found: $SETUP_SCRIPT" >&2
  exit 1
fi

bash "$SETUP_SCRIPT"
