#!/bin/sh
# Integrationstests gegen einen eigenen, frischen Docker-Stack (Port 8088, eigenes DB-Volume).
# Die lokale App auf Port 8087 und ihre Daten bleiben unberührt.
set -eu
cd "$(dirname "$0")/.."
if [ "${1:-mysql}" = "pgsql" ]; then
  export COMPOSE_FILE=compose.postgres.yaml COMPOSE_PROJECT_NAME=kittchenapp-pg-test APP_PORT=8090 DB_DRIVER=pgsql
elif [ "${1:-mysql}" = "mysql" ]; then
  export COMPOSE_FILE=compose.yaml COMPOSE_PROJECT_NAME=kittchenapp-test APP_PORT=8088 DB_DRIVER=mysql
else
  echo "Verwendung: sh tests/run.sh [mysql|pgsql]" >&2; exit 2
fi
export APP_BIND=127.0.0.1 MENU_SEED=test
docker compose down -v --remove-orphans >/dev/null 2>&1 || true
trap 'docker compose down -v --remove-orphans >/dev/null 2>&1' EXIT
docker compose up -d --build --wait
docker compose exec -T app php /var/www/html/api/migrate.php
TEST_URL=http://127.0.0.1:$APP_PORT python3 tests/integration.py

if [ "$DB_DRIVER" = "pgsql" ]; then
  # Separate fresh production-mode database in the disposable test volume.
  docker compose exec -T db createdb -U kitchen kitchen_production_test
  for pass in 1 2; do
    docker compose exec -T -e APP_ENV=production -e DB_NAME=kitchen_production_test -e MENU_SEED=speisekarte app php api/migrate.php
  done
  docker compose exec -T -e APP_ENV=production -e DB_NAME=kitchen_production_test app php api/check-environment.php
  docker compose exec -T -e APP_ENV=production -e DB_NAME=kitchen_production_test app php < tests/postgres-production.php
fi
