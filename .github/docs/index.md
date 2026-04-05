# QuillPHP Documentation

Welcome to the official documentation for **QuillPHP** — a high-performance PHP 8.3+ API framework powered by a native Rust binary core.

QuillPHP is designed for developers who want near-native throughput without leaving PHP. By separating the application into a **Boot Once** phase and a **Serve Forever** hot path, Quill eliminates the per-request overhead of traditional PHP frameworks.

---

## Getting Started

New to QuillPHP? Start with the **[Getting Started Guide](getting-started.md)** to have your first API running in minutes.

---

## Core Concepts

| Guide | Description |
| :--- | :--- |
| **[Architecture](architecture.md)** | How the Rust binary core and PHP worker interact |
| **[Routing](routing.md)** | Defining routes, parameters, groups, and resources |
| **[DTOs & Validation](validation.md)** | Native-validated request data with PHP attributes |
| **[Middleware](middleware.md)** | Global middleware pipeline and built-in CORS |
| **[Logging](logging.md)** | Structured text and JSON logging |

---

## Operations

| Guide | Description |
| :--- | :--- |
| **[Benchmarks](benchmarks.md)** | Throughput and latency metrics |
| **[Deployment](deployment.md)** | Running in production with multiple workers |
| **[Configuration](configuration.md)** | Full list of `App` constructor options |

---

## Reference

| Guide | Description |
| :--- | :--- |
| **[API Reference](api-reference.md)** | Method signatures for `App`, `Request`, and `Response` |
| **[Development & Contributing](development.md)** | Running tests, static analysis, and contributing |

---

## Community

QuillPHP is open-source (MIT).

- **[GitHub Repository](https://github.com/quillphp/quill)**
- **[Issue Tracker](https://github.com/quillphp/quill/issues)**
- **[Security Policy](https://github.com/quillphp/quill/blob/main/SECURITY.md)**

