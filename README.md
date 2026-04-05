<div align="center">
  <h1>QuillPHP</h1>
  <p><strong>High-performance PHP 8.3+ API framework — boot once, serve forever.</strong></p>

  [![CI](https://github.com/quillphp/quill/actions/workflows/ci.yml/badge.svg)](https://github.com/quillphp/quill/actions/workflows/ci.yml)
  [![Benchmark](https://github.com/quillphp/quill/actions/workflows/benchmark.yml/badge.svg)](https://github.com/quillphp/quill/actions/workflows/benchmark.yml)
  [![PHP](https://img.shields.io/badge/php-%5E8.3-777bb4.svg)](https://php.net)
  [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

  ### [Documentation](https://quillphp.github.io/quill) &bull; [Quick Start](.github/docs/getting-started.md) &bull; [Benchmarks](.github/docs/benchmarks.md)
</div>

---

## The Quill Philosophy

QuillPHP is a **binary-native** API framework engineered for extreme low-latency environments. By strictly separating the **Boot Phase** from the **Hot Path**, Quill achieves performance metrics previously reserved for compiled languages like Go and Rust.

### Performance at Scale

| Framework | Throughput (req/s) | Latency (ms) |
| :--- | :--- | :--- |
| **QuillPHP (Native)** | **61,892** | **1.61** |
| Go Fiber | 63,210 | 1.58 |
| Rust Actix | 68,450 | 1.45 |

*Benchmarks conducted on identical hardware (4 vCPU, 8GB RAM) using the native Quill Binary Core.*

---

## Feature Highlights

- **Native Rust Core** — Integrated FFI acceleration using `matchit` (radix trie) and `sonic-rs` (SIMD JSON).
- **Binary-Native** — Served directly by the **Quill Binary Server**, bypassing traditional SAPIs like FPM or Apache.
- **Zero-Reflection Dispatch** — Metadata is pre-mapped during the boot phase for O(1) request routing.
- **Unified Middleware** — Robust pipeline for CORS, Rate Limiting, and Security Headers.
- **DTO Validation** — Type-safe, attribute-driven request validation with zero runtime overhead.
- **OpenAPI 3.0** — Automatic Swagger UI generation directly from your code.

---

## Getting Started

### 1. Installation
```bash
composer create-project quillphp/quill my-api
cd my-api
```

### 2. Define Your API
```php
use Quill\App;
use Quill\Http\Request;

$app = new App();

// Simple JSON endpoint
$app->get('/hello', fn() => ['message' => 'Hello, World!']);

// Resource with auto-validation
$app->resource('/users', UserController::class);

$app->run();
```

### 3. Launch
```bash
php quill serve
```

---

## In-Depth Guides

- [**Architecture**](.github/docs/architecture.md) — How we achieve record-breaking speed.
- [**Routing**](.github/docs/routing.md) — Verb mapping, groups, and parameter extraction.
- [**Validation**](.github/docs/validation.md) — DTOs, attributes, and native schema checks.
- [**Deployment**](.github/docs/deployment.md) — Production-ready setups for Swoole and FrankenPHP.

---

## Contributing

We welcome contributions! Please see our [Contributing Guide](.github/docs/development.md) for local setup instructions.

## License

QuillPHP is open-source software licensed under the **[MIT License](LICENSE)**.
