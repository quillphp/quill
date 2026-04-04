#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../rust"

CARGO_BIN="$HOME/.cargo/bin/cargo"
if ! command -v $CARGO_BIN &> /dev/null; then
    CARGO_BIN="cargo"
fi

$CARGO_BIN build --release
mkdir -p ../build
if [ -f target/release/libquill_core.dylib ]; then
    cp target/release/libquill_core.dylib ../build/libquill.so
elif [ -f target/release/libquill_core.so ]; then
    cp target/release/libquill_core.so ../build/libquill.so
else
    echo "Compiled library not found!"
    exit 1
fi
echo "Built: ../build/libquill.so"
