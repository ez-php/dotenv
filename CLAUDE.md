# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "0.*"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3310** | **6381** |

Only set a port for services the module actually uses. Modules without external services need no port config.

---

# Package: ez-php/dotenv

`.env` file loader and parser — zero-dependency, standalone library.

This package has no knowledge of the Application, Container, or any other ez-php package. It is loaded by the application entry point before the framework bootstraps.

---

## Source Structure

```
src/
├── Dotenv.php   — Loads a .env file and populates $_ENV, $_SERVER, and putenv(); immutable by default
└── Parser.php   — Parses raw .env file content into a key→value map

tests/
├── TestCase.php              — Base PHPUnit test case
├── Env/DotenvTest.php        — Covers Dotenv: load, safeLoad, missing file, immutability
└── Env/ParserTest.php        — Covers Parser: all syntax forms, interpolation, edge cases
```

---

## Key Classes and Responsibilities

### Dotenv (`src/Dotenv.php`)

Responsible for locating the `.env` file, reading it, and writing the parsed variables into the PHP environment.

**Construction:**
```php
// Named constructor (preferred):
$dotenv = Dotenv::createImmutable('/path/to/project');
$dotenv = Dotenv::createImmutable('/path/to/project', '.env.testing');

// Direct:
$dotenv = new Dotenv('/path/to/project', '.env');
```

| Method | Behaviour |
|---|---|
| `load()` | Reads and parses the file; throws `RuntimeException` if missing or unreadable |
| `safeLoad()` | Like `load()` but silently skips a missing file; never throws |

**Immutability rule (`populate()`):** A variable is written only if `getenv($key) === false` AND the key is absent from both `$_ENV` and `$_SERVER`. Variables already present in the real environment (set before PHP started, or by a test) are never overwritten.

Variables are written to all three destinations: `putenv()`, `$_ENV`, `$_SERVER`.

---

### Parser (`src/Parser.php`)

Pure function-style class. `parse(string $content): array<string, string>` — takes raw file content, returns a flat string-to-string map.

**Supported syntax:**

| Form | Example | Result |
|---|---|---|
| Unquoted | `KEY=value` | `'value'` (inline `# comment` stripped) |
| Double-quoted | `KEY="val"` | Escape sequences + variable interpolation |
| Single-quoted | `KEY='val'` | Literal; no escapes, no interpolation |
| Empty | `KEY=` or `KEY=""` or `KEY=''` | `''` |
| Export prefix | `export KEY=value` | Prefix stripped, then parsed normally |
| Comment line | `# comment` | Line ignored |
| No `=` sign | `INVALID` | Line skipped |
| Invalid key | `1KEY=val` | Line skipped (key must match `[A-Za-z_][A-Za-z0-9_]*`) |

**Double-quoted escape sequences:** `\n` `\t` `\r` `\"` `\\` `\$` — anything else: backslash kept as-is.

**Variable interpolation (double-quoted only):**
- `$VAR` — expands to previously parsed value or existing env value; empty string if undefined
- `${VAR}` — braced form, same resolution order
- Resolution order: already-parsed variables in this file → `getenv()` → `''`

**Parsing is line-by-line.** Multi-line values are not supported.

---

## Design Decisions and Constraints

- **Zero framework dependencies** — This package must remain usable without `ez-php/framework`. Do not import any Application, Container, Config, or ServiceProvider class.
- **Immutable-only** — There is intentionally no `loadMutable()` or `overload()`. Existing environment variables must not be overwritten; the runtime environment always wins over the file. This prevents CI/CD pipeline variables from being silently shadowed by a committed `.env`.
- **Three-destination write** — `putenv()`, `$_ENV`, and `$_SERVER` are all set for maximum compatibility. PHP functions and libraries may use any of the three.
- **`Parser` is stateless and not injectable** — `Dotenv` constructs `Parser` directly (`new Parser()`). There is no parser interface and no reason for one — the parsing format is fixed.
- **No type coercion** — All values are `string`. The application is responsible for casting to `int`, `bool`, etc. (e.g. `(bool) getenv('APP_DEBUG')`).
- **No validation or required-variable checks** — Variable presence checks belong in the application bootstrap, not in this package.

---

## Testing Approach

- **No external infrastructure required** — Tests are purely in-process. Temporary `.env` files are written to `sys_get_temp_dir()` and cleaned up in `tearDown`.
- **Immutability tests** — Set a variable via `putenv()` or `$_ENV` before calling `load()`; assert it is not overwritten.
- **Parser tests** — Pass raw strings directly to `Parser::parse()`; assert the returned array. No file I/O needed.
- **Environment cleanup** — Tests that call `putenv()` or modify `$_ENV` / `$_SERVER` must restore original values in `tearDown` to avoid leaking state between test methods.
- **`#[UsesClass]` required** — PHPUnit is configured with `beStrictAboutCoverageMetadata=true`. Declare indirectly used classes with `#[UsesClass]`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| Wiring Dotenv into the Application | Application entry point (`public/index.php` or `artisan`) |
| Type coercion of env values | Application config files (`config/app.php` etc.) |
| Validation of required variables | Application bootstrap layer |
| `.env.example` generation or diffing | Developer tooling / CI scripts |
| Multi-line value support | Out of scope — keep the parser simple |

