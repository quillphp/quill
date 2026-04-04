# Getting Started with QuillPHP

Build O(1) latency APIs in minutes with the **Quill Binary Core**.

## Requirements

- **PHP 8.3+** (CLI mode)
- **FFI Extension** enabled (`ffi.enable=1`)
- **JSON & MBString Extensions**
- **Quill Binary Core** (for your OS, in `bin/`)

## Installation

```bash
composer create-project quillphp/quill my-api
cd my-api
```

## Serving Your API

Quill serves HTTP requests directly via its **Native Binary Core**. You do not need a web server like Apache or Nginx for development.

```bash
# Start the binary server
php bin/quill serve --port=8080
```

## Building Your First Route

Edit `routes.php` (or your entry point) to define a simple GET route.

```php
use Quill\App;
use Quill\Http\Request;

$app = new App();

// Define a route
$app->get('/hello', function (Request $request) {
    return ['message' => 'Hello from the Binary Core!'];
});

// Start the lifecycle
$app->run();
```

## Next Steps

- **[Architecture Deep-Dive](architecture.md)** — Learn how the binary core offloads the "Hot Path".
- **[Routing Strategies](routing.md)** — Master verb mapping, groups, and parameter extraction.
- **[Validation & DTOs](validation.md)** — Use type-safe, binary-validated request data.
- **[Middleware](middleware.md)** — Build robust pipelines for security and CORS.
