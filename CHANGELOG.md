# Changelog

All notable changes to `ez-php/dotenv` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `Dotenv` — `.env` file loader that writes parsed values into `$_ENV` and `$_SERVER`
- Support for quoted string values (single and double quotes)
- Variable interpolation with `${VAR}` syntax inside double-quoted values
- Immutable loading mode — skips variables that are already set in the environment
- `DotenvException` for parse errors and missing files
