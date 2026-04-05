# Routing Reference

Routes are defined in `routes.php`, compiled once at boot into a native Radix-tree, and matched in Rust on every request.

---

## HTTP Verbs

```php
$app->get('/path',    $handler);
$app->post('/path',   $handler);
$app->put('/path',    $handler);
$app->patch('/path',  $handler);
$app->delete('/path', $handler);
$app->head('/path',   $handler);
$app->options('/path',$handler);

// Multiple verbs at once
$app->map(['GET', 'POST'], '/path', $handler);
```

---

## Route Parameters

Named segments are captured and injected into your handler automatically.

```php
$app->get('/users/{id}', function (Request $req): array {
    return ['id' => $req->param('id')];
});
```

Type-hinted parameters in class handlers are automatically cast:

```php
class GetUserAction
{
    public function __invoke(Request $req, int $id): array
    {
        return ['id' => $id]; // already cast to int
    }
}
```

---

## Route Groups

```php
$app->group('/api/v1', function (App $app): void {
    $app->get('/status', fn() => ['ok' => true]);
    $app->resource('/posts', PostHandler::class);
});
```

---

## RESTful Resources

`resource()` registers the standard CRUD surface in one call:

| Verb | Path | Handler method |
| :--- | :--- | :--- |
| GET | `/posts` | `index` |
| POST | `/posts` | `store` |
| GET | `/posts/{id}` | `show` |
| PUT | `/posts/{id}` | `update` |
| DELETE | `/posts/{id}` | `destroy` |

```php
$app->resource('/posts', PostHandler::class);
```

---

## Handler Types

| Type | Example |
| :--- | :--- |
| Closure | `fn(Request $req): array => [...]` |
| Invokable class | `[GetUserAction::class, '__invoke']` |
| Class + method tuple | `[BenchHandler::class, 'echo']` |

The ADR (Action-Domain-Responder) pattern with invokable classes is recommended for maintainability:

```php
$app->get('/users',        [ListUsersAction::class,   '__invoke']);
$app->post('/users',       [CreateUserAction::class,  '__invoke']);
$app->get('/users/{id}',   [GetUserAction::class,     '__invoke']);
$app->put('/users/{id}',   [UpdateUserAction::class,  '__invoke']);
$app->delete('/users/{id}',[DeleteUserAction::class,  '__invoke']);
```

---

## What's Next?

- **[DTOs & Validation](validation.md)** — Validate and hydrate request bodies.
- **[Middleware](middleware.md)** — Build request/response pipelines.

