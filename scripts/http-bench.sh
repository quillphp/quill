#!/usr/bin/env bash
# =============================================================================
# Quill -- Real HTTP Benchmark Runner (Hardened)
# =============================================================================
#
# Two modes:
#
#   HTTP/1.1 (default)  -- PHP built-in server + wrk / ab
#     bin/bench-run
#
#   HTTP/2 production   -- FrankenPHP Docker (worker mode) + h2load
#     DOCKER=1 bin/bench-run
#
# Options:
#   DURATION      Seconds per test (default: 10)
#   CONNECTIONS   Concurrent connections (default: 100)
#   THREADS       Worker threads (default: 4)
# =============================================================================

set -euo pipefail

# Config
PHP="${PHP:-php}"
HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8765}"
DOCKER="${DOCKER:-0}"
DOCKER_PORT="${DOCKER_PORT:-8443}"
BENCH_PORT="${BENCH_PORT:-8080}"
DURATION="${DURATION:-10}"
CONNECTIONS="${CONNECTIONS:-100}"
STREAMS="${STREAMS:-10}"
THREADS="${THREADS:-4}"

INFRA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.github/infra" && pwd)"
SERVER_SCRIPT="${INFRA_DIR}/bench_server.php"
COMPOSE_FILE="${INFRA_DIR}/docker-compose.bench.yml"
TMP_POST_BODY="$(mktemp /tmp/quill_bench_post.XXXXXX)"
SERVER_PID=""
DOCKER_STARTED=0

# Colours
BOLD="\033[1m"
GREEN="\033[0;32m"
CYAN="\033[0;36m"
YELLOW="\033[1;33m"
RESET="\033[0m"

header()  { echo -e "\n${BOLD}${CYAN}$*${RESET}"; }
info()    { echo -e "  ${GREEN}>${RESET} $*"; }
warn()    { echo -e "  ${YELLOW}!${RESET}  $*"; }
divider() { echo "  ---------------------------------------------"; }

# Cleanup
cleanup() {
    rm -f "${TMP_POST_BODY}"
    if [ -n "${SERVER_PID}" ]; then
        kill "${SERVER_PID}" 2>/dev/null || true
    fi
    if [ "${DOCKER_STARTED}" = "1" ]; then
        echo ""
        info "Stopping FrankenPHP container..."
        docker compose -f "${COMPOSE_FILE}" down --remove-orphans >/dev/null 2>&1 || true
    fi
    echo ""
    info "Done."
}
trap cleanup EXIT

# POST body
printf '%s' '{"email":"bench@quillphp.com","name":"Quill Bench","age":25}' > "${TMP_POST_BODY}"

# Mode select
if [ "${DOCKER}" = "1" ]; then
    TOOL="h2load"
    BASE_URL="http://localhost:${BENCH_PORT}"
    HEALTH_URL="https://localhost:${DOCKER_PORT}/health"
else
    BASE_URL="http://${HOST}:${PORT}"
    HEALTH_URL="${BASE_URL}/health"
    if command -v wrk >/dev/null 2>&1; then TOOL="wrk"; else TOOL="ab"; fi
fi

header "Quill Benchmark Runner"
info "Mode       : $( [ "${DOCKER}" = "1" ] && echo "FrankenPHP (HTTP/2)" || echo "PHP Built-in (HTTP/1.1)" )"
info "Tool       : ${TOOL}"
info "Duration   : ${DURATION}s"
info "Connections: ${CONNECTIONS}"

# Start server
if [ "${DOCKER}" = "1" ]; then
    info "Starting FrankenPHP..."
    docker compose -f "${COMPOSE_FILE}" up -d --build >/dev/null 2>&1
    DOCKER_STARTED=1
else
    info "Starting PHP server..."
    PHP_CLI_SERVER_WORKERS="${THREADS}" "${PHP}" -S "${HOST}:${PORT}" "${SERVER_SCRIPT}" >/dev/null 2>&1 &
    SERVER_PID=$!
fi

# Wait for ready
READY=0
for _ in $(seq 1 40); do
    if curl -s -k "${HEALTH_URL}" >/dev/null 2>&1; then READY=1; break; fi
    sleep 0.5
done

if [ "${READY}" -eq 0 ]; then warn "Server failed to start."; exit 1; fi
info "Server is ready. Running benchmarks..."

# Results
if [ "${TOOL}" = "h2load" ]; then
    h2load -c "${CONNECTIONS}" -t "${THREADS}" -m "${STREAMS}" -D "${DURATION}" "${BASE_URL}/hello" | grep -E "(req/s|requests:|status codes:)" | sed 's/^/    /'
    h2load -c "${CONNECTIONS}" -t "${THREADS}" -m "${STREAMS}" -D "${DURATION}" -d "${TMP_POST_BODY}" -H "Content-Type: application/json" "${BASE_URL}/echo" | grep -E "(req/s|requests:|status codes:)" | sed 's/^/    /'
elif [ "${TOOL}" = "wrk" ]; then
    wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}s" "${BASE_URL}/hello" | sed 's/^/    /'
else
    ab -n 50000 -c "${CONNECTIONS}" -q "${BASE_URL}/hello" | grep -E "^(Requests per second|Complete requests|Failed)" | sed 's/^/    /'
fi
