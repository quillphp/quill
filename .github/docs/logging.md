# Logging Reference

QuillPHP includes a high-performance logger with text and JSON output and configurable log levels.

## Configuration

Configure the logger in the `App` constructor:

```php
use Quill\App;
use Quill\Logger;

$app = new App([
    'logger'     => 'php://stderr',   // or a file path, or a Logger instance
    'log_level'  => Logger::INFO,
    'log_format' => 'json',           // 'text' | 'json'
]);
```

## Log Levels

Lowest to highest:
- `Logger::DEBUG`
- `Logger::INFO`
- `Logger::WARNING`
- `Logger::ERROR`

---

## Using the Logger

```php
$logger = $app->getLogger();
$logger?->info('Server started', ['workers' => 4]);
$logger?->error('DB connection failed', ['host' => 'db.internal']);
```

---

## Performance Optimization

Access logs add **zero overhead** when the logger is set to `null`. This is achieved through simple null-safe checks during the request lifecycle.

---

## What's Next?

- Learn about **[Deployment](deployment.md)** to run the Binary Server in production.
- Review the **[Configuration Reference](configuration.md)**.
