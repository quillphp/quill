# Middleware Reference

QuillPHP implements the "Onion" middleware pattern. Middleware is a `callable(Request $req, callable $next): mixed`.

## Global Middleware

Register middleware globally in `public/index.php`.

```php
$app->use(function (Request $req, callable $next) {
    // Before handler
    $result = $next($req);
    // After handler
    return $result;
});
```

---

## Built-in CORS Middleware

Quill includes a first-class CORS middleware.

```php
use Quill\Http\Cors;

$app->use(Cors::middleware(
    origins: ['https://app.example.com'],
    methods: ['GET', 'POST', 'PUT', 'DELETE'],
    headers: ['Content-Type', 'Authorization'],
    credentials: true,
    maxAge: 86400,
));
```

---

## Authentication Example

Custom middleware for token validation:

```php
$app->use(function (Request $req, callable $next) {
    if ($req->path() === '/health') {
        return $next($req); // bypass auth
    }

    $token = $req->header('Authorization');
    if (!$token || !str_starts_with($token, 'Bearer ')) {
        return new \Quill\Http\HttpResponse(['error' => 'Unauthorized'], 401);
    }

    return $next($req);
});
```

---

## Performance Optimization

When no middleware is registered, the dispatch closure is **never allocated**. This saves ~0.5 µs per request in FrankenPHP worker mode.

---

## What's Next?

- Configure **[Logging](logging.md)** for your application.
- Learn about **[Deployment](deployment.md)** to run on Swoole in production.
