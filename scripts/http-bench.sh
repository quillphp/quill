#!/usr/bin/env bash
# =============================================================================
# Quill -- Native Binary HTTP Benchmark Runner
# =============================================================================
#
# Usage:
#   bin/bench-run
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
PORT="${PORT:-8080}"
DURATION="${DURATION:-10}"
CONNECTIONS="${CONNECTIONS:-100}"
THREADS="${THREADS:-4}"

SERVER_PID=""

# Colours
BOLD="\033[1m"
GREEN="\033[0;32m"
CYAN="\033[0;36m"
YELLOW="\033[1;33m"
RESET="\033[0m"

header()  { echo -e "\n${BOLD}${CYAN}$*${RESET}"; }
info()    { echo -e "  ${GREEN}>${RESET} $*"; }
warn()    { echo -e "  ${YELLOW}!${RESET}  $*"; }

# Cleanup
cleanup() {
    if [ -n "${SERVER_PID}" ]; then
        kill "${SERVER_PID}" 2>/dev/null || true
    fi
    echo ""
    info "Benchmarks completed."
}
trap cleanup EXIT

if command -v wrk >/dev/null 2>&1; then TOOL="wrk"; else TOOL="ab"; fi

header "Quill Binary Benchmark"
info "Server     : Quill Core"
info "Tool       : ${TOOL}"
info "Duration   : ${DURATION}s"
info "Connections: ${CONNECTIONS}"
info "Workers    : ${THREADS}"

# Start server
# APP_ENV=bench disables the usleep(100) idle yield inside the PHP poll loop,
# keeping it hot under load.
info "Starting Quill server..."
SERVER_LOG="/tmp/quill-server.log"
QUILL_WORKERS="${THREADS}" QUILL_CORE_BINARY="${QUILL_CORE_BINARY:-}" QUILL_CORE_HEADER="${QUILL_CORE_HEADER:-}" APP_ENV="${APP_ENV:-bench}" QUILL_RUNTIME="${QUILL_RUNTIME:-rust}" "${PHP}" -d ffi.enable=on bin/quill serve --port="${PORT}" > "${SERVER_LOG}" 2>&1 &
SERVER_PID=$!

# Wait for first worker to answer
READY=0
for _ in $(seq 1 20); do
    if curl -s "http://${HOST}:${PORT}/hello" >/dev/null 2>&1; then READY=1; break; fi
    sleep 0.5
done

if [ "${READY}" -eq 0 ]; then
    warn "Server failed to start. Last logs:"
    tail -n 20 "${SERVER_LOG}" | sed 's/^/    /'
    exit 1
fi

# When running multiple workers give the remaining processes time to bind the
# port and enter their poll loops before we start hammering them.
if [ "${THREADS}" -gt 1 ]; then
    sleep 1
fi
info "Server is ready. Running benchmarks..."

# Results — use time-based tests so the full ${DURATION}s window is exercised
# regardless of how fast the server is.
if [ "${TOOL}" = "wrk" ]; then
    wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}s" "http://${HOST}:${PORT}/hello" | sed 's/^/    /'
else
    ab -t "${DURATION}" -n 1000000 -c "${CONNECTIONS}" -q "http://${HOST}:${PORT}/hello" \
        | grep -E "^(Requests per second|Complete requests|Failed)" | sed 's/^/    /'
fi
