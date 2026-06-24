# Recipe Manager - Agent Guide

## Project Overview
PHP 8.3 recipe manager with single-file architecture. Backend in `src/RecipeController.php`, frontend in `public/index.php` (inline HTML/PHP + Bootstrap 5.3).

## Commands
```bash
# Install dependencies
composer install
npm install

# Lint (runs in order: php-linter → php-cs-fixer → phpstan → rector)
composer lint

# Auto-fix lint issues
composer lint:fix

# Run tests (none exist yet)
composer test
```

## Key Architecture
- **Entry point**: `public/index.php` → instantiates `RecipeController`, calls `handleRequest()` then `getIndexData()`
- **Database**: MySQL via PDO. Config in `config.ini` (not tracked; see `src/RecipeController.php:16-21` for defaults)
- **Session**: Used for flash messages
- **No tests** currently exist

## Database Schema (expected)
```sql
CREATE TABLE categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE recipes (id INT AUTO_INCREMENT PRIMARY KEY, category_id INT, title VARCHAR(255), ingredients TEXT, instructions TEXT, servings INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
```

## Conventions
- PHP 8.3 strict types (`declare(strict_types=1)`)
- PSR-12 + custom php-cs-fixer rules (`.php-cs-fixer.php`)
- PHPStan level 8 (`phpstan.neon.dist`)
- Markdown linting via npm (`markdownlint`)

## Gotchas
- `config.ini` must exist at repo root for DB connection (falls back to localhost/root/no-password)
- Single `public/index.php` contains all HTML/JS/PHP - no template separation
- Ingredient scaling is client-side JS only
- No autoloader beyond Composer PSR-4; single class in `src/`