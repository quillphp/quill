# Performance Benchmarks

QuillPHP offloads HTTP serving, routing, and validation to a native Rust core (Axum + sonic-rs), eliminating the traditional PHP per-request overhead.

---

## Benchmark Environment

| Parameter | Value |
| :--- | :--- |
| Tool | `wrk` |
| Duration | 5 s |
| Connections | 50 |
| Workers | 2 (`QUILL_WORKERS=2`) |
| Runtime | Quill Binary Core (Rust / Axum) |
| PHP | 8.3 |
| OS | Ubuntu (GitHub Actions `ubuntu-latest`) |

---

## Running Benchmarks Locally

```bash
# Install wrk (macOS)
brew install wrk

# Run the benchmark suite
QUILL_WORKERS=4 composer bench
```

The script `scripts/http-bench.sh` starts the Quill server, waits for it to be ready, then runs `wrk` against the `/hello` endpoint.

You can override the defaults with environment variables:

```bash
DURATION=30 CONNECTIONS=200 THREADS=8 composer bench
```

---

## Why Quill Is Fast

Traditional PHP frameworks repeat this work on **every request**:

| Concern | Traditional PHP | QuillPHP |
| :--- | :--- | :--- |
| Routing | O(n) regex iteration | O(log n) Radix tree in Rust |
| Body validation | PHP reflection + userland rules | SIMD JSON in Rust (sonic-rs) |
| JSON encoding | `json_encode` | sonic-rs (SIMD accelerated) |
| Response delivery | SAPI output buffer | Direct socket write from Rust |
| Bootstrap | Full framework init per request | Boot once, serve forever |

---

## Reproducing CI Results

The `benchmark` job in `.github/workflows/ci.yml` downloads the latest `libquill.so` from the `quillphp/quill-core` releases, starts the server, and runs `wrk`. Results are posted as a PR comment automatically.

```bash
# Trigger locally (requires wrk)
QUILL_CORE_BINARY=/path/to/libquill.so \
QUILL_CORE_HEADER=/path/to/quill.h \
DURATION=5 THREADS=2 CONNECTIONS=50 \
composer bench
```

