# Tests

This folder contains deterministic unit/contract tests and opt-in MySQL integration tests. Use synthetic data only.

## Requirements

- PHP 8.1+; MySQL/PDO is needed only for database tests.
- Node.js is needed for the Irisan boundary test.
- For integration, configure `.env` to a disposable database with a name ending in `_test`, import `database/schema.sql`, then run the migration command if testing legacy conversion.

## Run

```sh
php tests/risk.php
php tests/assessment.php
php tests/weather.php
php tests/auth.php
php tests/reports.php
php tests/admin-workflow.php
php tests/api-readings.php
php tests/csv.php
node --test tests/map-boundary.test.cjs
DB_DATABASE=smartslope_test php tests/integration.php
```

The integration script checks the effective app configuration and exits unless its DB name ends in `_test`. The migration test verifies an already-normalized test schema; full legacy backfill rehearsal uses a separate disposable copy and must compare detail values before/after. Never override a production `.env` casually: this app loads `.env` and process environment, and shell startup configuration can affect which value wins.
