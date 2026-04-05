# DTOs & Validation

QuillPHP validates request bodies **natively in Rust** before PHP is invoked. You declare rules using PHP attributes on a `DTO` subclass; the framework reflects them once at boot and registers binary schemas with the Rust validation engine.

---

## Creating a DTO

```php
<?php

namespace Dtos\User;

use Quill\Validation\DTO;
use Quill\Attributes\Required;
use Quill\Attributes\Email;
use Quill\Attributes\MinLength;
use Quill\Attributes\Min;
use Quill\Attributes\Max;
use Quill\Attributes\Nullable;

class CreateUserCommand extends DTO
{
    public function __construct(
        #[Required, Email]
        public readonly string $email,

        #[Required, MinLength(8)]
        public readonly string $password,

        #[Required, Min(18), Max(120)]
        public readonly int $age,

        #[Nullable]
        public readonly ?string $bio = null,
    ) {}
}
```

---

## Validation Attributes

| Attribute | Description |
| :--- | :--- |
| `#[Required]` | Field must be present and non-null |
| `#[Email]` | Must be a valid RFC 5322 email address |
| `#[MinLength(n)]` | String length ≥ `n` |
| `#[MaxLength(n)]` | String length ≤ `n` |
| `#[Min(n)]` | Numeric value ≥ `n` |
| `#[Max(n)]` | Numeric value ≤ `n` |
| `#[Regex(pattern)]` | Value must match `pattern` |
| `#[Numeric]` | Value must be numeric |
| `#[Boolean]` | Value must be a boolean |
| `#[Nullable]` | Field may be absent or null |

---

## Attaching a DTO to a Route

Pass the DTO class name as the third argument to any route method:

```php
$app->post('/users', [CreateUserAction::class, '__invoke'], CreateUserCommand::class);
```

The Rust core validates the JSON body against the registered schema before the FFI bridge is crossed. On failure it returns `422` immediately — PHP is never invoked.

---

## Using the DTO in a Handler

```php
<?php

namespace Handlers\User;

use Dtos\User\CreateUserCommand;
use Quill\Http\Request;

class CreateUserAction
{
    public function __invoke(Request $req, CreateUserCommand $command): array
    {
        // $command is already validated and hydrated
        return ['id' => 1, 'email' => $command->email];
    }
}
```

---

## Validation Error Response

When validation fails, Quill returns `422 Unprocessable Entity`:

```json
{
  "errors": {
    "email": ["Must be a valid email address"],
    "password": ["Must be at least 8 characters"]
  }
}
```

---

## What's Next?

- **[Middleware](middleware.md)** — Add cross-cutting concerns to your pipeline.
- **[Logging](logging.md)** — Capture structured request and error logs.

