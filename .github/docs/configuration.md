# Configuration Reference

Pass configuration options to the `App` constructor in `public/index.php`.

## App Configuration

```php
$app = new App([
    'docs'        => false,           // bool — enable /docs and /docs/openapi.json
    'debug'       => false,           // bool — include stack traces in error responses
    'env'         => 'prod',          // 'prod' | 'dev'
    'route_cache' => false,           // false = disabled (worker mode)
                                      // string = path to cache file (FPM mode)
    'logger'      => 'php://stderr',  // Logger instance | file path | null
    'log_level'   => Logger::INFO,    // Logger::DEBUG | INFO | WARNING | ERROR
    'log_format'  => 'json',          // 'text' | 'json'
]);
```

## Config Keys Detailed

| Key | Type | Default | Description |
|---|---|---|---|
| `docs` | `bool` | `false` | Enable/disable OpenAPI spec and Swagger UI. |
| `debug`| `bool` | `false` | If true, error responses include stack traces. |
| `env` | `string` | `'prod'` | Environment label (`prod`, `dev`). |
| `route_cache` | `mixed` | `false` | Path to route cache file or `false`. |
| `logger` | `mixed` | `null` | Logger instance, file path, or stream. |
| `log_level` | `int` | `Logger::INFO` | Minimum log level to record. |
| `log_format` | `string` | `'text'` | Record logs as `text` or `json`. |

---

## Performance Tip

Set `route_cache => false` in the long-running **Binary Server** mode. The compiled dispatcher lives in memory for the process lifetime — no file cache is needed.

---

## What's Next?

- Explore the **[API Reference](api-reference.md)**.
- Review the **[Architecture Guide](architecture.md)**.
