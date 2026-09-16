#!/bin/sh
set -eu

cd "$(dirname "$0")/.."
docker compose run --rm -T app composer check
docker compose run --rm -T node npm run check
