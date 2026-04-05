# Configuration Reference

All options are passed as an associative array to the `App` constructor in `public/index.php`.

---

## Full Example

```php
use Quill\App;
use Quill\Logger;

$app = new App([
    'docs'        => false,
    'debug'       => false,
    'env'         => 'prod',
    'route_cache' => false,
    'logger'      => 'php://stderr',
    'log_level'   => Logger::INFO,
    'log_format'  => 'json',
]);
```

---

## Options

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `docs` | `bool` | `false` | Expose `GET /docs` (Swagger UI) and `GET /docs/openapi.json` |
| `debug` | `bool` | `false` | Include stack traces in `5xx` error responses |
| `env` | `string` | `'prod'` | Environment label — `'prod'` or `'dev'` |
| `route_cache` | `false\|string` | `false` | Path to a route cache file, or `false` to disable |
| `logger` | `null\|string\|Logger` | `null` | Logger destination — stream URI, file path, or `Logger` instance |
| `log_level` | `int` | `Logger::INFO` | Minimum severity to record |
| `log_format` | `string` | `'text'` | Output format — `'text'` or `'json'` |

---

## Environment Variables

| Variable | Description |
| :--- | :--- |
| `QUILL_WORKERS` | Number of worker processes to fork (default: `1`) |
| `QUILL_RUNTIME` | Set to `rust` to force the binary core |
| `QUILL_CORE_BINARY` | Absolute path to `libquill.so` / `libquill.dylib` |
| `QUILL_CORE_HEADER` | Absolute path to `quill.h` |
| `APP_ENV` | Set to `bench` to disable idle `usleep()` in the poll loop |

---

## Notes

- **`route_cache`** — Leave as `false` in long-running binary server mode. The compiled router lives in the Rust heap for the entire process lifetime; file caching is only beneficial in FPM-style request-per-process environments.
- **`docs`** — Requires the routes to carry OpenAPI metadata. Disabled by default in production to avoid exposing API structure.

