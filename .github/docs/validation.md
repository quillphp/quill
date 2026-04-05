# DTOs & Validation

QuillPHP simplifies data handling with Data Transfer Objects (DTOs) and Attribute-based validation. 

By defining your request data as a DTO class, Quill automatically validates it at boot and injects the hydrated object into your handler.

## Creating a DTO

DTOs are plain PHP classes with typed constructor properties and validation attributes.

```php
<?php

namespace Dtos;

use Quill\Validation\DTO;
use Quill\Attributes\Required;
use Quill\Attributes\Email;
use Quill\Attributes\MinLength;
use Quill\Attributes\Min;
use Quill\Attributes\Max;
use Quill\Attributes\Regex;
use Quill\Attributes\Nullable;
use Quill\Attributes\Boolean;
use Quill\Attributes\Numeric;

class CreateUserDto extends DTO
{
    public function __construct(
        #[Required, Email]
        public readonly string $email,

        #[Required, MinLength(8)]
        public readonly string $password,

        #[Required, Min(0), Max(150)]
        public readonly int $age,

        #[Nullable]
        public readonly ?string $bio = null,
    ) {}
}
```

---

## Validation Attributes

| Attribute | Description |
|---|---|
| `#[Required]` | Field must be present and non-null |
| `#[Email]` | Must be a valid RFC email address |
| `#[MinLength(n)]` | String must be at least `n` characters |
| `#[MaxLength(n)]` | String must be at most `n` characters |
| `#[Min(n)]` | Numeric value must be ≥ `n` |
| `#[Max(n)]` | Numeric value must be ≤ `n` |
| `#[Regex(pattern)]` | Value must match the given regex |
| `#[Numeric]` | Value must be numeric |
| `#[Boolean]` | Value must be a boolean |
| `#[Nullable]` | Field may be null/absent |

---

## Using DTOs in Handlers

```php
<?php

namespace Handlers;

use Dtos\CreateUserDto;
use Quill\Http\Request;

class UserHandler
{
    public function store(Request $req): array
    {
        $dto = CreateUserDto::fromRequest($req); // validated, throws 422 on failure
        return ['id' => 1, 'email' => $dto->email];
    }
}
```

---

## Validation Errors

When validation fails, Quill automatically returns a structured `422 Unprocessable Entity` response.

```json
{
  "status": 422,
  "error": "Validation Failed",
  "errors": {
    "email": ["Must be a valid email address"],
    "password": ["Must be at least 8 characters"]
  }
}
```

---

## What's Next?

- Explore the **[Middleware Pipeline](middleware.md)**.
- Configure **[Logging](logging.md)** for your application.
