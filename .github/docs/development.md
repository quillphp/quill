# Development & Contributing

Guidelines for testing, contributing, and maintaining the QuillPHP framework.

## Testing

QuillPHP uses PHPUnit for unit and integration testing.

```bash
# Run all tests (PHP 8.3 and 8.4 in CI)
composer test

# Run with coverage (requires xdebug or pcov)
vendor/bin/phpunit --coverage-text

# Run static analysis (PHPStan level 6)
vendor/bin/phpstan analyse
```

### Test Suites

- `AppTest` — Core orchestrator and lifecycle tests.
- `RouterTest` — FastRoute compilation and dispatch tests.
- `ValidatorTest` — DTO hydration and attribute validation tests.
- `CorsTest` — Preflight and CORS header tests.
- `MiddlewareTest` — Pipeline execution and onion pattern tests.

---

## Contributing

We welcome contributions from the community!

1. **Fork the repository** and create a feature branch.
2. **Implement your changes**, ensuring that you maintain the core philosophy of zero-reflection on the hot path.
3. **Write tests** for your feature or bug fix.
4. **Ensure all checks pass**: `composer test` and `vendor/bin/phpstan analyse`.
5. **Benchmark your changes**: Ensure you haven't introduced any performance regressions on the hot path.
6. **Open a pull request** against the `main` branch.

---

## Performance Regressions

Always run the full benchmark suite before and after significant architectural changes:

```bash
php scripts/benchmark.php
```

If your change increases latency by more than **2µs** per request on the in-process benchmark, it should be re-evaluated.

---

## License

QuillPHP is open-source software licensed under the **MIT License**. See [LICENSE](LICENSE) for details.
