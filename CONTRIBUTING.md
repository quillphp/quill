# Contributing to QuillPHP

Thank you for considering contributing to QuillPHP! We aim to build a high-performance framework with a welcoming community.

## Getting Started

1. **Fork the repository** on GitHub.
2. **Clone your fork** locally: `git clone https://github.com/your-username/quill.git`
3. **Install dependencies**: `composer install`

## Development Workflow

We strive for excellent code quality and zero regressions. Please run the following checks before submitting your code:

### 1. Tests (Pest)
We use Pest for testing. Make sure to test new features or bug fixes.
```bash
composer test
```

### 2. Static Analysis (PHPStan)
We enforce rigorous static analysis to catch bugs early.
```bash
composer analyze
```

## Pull Request Guidelines

- Create a feature branch from `main`: `git checkout -b feature/my-cool-feature`
- Keep your commits atomic and your PR focused on a single change.
- Fill out the provided Pull Request template completely.
- Ensure all GitHub Actions CI checks pass.
- Write clear, understandable commit messages.

Thank you for helping make QuillPHP better!
