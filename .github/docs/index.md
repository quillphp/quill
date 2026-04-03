# QuillPHP Documentation

Welcome to the official documentation for **QuillPHP**, the ultra-high-performance PHP 8.3+ API framework.

QuillPHP is designed for developers who need Go-tier performance with the developer experience of PHP. By separating application logic into **Boot Once** and **Serve Forever** phases, Quill eliminates the runtime overhead characteristic of traditional frameworks.

## Getting Started

If you're new to QuillPHP, start with the **[Installation Guide](getting-started.md)** to boot your first API in under 60 seconds.

## Architecture & Core Concepts

For a deep-dive into how QuillPHP achieves its performance benchmarks, read the **[Architecture Deep-Dive](architecture.md)**. 

### Key Concepts:
- **[Zero-Reflection Dispatch](architecture.md#zero-reflection-optimization)**: Discover how we pre-calculate handler metadata at boot.
- **[Swoole Native Bridge](architecture.md#zero-copy-swoole-bridge)**: How we avoid output buffering to save microseconds.

## Feature Guides

Detailed documentation for the framework's core features:

- **[Routing Strategy](routing.md)**: Closures, Class Handlers, and RESTful Resources.
- **[DTOs & Validation](validation.md)**: Automated validation with zero runtime overhead.
- **[Middleware Pipeline](middleware.md)**: Understanding the Onion pattern and the CORS bridge.
- **[Logging Reference](logging.md)**: Structured text and JSON logging.

## Operations & Deployment

How to run QuillPHP in production:

- **[Benchmarks](benchmarks.md)**: Comparative HTTP and In-Process metrics.
- **[Deployment Guide](deployment.md)**: Swoole, FrankenPHP, Docker, and OPcache tuning.
- **[Configuration Reference](configuration.md)**: Full list of application settings.

## API Reference

Detailed technical specifications:

- **[API Reference](api-reference.md)**: Method tables for `App`, `Request`, and `Response`.
- **[Development & Contributing](development.md)**: Testing, analysis, and maintenance.

---

## Community & Contributing

QuillPHP is open-source (MIT). We welcome contributions that maintain the core philosophy of zero-reflection and ultra-low latency.

- **[GitHub Repository](https://github.com/quillphp/quill)**
- **[Issue Tracker](https://github.com/quillphp/quill/issues)**
- **[License](development.md#license)**
