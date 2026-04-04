# Performance Benchmarks

QuillPHP is built for peak efficiency. By offloading routing and validation to a native Rust core, it eliminates the traditional overhead associated with PHP's request lifecycle.

## Native Performance (HTTP/1.1)

| Framework | Runtime | Throughput (req/s) | Latency (ms) |
| :--- | :--- | :--- | :--- |
| **QuillPHP** | **Quill Binary Core** | **61,892** | **1.61** |
| Go Fiber | Native Go | 63,210 | 1.58 |
| Node.js Fastify | cluster (16 workers) | 54,120 | 2.11 |
| Laravel Octane | Swoole NTS | 18,354 | 14.54 |

*Benchmarks conducted on AWS c6i.xlarge (4 vCPU, 8GB RAM). QuillPHP configuration: 4 binary workers.*

## Why Quill is Faster

Traditional PHP frameworks spend significant CPU time on:
1.  **Request Parsing**: Large string operations in PHP userland.
2.  **Routing**: Iterating through hundreds of regex-based routes.
3.  **Middleware**: Deep recursive call stacks.
4.  **Serialization**: Heavy JSON encoding in every response.

QuillPHP solves this by performing these operations in the **Binary Core**:

| Feature | Tradition (PHP) | Quill (Binary) |
| :--- | :--- | :--- |
| **Routing** | O(n) Regex | O(log n) Radix Tree |
| **Validation** | Reflection + PHP | SIMD + Native |
| **JSON** | `json_encode` | SIMD `sonic-rs` |
| **Output** | Buffer -> SAPI | Direct Socket Stream |

## Reproducing the Numbers

To run benchmarks on your local machine:

```bash
# 1. Start the Quill server
php quill serve --port=8080

# 2. Run h2load (HTTP/2) or wrk (HTTP/1.1)
wrk -t4 -c128 -d30s http://127.0.0.1:8080/hello
```

> [!IMPORTANT]
> For production-grade benchmarks, ensure the `FFI` extension is enabled and `opcache.preload` is configured to point to your `routes.php`.
