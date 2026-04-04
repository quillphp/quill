# Routing Reference

QuillPHP routing is built for speed and precision. Routes are defined in `routes.php` and compiled once at boot into `O(1)` dispatch tables.

## Basic Routing

```php
// Basic HTTP verbs
$app->get('/path', $handler);
$app->post('/path', $handler);
$app->put('/path', $handler);
$app->patch('/path', $handler);
$app->delete('/path', $handler);
$app->head('/path', $handler);
$app->options('/path', $handler);

// Multiple verbs at once
$app->map(['GET', 'POST'], '/path', $handler);
```

---

## Route Parameters

Named parameters are captured in the URL and injected into your handler.

```php
$app->get('/users/{id}', fn($req) => ['id' => $req->param('id')]);

// Parameters are automatically typed in action handlers if hinted:
class GetUserAction {
    public function __invoke(Request $req, int $id): array {
        return ['id' => $id]; // $id is already an integer
    }
}
```

---

## Route Groups

Group routes under a common prefix for cleaner structure.

```php
$app->group('/api/v2', function ($app) {
    $app->get('/status', fn() => ['v' => 2]);
    $app->resource('/posts', PostHandler::class);
});
```

---

## Action-Based Routing (ADR)

QuillPHP advocates for the Action-Domain-Responder (ADR) pattern, where each endpoint is handled by a single, focused class with an `__invoke` method.

```php
$app->get('/users', [\Handlers\User\ListUsersAction::class, '__invoke']);
$app->post('/users', [\Handlers\User\CreateUserAction::class, '__invoke']);
$app->get('/users/{id}', [\Handlers\User\GetUserAction::class, '__invoke']);
$app->put('/users/{id}', [\Handlers\User\UpdateUserAction::class, '__invoke']);
$app->delete('/users/{id}', [\Handlers\User\DeleteUserAction::class, '__invoke']);
```

---

## Handler Types

Quill allows three types of handlers:

1. **Closures:** `fn(Request $req) => [...]`
2. **Callable Objects:** Any `__invoke`able class (Recommended for ADR).
3. **Class Tuples:** `[BenchHandler::class, 'echo']` — High performance mapping for grouping similar endpoints.

---

## What's Next?

- Learn about **[DTOs & Validation](validation.md)** for your request data.
- Explore the **[Middleware Pipeline](middleware.md)**.
