# ez-php/dotenv

Lightweight `.env` file loader for PHP — supports quoted values, variable interpolation, and immutable loading. Zero dependencies.

[![CI](https://github.com/ez-php/dotenv/actions/workflows/ci.yml/badge.svg)](https://github.com/ez-php/dotenv/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/ez-php/dotenv/branch/main/graph/badge.svg)](https://codecov.io/gh/ez-php/dotenv)

## Requirements

- PHP 8.5+

## Installation

```bash
composer require ez-php/dotenv
```

## Usage

```php
use EzPhp\Env\Dotenv;

// Load .env from the project root (immutable — never overwrites existing env vars)
Dotenv::createImmutable(__DIR__)->load();

// Silently skip if the file doesn't exist (useful in production)
Dotenv::createImmutable(__DIR__)->safeLoad();

// Custom filename
Dotenv::createImmutable(__DIR__, '.env.testing')->load();
```

Variables are written to `$_ENV`, `$_SERVER`, and `getenv()`.

## Supported syntax

```dotenv
# Comments are ignored
APP_NAME=My App
APP_ENV=production

# Quoted values preserve whitespace and support escapes
GREETING="Hello, World!\nWelcome."

# Single-quoted values are literal — no interpolation, no escapes
LITERAL='value with $dollar and \n backslash'

# Variable interpolation in double-quoted values
BASE_URL=https://example.com
API_URL="${BASE_URL}/api/v1"

# export prefix is stripped
export SECRET_KEY=abc123

# Empty values
EMPTY=
```

## Immutability

Variables already present in the environment (set before loading) are never overwritten. This means real environment variables (e.g. set in Docker or CI) always take precedence over `.env` file values.

## Parser

`Parser` is the internal class that turns raw `.env` file content into a flat `array<string, string>` map. You can use it directly if you want to parse `.env` content without writing to the environment:

```php
use EzPhp\Env\Parser;

$vars = (new Parser())->parse(file_get_contents('.env'));
// ['APP_NAME' => 'My App', 'APP_ENV' => 'production', ...]
```

All syntax forms are supported: unquoted, double-quoted (escape sequences + interpolation), single-quoted (literal), empty values, `export` prefix, and inline comments.

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
