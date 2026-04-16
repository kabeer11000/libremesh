# LibreMesh Git Hooks

This project uses [pre-commit](https://pre-commit.com/) framework for managing git hooks.

## Quick Start

1. **Install pre-commit** (requires Python):
   ```bash
   pip install pre-commit
   ```

2. **Install hooks**:
   ```bash
   pre-commit install
   ```

3. **Run manually** (optional):
   ```bash
   pre-commit run --all-files
   ```

## What Hooks Run

| Hook | Description |
|------|-------------|
| `trailing-whitespace` | Removes trailing whitespace |
| `end-of-file-fixer` | Ensures files end with newline |
| `check-yaml` | Validates YAML files |
| `check-json` | Validates JSON files |
| `mixed-line-ending` | Normalizes line endings to LF |
| `php-syntax` | PHP syntax validation via `php -l` |
| `php-cs-fixer` | Code style check (dry-run) |
| `phpstan` | Static analysis (if configured) |

## PHP Tools Needed for Full Functionality

For the PHP-specific hooks to work, install these tools:

```bash
# PHP-CS-Fixer (code style)
curl -L https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/latest/download/php-cs-fixer.phar -o php-cs-fixer
chmod +x php-cs-fixer

# PHPStan (static analysis) - requires composer
composer require --dev phpstan/phpstan
```

## Skipping Hooks

To skip hooks temporarily:
```bash
git commit --no-verify -m "Emergency fix"
```

## Update Hooks

```bash
pre-commit autoupdate
pre-commit install
```