# Logging Reference

QuillPHP includes a lightweight structured logger with configurable level and format.

---

## Configuration

Pass logger options to the `App` constructor:

```php
use Quill\App;
use Quill\Logger;

$app = new App([
    'logger'     => 'php://stderr',  // stream, file path, or Logger instance
    'log_level'  => Logger::INFO,    // minimum level to record
    'log_format' => 'json',          // 'text' or 'json'
]);
```

Set `'logger' => null` to disable logging entirely — zero overhead on the hot path.

---

## Log Levels

From lowest to highest severity:

| Constant | Value |
| :--- | :--- |
| `Logger::DEBUG` | 0 |
| `Logger::INFO` | 1 |
| `Logger::WARNING` | 2 |
| `Logger::ERROR` | 3 |

Only messages at or above the configured `log_level` are written.

---

## Writing Log Entries

```php
$logger = $app->getLogger();

$logger?->debug('Cache miss', ['key' => 'user:42']);
$logger?->info('Request handled', ['path' => '/users', 'ms' => 1.2]);
$logger?->warning('Slow query', ['duration_ms' => 320]);
$logger?->error('DB connection failed', ['host' => 'db.internal']);
```

The null-safe `?->` operator means no guard clause is needed when the logger is disabled.

---

## Log Formats

### Text (`'log_format' => 'text'`)
```
[2026-04-05T12:00:00+00:00] INFO: Request handled {"path":"/users","ms":1.2}
```

### JSON (`'log_format' => 'json'`)
```json
{"time":"2026-04-05T12:00:00+00:00","level":"INFO","message":"Request handled","context":{"path":"/users","ms":1.2}}
```

JSON format is recommended for production environments where logs are ingested by tools like Datadog, Loki, or CloudWatch.

---

## What's Next?

- **[Deployment](deployment.md)** — Run Quill in production.
- **[Configuration](configuration.md)** — Full list of `App` options.

