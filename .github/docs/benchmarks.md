# Benchmarks

QuillPHP is designed for high-performance API orchestration. All benchmark jobs run automatically on every push to `main` and weekly on a schedule.

**[View live results →](https://github.com/quillphp/quill/actions/workflows/benchmark.yml)**

## HTTP Throughput (Full Round-Trip)

> **Runner:** `ubuntu-latest` (2 vCPU, ~7 GB RAM) · **Tool:** `wrk -t2 -c200 -d15s` · **Route:** `GET /hello`
> Last verified: run [#12](https://github.com/quillphp/quill/actions/runs/23957018376) · 2026-04-03

| Framework | Runtime | Req/s | Latency (avg) |
|-----------|---------|------:|--------------:|
| **QuillPHP** | Swoole NTS + JIT (`SWOOLE_BASE`) | **61,892** | 3.22 ms |
| Go Fiber | Go 1.22.12 (fasthttp) | 63,256 | 2.93 ms |
| FrankenPHP | Worker mode (ZTS, no JIT) | 10,804 | 18.55 ms |
| FastAPI | Python 3.12 + Uvicorn (2 workers) | 8,631 | 26.94 ms |
| **QuillPHP** | PHP 8.3 built-in server (2 workers) | **3,361** | 29.13 ms |
| Laravel Octane| Swoole NTS + JIT | 354 | 554.54 ms |

**Key Signal:** QuillPHP on Swoole matches Go Fiber (the fastest Go framework) within **2.1%** on identical hardware, and is **175× faster** than Laravel Octane on the same Swoole runtime. The delta against Octane is pure framework overhead—same runtime, different dispatch architecture.

---

## In-Process Dispatch (Framework Overhead Only)

> Measures: routing → parameter injection → handler resolve → JSON encode.
> **Zero network cost.**
> PHP 8.3.30 NTS + JIT · 100,000 iterations per route.

| Route | Handler | Time | Req/s |
|---|---|---|---|
| `GET /hello` | `BenchHandler::hello` | 0.1571s | **636,340** |
| `GET /users/42` | `BenchHandler::user` | 0.2423s | **412,666** |
| `POST /echo` | `BenchHandler::echo` | 0.2073s | **482,452** |
| `POST /users` (DTO validation) | `UserHandler + DTO` | 0.1688s | **296,248** |

---

## Performance Architecture

| Layer | Technique | Effect |
|---|---|---|
| Boot | Reflect handlers once → flat arrays | Zero reflection on hot path |
| Routing | FastRoute compiled dispatcher | O(1) static / O(log n) dynamic |
| Swoole | Direct `\Swoole\Http\Response::end()` | Eliminates `ob_start` bridge (~2–3 µs/req) |
| No-middleware path | No closure allocation | ~0.5 µs saved per request |
| Autoloader | `--classmap-authoritative` | Eliminates PSR-4 `stat()` fallback |
| OPcache | `preload` + `validate_timestamps=0`| Zero file I/O per request |
| JIT | `opcache.jit=tracing` (NTS only) | ~20–40% throughput gain |
| GC | Throttled `gc_collect_cycles()` | Prevents memory growth without overhead |

---

## Reproduce Locally

### In-Process (framework overhead only)

```bash
php -d opcache.enable_cli=1 -d opcache.jit=tracing \
    -d opcache.jit_buffer_size=128M scripts/benchmark.php
```

### HTTP via Swoole

```bash
SWOOLE_WORKERS=4 SWOOLE_PORT=8080 SWOOLE_MODE=base php scripts/swoole_bench.php &
sleep 1
wrk -t2 -c200 -d15s http://127.0.0.1:8080/hello
kill %1
```

### HTTP via PHP Built-In Server

```bash
PHP_CLI_SERVER_WORKERS=$(nproc) php -S 127.0.0.1:8765 scripts/bench_server.php &
sleep 1
wrk -t2 -c200 -d15s http://127.0.0.1:8765/hello
kill %1
```
