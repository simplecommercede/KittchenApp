#!/bin/sh
# Integrationstests gegen einen eigenen, frischen Docker-Stack (Port 8088, eigenes DB-Volume).
# Die lokale App auf Port 8087 und ihre Daten bleiben unberührt.
set -eu
cd "$(dirname "$0")/.."
export COMPOSE_PROJECT_NAME=kittchenapp-test APP_PORT=8088 MENU_SEED=test
docker compose down -v --remove-orphans >/dev/null 2>&1 || true
trap 'docker compose down -v --remove-orphans >/dev/null 2>&1' EXIT
docker compose up -d --build --wait
docker compose exec -T app php /var/www/html/api/migrate.php
TEST_URL=http://127.0.0.1:8088 python3 tests/integration.py
