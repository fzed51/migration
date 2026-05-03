# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer lint    # validate + phpcs (PSR-2) + phpstan (level 6)
composer test    # phpunit
```

Run a single test class:
```bash
./vendor/bin/phpunit --filter BinTest
```

## Architecture

This is a CLI tool for running SQL database migrations, published as a Composer library (`fzed51/migration`).

**Entry point**: `bin/migrate` — parses CLI flags and delegates to classes in `src/`.

**Core classes**:
- `MigrationCore` — setup (creates `migration_story` table), migrate (discovers and runs SQL files), execute (splits SQL by `---` separator, stores SHA1 checksum)
- `Migration` extends `MigrationCore` — adds CLI flag handling (`-i`, `-n`, `-p`, `-c`)
- `MigrationConfigFile` — loads `migration-config.json` (or a PHP file via `array_path` traversal)
- `CreateMigration` — creates `YYYYMMDD-NN-name.sql` stubs in each provider directory
- `CreateProviderDirectory` — creates provider subdirectories (mysql, sqlite, postgres)

**Migration file format**: SQL files named `YYYYMMDD-NN-*.sql`, placed in provider-specific directories. Statements separated by `---`.

**Database support**: MySQL, SQLite, PostgreSQL via `fzed51/pdo-helper`.

**Testing**: `DbTestCase` uses SQLite in-memory via `PDOFactory::sqlite()`. `BinTest` runs CLI integration tests by executing `bin/migrate` directly.

**PDOFactory column casing**: `PDOFactory` sets `PDO::ATTR_CASE = PDO::CASE_UPPER` on every connection it creates (MySQL, SQLite, PostgreSQL). All column names fetched via `fetchAll(PDO::FETCH_ASSOC)` are therefore **uppercase** — e.g. `migration_story.file` is accessed as `$row['FILE']`, `checksum` as `$row['CHECKSUM']`. This is intentional; never lowercase these keys when reading rows returned by `PDOFactory`-managed connections.

## Code conventions

- PHP 8.1+, strict types, PSR-2 style
- Comments and error messages are in French
- PHPStan level 6 — maintain type coverage
