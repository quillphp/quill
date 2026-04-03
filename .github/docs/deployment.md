# Deployment Guide

QuillPHP is built for performance and supports several worker-native runtimes.

## Swoole (Recommended)

Install the Swoole extension (NTS PHP for JIT support):

```bash
pecl install swoole
```

Run with `SWOOLE_BASE` mode on Linux for highest throughput:

```bash
SWOOLE_WORKERS=8 \
SWOOLE_PORT=8080 \
SWOOLE_MODE=base \
php public/index.php
```

### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `SWOOLE_WORKERS` | `swoole_cpu_num()` | Number of worker processes |
| `SWOOLE_PORT` | `8080` | Listening port |
| `SWOOLE_MODE` | `process` | `base` (Linux) or `process` (macOS/Docker) |
| `SWOOLE_MAX_REQUEST`| `0` | Worker recycle after N requests (0 = disabled) |
| `QUILL_GC_INTERVAL` | `500` | Run GC every N requests (0 = disabled) |

---

## FrankenPHP

Docker configuration for FrankenPHP:

```dockerfile
FROM dunglas/frankenphp

COPY . /app
WORKDIR /app

RUN composer install --no-dev --optimize-autoloader --classmap-authoritative

CMD ["frankenphp", "run", "--config", "Caddyfile"]
```

Example `Caddyfile`:

```caddyfile
{
    frankenphp
}

:8080 {
    root * /app/public
    php_server
}
```

---

## Docker (Swoole)

```dockerfile
FROM php:8.3-cli

RUN pecl install swoole && docker-php-ext-enable swoole \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --classmap-authoritative

EXPOSE 8080
CMD ["sh", "-c", "SWOOLE_WORKERS=${SWOOLE_WORKERS:-4} SWOOLE_MODE=base php public/index.php"]
```

---

## Production OPcache (php.ini)

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0     ; disable in production — reload on deploy
opcache.revalidate_freq=0
opcache.jit=tracing               ; NTS PHP only
opcache.jit_buffer_size=128M
```

---

## What's Next?

- Review the **[Configuration Reference](configuration.md)**.
- Explore the **[API Reference](api-reference.md)**.
