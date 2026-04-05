# Deployment Guide

QuillPHP is built for self-contained binary deployment. By avoiding traditional SAPIs like FPM, deployment is simplified to running the Quill Binary Server directly.

## Deployment Workflow

### 1. Build and Prepare
Ensure the Quill Binary Core for your target OS is in the `bin/` directory.

```bash
# Verify the binary core is accessible
ls bin/libquill.*
```

### 2. Configure Opcache Preload
For maximum performance, we recommend preloading the Application and Lifecycle code:

```ini
# opcache.ini
opcache.preload=/var/www/scripts/preload.php
opcache.preload_user=www-data
```

### 3. Native Binary Server
Run the production server using the Quill CLI.

```bash
# Serve with multiple workers (Linux/macOS)
QUILL_WORKERS=8 php bin/quill serve --port=80
```

## Docker Deployment

The recommended approach is a multi-stage Docker build that includes the PHP runtime and the Quill Binary Core.

```dockerfile
# Dockerfile
FROM php:8.3-cli-alpine

# Install FFI and other requirements
RUN apk add --no-cache libffi-dev && \
    docker-php-ext-install ffi

# Copy application and binary core
COPY . /var/www
WORKDIR /var/www

# Launch the server
CMD ["php", "bin/quill", "serve", "--port=80"]
```

## Security Hardening

To secure your production instance, follow these best practices:

- **Resource Limits**: Configured via the Quill CLI or OS-level limits (e.g., `ulimit -n 65535`).
- **Binary Hardening**: Ensure `libquill.so` is owned by `root` with `755` permissions.
- **Process Isolation**: Run the Quill process as a non-privileged user (e.g., `www-data`).
- **Reverse Proxy**: While Quill serves HTTP directly, we recommend using **Nginx** or **Traefik** as a reverse proxy for TLS termination and global rate limiting.
