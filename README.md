<div align="center">
  <img src="public/.github/docs/logo.svg" width="100" alt="QuillPHP" />

  # QuillPHP

  **High-performance PHP 8.3+ API framework — boot once, serve forever.**

  [![CI](https://github.com/quillphp/quill/actions/workflows/ci.yml/badge.svg)](https://github.com/quillphp/quill/actions/workflows/ci.yml)
  [![Benchmark](https://github.com/quillphp/quill/actions/workflows/benchmark.yml/badge.svg)](https://github.com/quillphp/quill/actions/workflows/benchmark.yml)
  [![PHP](https://img.shields.io/badge/php-%5E8.3-777bb4.svg)](https://php.net)
  [![PHPStan](https://img.shields.io/badge/PHPStan-level%206-2196F3.svg)](https://phpstan.org)
  [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

  [**Official Documentation**](https://quillphp.github.io/quill) · [**Benchmarks**](.github/docs/benchmarks.md) · [**Quick Start**](.github/docs/getting-started.md) · [**Architecture**](.github/docs/architecture.md)
</div>

---

QuillPHP is a worker-native PHP API framework built for environments where every microsecond matters. It achieves Go-tier latency by separating its lifecycle into a high-overhead **Boot Phase** and a zero-overhead **Hot Path**.

### The Power of Quill

```
GET /hello    636,340 dispatch/s    (In-process overhead)
GET /hello     61,892 req/s         (Full HTTP via Swoole)
```

Quill matches **Go Fiber** performance within **2.2%** on identical hardware and is **175× faster** than Laravel Octane on the same Swoole runtime.

---

### Feature Highlights

- **Zero-Reflection Dispatch** — Handler metadata is pre-calculated at boot.
- **Worker-Native** — Native support for Swoole, FrankenPHP, and RoadRunner.
- **Attribute-Based Validation** — Hydrated DTOs with zero runtime reflection.
- **Microsecond Precision** — Direct response writing avoids standard output buffering.
- **Interactive OpenAPI** — Built-in Swagger UI generated from code metadata.

---

### Quick Start in 30 Seconds

```bash
composer create-project quillphp/quill my-api && cd my-api
php quill serve
```

```php
// routes.php
$app->get('/hello', fn() => ['message' => 'Hello, World!']);
$app->resource('/users', UserHandler::class); // CRUD with auto-validation
```

---

### Documentation Hub

Explore the detailed technical documentation:

- **[Installation & Quick Start](.github/docs/getting-started.md)** — From zero to `hello world`.
- **[Architecture Deep-Dive](.github/docs/architecture.md)** — How we achieve record-breaking latency.
- **[Benchmarks & Metrics](.github/docs/benchmarks.md)** — The hard numbers and how to reproduce them.
- **[Routing Strategy](.github/docs/routing.md)** — Full verb mapping, groups, and parameters.
- **[DTOs & Validation](.github/docs/validation.md)** — Secure request data with zero overhead.
- **[Middleware & CORS](.github/docs/middleware.md)** — Efficient pipeline orchestration.
- **[Deployment Guide](.github/docs/deployment.md)** — Running on Swoole, FrankenPHP, and Docker.
- **[API & Configuration](.github/docs/api-reference.md)** — Detailed technical reference tables.

---

### License

QuillPHP is open-source software licensed under the **[MIT License](LICENSE)**.
