# API Reference

Detailed method signatures for the core QuillPHP classes.

---

## `Quill\App`

| Method | Signature | Description |
| :--- | :--- | :--- |
| `get` | `(string $path, callable\|array $handler): void` | Register a GET route |
| `post` | `(string $path, callable\|array $handler): void` | Register a POST route |
| `put` | `(string $path, callable\|array $handler): void` | Register a PUT route |
| `patch` | `(string $path, callable\|array $handler): void` | Register a PATCH route |
| `delete` | `(string $path, callable\|array $handler): void` | Register a DELETE route |
| `head` | `(string $path, callable\|array $handler): void` | Register a HEAD route |
| `options` | `(string $path, callable\|array $handler): void` | Register an OPTIONS route |
| `map` | `(array $methods, string $path, callable\|array $handler): void` | Register multiple methods at once |
| `group` | `(string $prefix, callable $callback): void` | Group routes under a common prefix |
| `resource` | `(string $path, string $class): void` | Register a full RESTful resource (index/store/show/update/destroy) |
| `use` | `(callable $middleware): void` | Register global middleware |
| `boot` | `(): void` | Compile routes and validators (called automatically by `run()`) |
| `run` | `(): void` | Start the server |
| `setLogger` | `(?Logger $logger): void` | Replace the active logger |
| `getLogger` | `(): ?Logger` | Get the active logger |

---

## `Quill\Http\Request`

| Method | Return | Description |
| :--- | :--- | :--- |
| `method()` | `string` | HTTP verb (`GET`, `POST`, …) |
| `path()` | `string` | URL path without query string |
| `param(string $key)` | `string\|null` | Named route parameter |
| `query(string $key)` | `string\|null` | Query string value |
| `input(string $key)` | `mixed` | Parsed JSON body field |
| `body()` | `array` | Full parsed JSON body |
| `header(string $name)` | `string\|null` | Request header value |
| `ip()` | `string\|null` | Client IP address |
| `protocol()` | `string` | HTTP protocol version |

---

## `Quill\Http\HttpResponse`

JSON response wrapper.

```php
// 200 OK (default)
return new HttpResponse(['success' => true]);

// 201 Created
return new HttpResponse(['id' => 42], 201);

// 404 Not Found
return new HttpResponse(['error' => 'Not found'], 404);

// 422 with custom headers
return new HttpResponse(['errors' => [...]], 422, ['X-Trace-Id' => 'abc']);
```

---

## `Quill\Http\HtmlResponse`

HTML response wrapper.

```php
return new HtmlResponse('<h1>Hello</h1>');
return new HtmlResponse('<h1>Not Found</h1>', 404);
```

---

## `Quill\Validation\DTO`

Base class for all Data Transfer Objects.

| Method | Signature | Description |
| :--- | :--- | :--- |
| `fromRequest` | `static (Request $req): static` | Hydrate a DTO from an already-validated request; throws `422` on failure |

---

## `Quill\Logger`

| Method | Signature | Description |
| :--- | :--- | :--- |
| `debug` | `(string $msg, array $ctx = []): void` | Log at DEBUG level |
| `info` | `(string $msg, array $ctx = []): void` | Log at INFO level |
| `warning` | `(string $msg, array $ctx = []): void` | Log at WARNING level |
| `error` | `(string $msg, array $ctx = []): void` | Log at ERROR level |

---

## `Quill\Runtime\Runtime`

| Method | Description |
| :--- | :--- |
| `Runtime::boot(): bool` | Auto-discover and load the native binary |
| `Runtime::init(string $soPath, string $headerPath): void` | Load a specific binary |
| `Runtime::isAvailable(): bool` | Whether the FFI binary loaded successfully |
| `Runtime::get(): \FFI` | Return the active FFI instance (throws if not initialized) |

