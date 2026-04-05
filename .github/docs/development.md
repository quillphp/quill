# Development & Contributing

Guidelines for running tests, static analysis, and contributing to QuillPHP.

---

## Running Tests

QuillPHP uses [Pest](https://pestphp.com/) for all testing.

```bash
# Run the full test suite
composer test

# Run with verbose output
vendor/bin/pest --verbose

# Run a specific test file
vendor/bin/pest tests/Unit/Routing/RouterTest.php
```

Tests are split into two layers:

| Suite | Path | Covers |
| :--- | :--- | :--- |
| Unit | `tests/Unit/` | Individual classes in isolation |
| Feature | `tests/Feature/` | End-to-end request/response flows |

### Unit test files

| File | What it tests |
| :--- | :--- |
| `Unit/Routing/RouterTest.php` | Route compilation, matching, parameters |
| `Unit/Runtime/RuntimeTest.php` | FFI boot, init, availability flag |
| `Unit/Runtime/JsonTest.php` | `Json::encode` / `Json::decode` round-trips |
| `Unit/Validation/ValidatorTest.php` | DTO reflection, schema registration, validation |
| `Unit/Http/RequestTest.php` | Request parsing and accessors |

### Top-level test files

| File | What it tests |
| :--- | :--- |
| `AppTest.php` | Full app lifecycle and route dispatch |
| `CacheTest.php` | Cache driver behaviour |
| `ContainerTest.php` | DI container resolution |
| `CorsTest.php` | CORS preflight and header injection |
| `DatabaseTest.php` | Database abstraction layer |
| `MiddlewareTest.php` | Pipeline execution and ordering |
| `OpenApiTest.php` | OpenAPI spec generation |
| `SecurityTest.php` | Security header middleware |

---

## Static Analysis

QuillPHP targets **PHPStan level 9**:

```bash
composer analyze
# or
vendor/bin/phpstan analyse --no-progress
```

All `@phpstan-ignore-next-line` annotations must include a brief justification comment.

---

## Local Benchmarks

Run the HTTP benchmark suite locally (requires `wrk`):

```bash
# macOS
brew install wrk

# Run default benchmark (5s, 50 connections, 2 workers)
composer bench

# Custom parameters
DURATION=30 CONNECTIONS=200 THREADS=8 composer bench
```

---

## Contributing

1. **Fork** the repository and create a feature branch from `main`.
2. **Implement** your changes, keeping the hot path free of reflection and object allocation.
3. **Write tests** — all new behaviour must be covered by Pest tests.
4. **Pass all checks**: `composer test` and `composer analyze` must both succeed.
5. **Benchmark** — run `composer bench` before and after; performance regressions on the hot path require justification.
6. **Open a pull request** against `main`. The PR description must include the `## Checklist` section from the template.

---

## License

QuillPHP is released under the **MIT License**. See [LICENSE](../../LICENSE) for details.

