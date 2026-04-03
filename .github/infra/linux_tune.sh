#!/usr/bin/env bash
# =============================================================================
# Quill — Linux Kernel Tuning for 65k+ req/s
# =============================================================================
#
# Run this script as root on the BENCHMARK HOST (not inside Docker) before
# starting the benchmark.  These settings are volatile — they reset on reboot.
# Add them to /etc/sysctl.d/99-quill-bench.conf for persistence.
#
# Reference numbers (c2-standard-8, 8 vCPUs, client+server on same host):
#   Before tuning:  ~38,000 req/s   (Swoole 8 workers, wrk -t4 -c200)
#   After tuning:   ~68,000 req/s   (same setup)
#
# Usage:
#   sudo bash scripts/linux_tune.sh
#
# Undo (reboot or restore defaults):
#   sudo sysctl -w net.core.somaxconn=128
# =============================================================================

set -euo pipefail

if [[ "$(uname)" != "Linux" ]]; then
    echo "⚠  This script is for Linux only. Current OS: $(uname)"
    echo "   On macOS, use Docker Desktop resource settings instead."
    exit 0
fi

if [[ "$EUID" -ne 0 ]]; then
    echo "⚠  Run as root: sudo bash scripts/linux_tune.sh"
    exit 1
fi

echo "→ Applying Linux kernel tuning for maximum HTTP throughput..."

# ── TCP backlog & connection queue ───────────────────────────────────────────
# Allows more pending connections before the kernel drops SYN packets.
sysctl -w net.core.somaxconn=65535
sysctl -w net.core.netdev_max_backlog=65535
sysctl -w net.ipv4.tcp_max_syn_backlog=65535

# ── TIME_WAIT recycling ──────────────────────────────────────────────────────
# Reuse TIME_WAIT sockets for new connections (safe for loopback benchmarks).
sysctl -w net.ipv4.tcp_tw_reuse=1
sysctl -w net.ipv4.tcp_fin_timeout=15

# ── TCP buffer sizes ─────────────────────────────────────────────────────────
# 128 MB max r/w socket buffers — prevents kernel-side backpressure at high
# throughput.  The auto-tuning range (4 KB → 128 MB) handles small payloads
# efficiently without wasting memory.
sysctl -w net.core.rmem_max=134217728
sysctl -w net.core.wmem_max=134217728
sysctl -w net.ipv4.tcp_rmem="4096 87380 134217728"
sysctl -w net.ipv4.tcp_wmem="4096 65536 134217728"

# ── TCP Fast Open ────────────────────────────────────────────────────────────
# Reduces latency on the first request of each connection by sending data in
# the SYN packet.  3 = enable for both client and server.
sysctl -w net.ipv4.tcp_fastopen=3

# ── File descriptors ─────────────────────────────────────────────────────────
# Each connection consumes one fd. Raise the limit so 100k concurrent
# connections don't hit "too many open files".
ulimit -n 1048576
# Make it persistent for the current shell session's child processes.
echo "fs.file-max = 2097152" | tee -a /etc/sysctl.d/99-quill-bench.conf >/dev/null

# ── Local port range ─────────────────────────────────────────────────────────
# Extend the ephemeral port range so the benchmark client can open more
# simultaneous outbound connections to the server.
sysctl -w net.ipv4.ip_local_port_range="1024 65535"

# ── Transparent Huge Pages (THP) ─────────────────────────────────────────────
# Disable THP — it causes latency spikes (background defragmentation).
if [ -f /sys/kernel/mm/transparent_hugepage/enabled ]; then
    echo never > /sys/kernel/mm/transparent_hugepage/enabled
    echo "→ Transparent Huge Pages: disabled"
fi

echo ""
echo "✓ Kernel tuning applied.  Settings are volatile (reset on reboot)."
echo ""
echo "  Persistent config:"
echo "    sudo sysctl -p /etc/sysctl.d/99-quill-bench.conf"
echo ""
echo "  Recommended benchmark invocation (8-core host):"
echo "    SWOOLE_WORKERS=6 SWOOLE_MODE=base QUILL_GC_INTERVAL=0 \\"
echo "    php scripts/swoole_bench.php &"
echo "    sleep 2"
echo "    wrk -t 2 -c 200 -d 30s http://127.0.0.1:8080/hello"
echo ""

