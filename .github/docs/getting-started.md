# Getting Started with QuillPHP

Build high-throughput APIs in minutes using the **Quill Binary Core**.

## Requirements

| Requirement | Version |
| :--- | :--- |
| PHP | 8.3+ (CLI) |
| PHP Extensions | `ffi`, `pcntl`, `posix`, `json`, `mbstring` |
| php.ini | `ffi.enable=on` |
| Quill Binary Core | `libquill.so` / `libquill.dylib` (bundled via Composer) |

---

## Installation

```bash
composer create-project quillphp/quill my-api
cd my-api
```

The `quillphp/quill-core` Composer dependency ships a pre-built binary for Linux and macOS. No manual build step is required.

---

## Start the Server

```bash
# Single worker (development)
php bin/quill serve

# Multiple workers (production)
QUILL_WORKERS=4 php bin/quill serve --port=8080
```

---

## Your First Route

Edit `routes.php` to define a GET endpoint:

```php
use Quill\App;
use Quill\Http\Request;

$app = new App();

$app->get('/hello', function (Request $request): array {
    return ['message' => 'Hello from Quill!'];
});

$app->run();
```

Start the server and test it:

```bash
curl http://localhost:8080/hello
# {"message":"Hello from Quill!"}
```

---

## Project Structure

```
my-api/
├── attributes/          # Custom validation attributes
├── bin/
│   └── quill            # CLI entry point
├── domain/              # Business / domain logic
├── dtos/                # Data Transfer Objects
├── handlers/            # Action handlers (ADR pattern)
├── public/
│   └── index.php        # Application bootstrap
├── routes.php           # Route definitions
├── scripts/
│   └── http-bench.sh    # Local benchmark runner
└── src/                 # Framework source
    ├── Http/            # Request, Response, Cors
    ├── Routing/         # Router, RouteMatch
    ├── Runtime/         # Server, Runtime (FFI), Json
    └── Validation/      # Validator, DTO
```

---

## Next Steps

- **[Architecture](architecture.md)** — How the binary core and PHP interact.
- **[Routing](routing.md)** — Groups, parameters, and ADR handlers.
- **[DTOs & Validation](validation.md)** — Type-safe request bodies.
- **[Middleware](middleware.md)** — CORS, auth, and custom pipelines.

