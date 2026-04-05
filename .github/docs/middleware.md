# Middleware Reference

QuillPHP uses the **Onion** pattern: each middleware wraps the next, receiving the request before and the response after the inner layers execute.

A middleware is any `callable(Request $req, callable $next): mixed`.

---

## Global Middleware

Register with `$app->use()` in `public/index.php`:

```php
$app->use(function (Request $req, callable $next): mixed {
    // before handler
    $result = $next($req);
    // after handler
    return $result;
});
```

Middleware is executed in registration order (outermost first).

> **Performance note:** When no middleware is registered, the `Pipeline` is bypassed entirely and the handler is called directly — zero allocation overhead.

---

## Built-in CORS Middleware

```php
use Quill\Http\Cors;

$app->use(Cors::middleware(
    origins:     ['https://app.example.com'],
    methods:     ['GET', 'POST', 'PUT', 'DELETE'],
    headers:     ['Content-Type', 'Authorization'],
    credentials: true,
    maxAge:      86400,
));
```

CORS `OPTIONS` preflight requests are handled automatically.

---

## Built-in Security Headers

```php
use Quill\Middleware\SecurityHeaders;

$app->use(new SecurityHeaders());
```

Adds `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy` headers to every response.

---

## Built-in Rate Limiter

```php
use Quill\Middleware\RateLimiter;

$app->use(new RateLimiter(maxRequests: 100, windowSeconds: 60));
```

---

## Built-in Request ID

```php
use Quill\Middleware\RequestId;

$app->use(new RequestId());
// Attaches X-Request-Id header to every response
```

---

## Authentication Example

```php
$app->use(function (Request $req, callable $next): mixed {
    if ($req->path() === '/health') {
        return $next($req); // bypass auth for health check
    }

    $token = $req->header('Authorization');
    if (!$token || !str_starts_with($token, 'Bearer ')) {
        return new \Quill\Http\HttpResponse(['error' => 'Unauthorized'], 401);
    }

    return $next($req);
});
```

---

## Exception Recovery

```php
use Quill\Middleware\Recover;

$app->use(new Recover()); // catches uncaught Throwables → 500 JSON response
```

---

## What's Next?

- **[Logging](logging.md)** — Add structured logging to your middleware.
- **[Deployment](deployment.md)** — Run Quill with multiple workers in production.

