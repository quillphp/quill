# Architecture

QuillPHP follows a **Boot Once, Serve Forever** model. All expensive reflection and compilation work happens once at startup; the per-request hot path is kept to an absolute minimum.

---

## 1. Request Lifecycle

Every request is handled by the Rust binary core before PHP is ever invoked.

```
Incoming Request
      │
      ▼
┌─────────────────────────────┐
│  Quill Binary Core (Rust)   │  ← Axum HTTP server
│  • Radix-tree routing       │
│  • JSON body parsing        │
│  • DTO schema validation    │
└────────────┬────────────────┘
             │  FFI bridge (single C call)
             ▼
┌─────────────────────────────┐
│  PHP Worker Process         │
│  • Middleware pipeline      │
│  • Handler execution        │
│  • Response serialisation   │
└────────────┬────────────────┘
             │  FFI bridge (response)
             ▼
      Rust → Socket → Client
```

Invalid requests (bad routes, failed validation) are rejected entirely in Rust — PHP is never touched.

---

## 2. Boot Phase vs. Hot Path

### Boot Phase  *(runs once per worker, at startup)*

| Step | What happens |
| :--- | :--- |
| `Router::compile()` | Serialises all PHP routes into a JSON manifest and calls `quill_router_build()` via FFI, producing a native Radix-tree in the Rust heap |
| `Validator::reinitialize()` | Reflects all DTO attribute rules and registers JSON schemas with the Rust validation engine via `quill_validator_register()` |
| Param cache | Reflection data for every handler (type hints, defaults) is stored in PHP memory and never re-computed |

### Hot Path  *(runs per request)*

| Step | Where |
| :--- | :--- |
| HTTP accept + parse | Rust (Axum) |
| Route matching | Rust (O(log n) Radix tree) |
| Body validation | Rust (sonic-rs SIMD) |
| FFI poll | PHP calls `quill_server_poll()` |
| Middleware pipeline | PHP |
| Handler | PHP |
| Response | PHP calls `quill_server_respond()` |

---

## 3. Multi-Worker Architecture

```
php bin/quill serve
        │
        ├── pcntl_fork()  ──►  Worker 1 (child)
        ├── pcntl_fork()  ──►  Worker 2 (child)
        │                         ⋮
        └──────────────────────►  Worker N (parent)
```

Each worker:
1. Calls `Validator::reinitialize()` and `Router::recompile()` **after** the fork, so every process owns its own Rust objects with no shared heap state across process boundaries.
2. Binds the TCP port independently using `SO_REUSEPORT`; the kernel distributes connections between workers.
3. Optionally participates in a single pre-bound socket when `quill_server_prebind()` is available in the loaded binary (graceful fallback otherwise).

Worker count is controlled by the `QUILL_WORKERS` environment variable.

---

## 4. Key Components

### `src/Runtime/Runtime.php`
Manages FFI initialisation. Discovers `libquill.so` / `libquill.dylib` via:
1. `QUILL_CORE_BINARY` / `QUILL_CORE_HEADER` environment variables
2. `vendor/quillphp/quill-core/bin/`
3. `/usr/local/lib/`

### `src/Runtime/Server.php`
Owns the main event loop. Calls `quill_server_poll()` in a tight loop, dispatches matched requests to PHP handlers via `RouteMatch`, and returns responses with `quill_server_respond()`.

### `src/Routing/Router.php`
Compiles PHP route definitions into a JSON manifest for the Rust Radix-tree router. Also holds the param cache used for zero-reflection dispatch on the hot path.

### `src/Validation/Validator.php`
Wraps the Rust validator registry. Reflects DTO PHP attributes once and registers binary schemas. Validates request bodies natively before they reach PHP.

### `src/Runtime/SocketServer.php`
Pure-PHP fallback HTTP server for environments without FFI. Used automatically when `Runtime::isAvailable()` returns `false`.

---

## 5. Recommended Project Layout (ADR)

QuillPHP encourages **Action-Domain-Responder** over MVC:

| Directory | Role |
| :--- | :--- |
| `handlers/` | Single-operation invokable classes (Actions) |
| `dtos/` | Typed `DTO` subclasses — Commands / Queries |
| `domain/` | Pure business logic, framework-agnostic |

