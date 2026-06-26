#!/usr/bin/env bash
#
# Spin up the ephemeral e2e stack, run the e2e PHPUnit suite against it over
# HTTP, then tear it down — leaving the dev stack untouched.
set -euo pipefail

cd "$(dirname "$0")/.."

PROJECT=clockwork-e2e
COMPOSE=(docker compose -p "$PROJECT" -f docker-compose.test.yml)
BASE_URL="http://localhost:8081"

cleanup() {
  echo "==> Tearing down test stack"
  "${COMPOSE[@]}" down -v >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "==> Starting ephemeral test stack (clockwork_test)"
"${COMPOSE[@]}" up --build -d

echo "==> Waiting for API health at ${BASE_URL}"
healthy=
for _ in $(seq 1 40); do
  if curl -sf "${BASE_URL}/api/health" >/dev/null 2>&1; then healthy=1; break; fi
  sleep 2
done
if [ -z "$healthy" ]; then
  echo "!! API did not become healthy" >&2
  "${COMPOSE[@]}" logs --tail 40 || true
  exit 1
fi

echo "==> Running e2e suite"
BASE_URL="$BASE_URL" \
DB_TEST_HOST=127.0.0.1 DB_TEST_PORT=3307 DB_TEST_NAME=clockwork_test \
DB_TEST_USER=clockwork DB_TEST_PASSWORD=test \
  ./vendor/bin/phpunit --testsuite e2e
