# Deployment & Multi-worker Architecture

QuillPHP is designed for ultra-high performance using a binary-first core and a multi-worker process model. This document outlines the requirements and best practices for deploying QuillPHP in production.

## System Requirements

To use the high-performance binary runtime and multi-worker features, your environment must have the following PHP extensions enabled:

- **ext-ffi**: Required for communication with the native Quill Core (`libquill`).
- **ext-pcntl**: Required for process forking and signal management.
- **ext-posix**: Required for advanced process control.

### Installation Check
You can verify your environment by running:
```bash
php -m | grep -E "ffi|pcntl|posix"
```

## Multi-worker Architecture

QuillPHP uses a "pre-fork" model. The parent process binds to the TCP port and then spawns multiple worker processes (configured via `QUILL_WORKERS`).

### Fork Safety
> [!IMPORTANT]
> Because workers are created via `pcntl_fork()`, they inherit the memory state of the parent. QuillPHP includes built-in guards to ensure that native handles (like the Router and Validator) are safely re-initialized in each child process to prevent memory corruption or double-free errors.

### PHP-FPM / CGI Warning
> [!CAUTION]
> **Do not set `QUILL_WORKERS > 1` when running under PHP-FPM or CGI.**
> Forking processes from within a web server environment like FPM is unsafe and can lead to deadlocks or leaked resources. Multi-worker mode is strictly intended for standalone CLI execution via `bin/quill serve`.

## Process Management

QuillPHP does not currently include a process watchdog. If a worker process dies, it will not be automatically respawned by the parent.

### Recommended: systemd
For production deployments, we recommend using `systemd` with `Restart=on-failure`:

```ini
[Unit]
Description=QuillPHP Application
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php bin/quill serve
Restart=on-failure
Environment=APP_ENV=prod
Environment=QUILL_WORKERS=4

[Install]
WantedBy=multi-user.target
```

## Signals

QuillPHP handles the following signals:

- **SIGINT / SIGTERM**: Graceful shutdown. The parent process forwards the signal to all children and waits for them to exit before shutting down.
- **SIGCHLD**: Monitored by the parent to detect child process exits.
