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

**Entry point**: `bin/migrate` — finds the Composer autoloader and runs `Migration\Console\Application` (symfony/console `^7.4 || ^8.0`).

**CLI layer** (`src/Console/`): thin symfony/console commands that delegate to the core classes, which still write progress with `echo`.
- `Application` — registers the commands, default command is `list` (running `migrate` alone never touches the DB), overrides `getHelp()` (overview shown by `migrate`, `list`, `--help`)
- `AbstractConfigCommand` — shared `-c|--config` option, `loadConfig()`, `HELP_CONVENTIONS` help block
- `InitCommand` (`init`), `ProviderCommand` (`provider <name>`), `NewCommand` (`new <name>`), `RunCommand` (`run`, the only command that modifies the DB)
- Help texts are written for humans *and* agents: every command's `setHelp()` follows the same sections (Effet, Préconditions, Idempotence, Sortie, Erreurs fréquentes, Exemples) + `HELP_CONVENTIONS`. `BinTest::testEachCommandHasStructuredHelp` enforces it; keep help and README in sync with behaviour.

**Core classes**:
- `MigrationCore` — setup (creates `migration_story` table), migrate (discovers and runs SQL files), execute (splits SQL by `---` separator, stores SHA1 checksum)
- `Migration` extends `MigrationCore` — opens the PDO connection from the config
- `MigrationConfigFile` — loads `migration-config.json` (or a PHP file via `array_path` traversal)
- `CreateMigration` — creates `YYYYMMDD-NN-name.sql` stubs in each provider directory
- `CreateProviderDirectory` — creates provider subdirectories (mysql, sqlite, postgres)

**Migration file format**: SQL files named `YYYYMMDD-NN-*.sql`, placed in provider-specific directories. Statements separated by `---`.

**Database support**: MySQL, SQLite, PostgreSQL via `fzed51/pdo-helper`.

**Testing**: `DbTestCase` uses SQLite via `PDOFactory::sqlite()`. `BinTest` runs CLI integration tests through `ApplicationTester` (symfony/console) (plus one smoke test spawning `bin/migrate`).

**PDOFactory column casing**: `PDOFactory` sets `PDO::ATTR_CASE = PDO::CASE_UPPER` on every connection it creates (MySQL, SQLite, PostgreSQL). All column names fetched via `fetchAll(PDO::FETCH_ASSOC)` are therefore **uppercase** — e.g. `migration_story.file` is accessed as `$row['FILE']`, `checksum` as `$row['CHECKSUM']`. This is intentional; never lowercase these keys when reading rows returned by `PDOFactory`-managed connections.

## Code conventions

- PHP 8.2+, strict types, PSR-2 style
- Comments and error messages are in French
- PHPStan level 6 — maintain type coverage
- Record every user-visible change in `CHANGELOG.md` (Keep a Changelog, in French); breaking changes also go in the README section "Migration depuis la v2"

## CI and releases

- `.github/workflows/ci.yml`: `composer lint` on PHP 8.2, `composer test` on PHP 8.2 → 8.5 with the lock, plus the newest PHP with `highest` dependencies. Add each new PHP version to the matrix.
- `composer.lock` must stay installable on PHP 8.2 (the lowest supported version).
- Release: date the version in `CHANGELOG.md` and its compare link, merge to `main`, then tag `vX.Y.Z` on `main` and publish the GitHub release (Packagist picks up the tag).
