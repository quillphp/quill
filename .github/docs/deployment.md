# Deployment Guide

QuillPHP runs as a self-contained long-running process. No web server (Apache, Nginx, PHP-FPM) is required.

---

## 1. Prerequisites

- PHP 8.3+ CLI with `ffi`, `pcntl`, `posix` extensions enabled
- `ffi.enable=on` in `php.ini` (or pass `-d ffi.enable=on` on the command line)
- `libquill.so` / `libquill.dylib` from `quillphp/quill-core` (installed via Composer)

---

## 2. Start the Server

```bash
# Single worker
php bin/quill serve --port=8080

# Multiple workers (recommended for production)
QUILL_WORKERS=8 php bin/quill serve --port=8080
```

The process prints one line per worker when ready:

```
[Worker 12345] listening on http://0.0.0.0:8080
[Worker 12346] listening on http://0.0.0.0:8080
```

Each worker binds the port independently via `SO_REUSEPORT`; the kernel distributes connections evenly between them.

---

## 3. Recommended Worker Count

Set `QUILL_WORKERS` to the number of available CPU cores:

```bash
QUILL_WORKERS=$(nproc) php bin/quill serve --port=8080
```

---

## 4. OPcache Tuning

Enable OPcache for maximum PHP throughput:

```ini
; php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=128M
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

---

## 5. Docker

```dockerfile
FROM php:8.3-cli-alpine

RUN apk add --no-cache libffi-dev \
    && docker-php-ext-configure ffi \
    && docker-php-ext-install ffi pcntl posix \
    && echo "ffi.enable=on" >> /usr/local/etc/php/php.ini

WORKDIR /var/www
COPY . .

RUN composer install --no-dev --optimize-autoloader

CMD ["php", "bin/quill", "serve", "--port=80"]
```

Run with workers matching vCPU count:

```bash
docker run -e QUILL_WORKERS=4 -p 8080:80 my-quill-api
```

---

## 6. Reverse Proxy (Nginx / Traefik)

For TLS termination and global rate limiting, place Quill behind Nginx:

```nginx
upstream quill {
    server 127.0.0.1:8080;
    keepalive 64;
}

server {
    listen 443 ssl http2;
    server_name api.example.com;

    location / {
        proxy_pass         http://quill;
        proxy_http_version 1.1;
        proxy_set_header   Connection "";
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
    }
}
```

---

## 7. Process Management (systemd)

```ini
[Unit]
Description=QuillPHP API Server
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/my-api
Environment=QUILL_WORKERS=8
ExecStart=/usr/bin/php bin/quill serve --port=8080
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now quill-api
```

---

## 8. Security Hardening

- Run the process as a non-root user.
- Ensure `libquill.so` is owned by `root` with `755` permissions.
- Configure OS-level file descriptor limits: `ulimit -n 65535`.
- Use a reverse proxy for TLS; Quill speaks plain HTTP internally.

