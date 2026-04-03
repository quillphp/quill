# API Reference

Detailed technical specifications for the core QuillPHP components.

## `App`

The main application orchestrator.

| Method | Signature | Description |
|---|---|---|
| `get` | `(string $path, callable\|array $handler): void` | Register a GET route |
| `post` | `(string $path, callable\|array $handler): void` | Register a POST route |
| `put` | `(string $path, callable\|array $handler): void` | Register a PUT route |
| `patch` | `(string $path, callable\|array $handler): void` | Register a PATCH route |
| `delete` | `(string $path, callable\|array $handler): void` | Register a DELETE route |
| `head` | `(string $path, callable\|array $handler): void`| Register a HEAD route |
| `options`| `(string $path, callable\|array $handler): void`| Register an OPTIONS route |
| `map` | `(array $methods, string $path, callable\|array $handler): void` | Register multiple methods |
| `group` | `(string $prefix, callable $callback): void` | Group routes under a prefix |
| `resource`| `(string $path, string $class): void`| Register a full RESTful resource |
| `use` | `(callable $middleware): void` | Register global middleware |
| `boot` | `(): void` | Compile internal state (called automatically) |
| `run` | `(): void` | Start the server (Swoole, FrankenPHP, etc.) |
| `setLogger`| `(?Logger $logger): void` | Update the logger at runtime |
| `getLogger`| `(): ?Logger` | Get the active logger instance |

---

## `Request`

Access point for all incoming HTTP data.

| Method | Description |
|---|---|
| `method()` | Get HTTP method (`GET`, `POST`, etc.) |
| `path()` | Get the URL path (without query string) |
| `param(string $key)` | Get an individual named route parameter |
| `query(string $key)` | Get a query string value by key |
| `input(string $key)`| Get a parsed JSON body field |
| `body()` | Get the full parsed JSON body array |
| `header(string $name)`| Get a request header value |
| `ip()` | Get the client IP address |
| `protocol()`| Get the HTTP protocol version |

---

## `HttpResponse`

Wrapper for generating JSON responses.

```php
// Status 200 (Default)
return new \Quill\HttpResponse(['success' => true]);

// Status 201 Created
return new \Quill\HttpResponse(['id' => 1], 201);

// Status 404 Not Found
return new \Quill\HttpResponse(['error' => 'User not found'], 404);
```

---

## `HtmlResponse`

Wrapper for generating HTML responses.

```php
return new \Quill\HtmlResponse('<h1>Hello World</h1>');
```

---

## What's Next?

- Explore the **[Architecture Guide](architecture.md)**.
- Review **[DTOs & Validation](validation.md)**.
