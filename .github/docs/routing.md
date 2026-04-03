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

// Parameters are automatically typed in class handlers if hinted:
class UserHandler {
    public function show(Request $req, int $id): array {
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

## RESTful Resources

The `resource` method automatically registers six standard CRUD routes.

```php
$app->resource('/users', UserHandler::class);
```

| Method | Path | Handler method |
|---|---|---|
| `GET` | `/users` | `UserHandler::index` |
| `POST` | `/users` | `UserHandler::store` |
| `GET` | `/users/{id}` | `UserHandler::show` |
| `PUT` | `/users/{id}` | `UserHandler::update` |
| `PATCH` | `/users/{id}` | `UserHandler::update` |
| `DELETE` | `/users/{id}` | `UserHandler::destroy` |

---

## Handler Types

Quill allows three types of handlers:

1. **Closures:** `fn(Request $req) => [...]`
2. **Class Tuples:** `[UserHandler::class, 'store']` — Best for auto-validation.
3. **Callable Objects:** Any `__invoke`able class.

---

## What's Next?

- Learn about **[DTOs & Validation](validation.md)** for your request data.
- Explore the **[Middleware Pipeline](middleware.md)**.
