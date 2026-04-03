# Getting Started

QuillPHP is a high-performance PHP API framework. This guide will help you install and run your first Quill application.

## Requirements

- **PHP 8.3+** (NTS recommended for JIT)
- **Composer 2.0+**
- **Extension (Optional/Recommended):** Swoole (for peak performance)

## Installation

```bash
composer create-project quillphp/quill my-api
cd my-api
php quill serve
```

This will set up a fresh project and start the built-in development server at `http://127.0.0.1:8765`.

---

## Folder Structure

- `attributes/` — Custom validation rules and metadata.
- `dtos/` — Data Transfer Objects for automatic validation.
- `handlers/` — Business logic (similar to controllers).
- `public/` — Public entry point (`index.php`) and documentation assets.
- `src/` — The core QuillPHP framework.
- `tests/` — PHPUnit test suite.
- `routes.php` — Your application's API routing map.
- `quill` — The project CLI tool.

---

## First Steps

Define your routes in `routes.php`:

```php
<?php

use Handlers\UserHandler;

/** @var \Quill\App $app */

// Closure route
$app->get('/hello', fn() => ['message' => 'Hello, World!']);

// Class-based handler
$app->post('/users', [UserHandler::class, 'store']);

// Full RESTful resource
$app->resource('/users', UserHandler::class);
```

Run the built-in server:

```bash
# via Composer script
composer serve

# or directly
./quill serve
```

---

## The CLI Tool

Quill comes with a built-in CLI tool to help manage your development workflow:

- `php quill serve` — Start the development server.
- `php quill benchmark` — Run the in-process performance benchmarks.

---

## What's Next?

- Explore **[Routing](routing.md)** for advanced route definitions.
- Set up **[DTOs & Validation](validation.md)** for your request data.
- Learn about **[Deployment](deployment.md)** to run on Swoole in production.
