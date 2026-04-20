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
        # Try graceful shutdown first (by killing the parent)
        kill "${SERVER_PID}" 2>/dev/null || true
        # Wait a bit for graceful exit
        sleep 0.5
        # Force kill any remaining children if the parent process group is still alive
        kill -9 -"${SERVER_PID}" 2>/dev/null || true
    fi
    # Final safety: Ensure no processes are holding the port
    if command -v lsof >/dev/null 2>&1; then
        LSOF_PID=$(lsof -t -i:"${PORT}" || true)
        if [ -n "${LSOF_PID}" ]; then
            kill -9 ${LSOF_PID} 2>/dev/null || true
        fi
    fi
    echo ""
    info "Benchmarks completed."
}
trap cleanup EXIT

JSON_MODE=0
if [[ "${1:-}" == "--json" ]]; then JSON_MODE=1; fi

if command -v wrk >/dev/null 2>&1; then TOOL="wrk"; else TOOL="ab"; fi

if [ "${JSON_MODE}" -eq 0 ]; then
    header "Quill Binary Benchmark"
    info "Server     : Quill Core"
    info "Tool       : ${TOOL}"
    info "Duration   : ${DURATION}s"
    info "Connections: ${CONNECTIONS}"
    info "Workers    : ${THREADS}"
fi

# Start server
SERVER_LOG="/tmp/quill-server.log"
QUILL_WORKERS="${THREADS}" QUILL_CORE_BINARY="${QUILL_CORE_BINARY:-}" QUILL_CORE_HEADER="${QUILL_CORE_HEADER:-}" APP_ENV="${APP_ENV:-bench}" QUILL_RUNTIME="${QUILL_RUNTIME:-rust}" "${PHP}" ${PHP_OPTS} -d ffi.enable=on bin/quill serve --port="${PORT}" > "${SERVER_LOG}" 2>&1 &
SERVER_PID=$!

# Wait for first worker to answer
READY=0
for _ in $(seq 1 20); do
    if curl -s "http://${HOST}:${PORT}/health" >/dev/null 2>&1; then READY=1; break; fi
    sleep 0.5
done

if [ "${READY}" -eq 0 ]; then
    if [ "${JSON_MODE}" -eq 0 ]; then warn "Server failed to start."; fi
    exit 1
fi

if [ "${THREADS}" -gt 1 ]; then sleep 1; fi
if [ "${JSON_MODE}" -eq 0 ]; then info "Server is ready. Running benchmarks..."; fi

# Results
if [ "${TOOL}" = "wrk" ]; then
    RESULT=$(wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}s" --latency "http://${HOST}:${PORT}/hello")
    if [ "${JSON_MODE}" -eq 1 ]; then
        RPS=$(echo "$RESULT" | grep "Requests/sec:" | awk '{print $2}')
        LATENCY=$(echo "$RESULT" | grep "Latency" | grep "99%" | awk '{print $2}')
        echo "{\"rps\": $RPS, \"p99_latency\": \"$LATENCY\", \"tool\": \"wrk\"}"
    else
        echo "$RESULT" | sed 's/^/    /'
    fi
else
    RESULT=$(ab -t "${DURATION}" -n 1000000 -c "${CONNECTIONS}" -q "http://${HOST}:${PORT}/hello")
    if [ "${JSON_MODE}" -eq 1 ]; then
        RPS=$(echo "$RESULT" | grep "Requests per second:" | awk '{print $4}')
        echo "{\"rps\": $RPS, \"tool\": \"ab\"}"
    else
        echo "$RESULT" | grep -E "^(Requests per second|Complete requests|Failed|Percentage of the requests)" | sed 's/^/    /'
    fi
fi
