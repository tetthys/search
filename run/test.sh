#!/usr/bin/env bash
set -euo pipefail

# Run Pest tests fully inside Docker containers (php + composer).
# Usage:
#   bash ./run/test.sh
#   bash ./run/test.sh -- --filter=Search   # pass args to Pest

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PEST_ARGS=()
if [[ $# -gt 0 ]]; then
  if [[ "$1" == "--" ]]; then
    shift
    PEST_ARGS=("$@")
  else
    PEST_ARGS=("$@")
  fi
fi

# 1) Ensure dependencies installed with composer image
docker compose run --rm composer install --no-interaction --prefer-dist --ansi

# 2) Run Pest inside php container
CMD='
  set -euo pipefail
  if [ ! -x "./vendor/bin/pest" ]; then
    echo "Error: Pest binary not found." >&2
    exit 1
  fi
  ./vendor/bin/pest -vv --colors=always '"${PEST_ARGS[*]}"'
'

docker compose run --rm php bash -lc "$CMD"
