# Testing and verification

## Automated checks in the repository

Run from the project root with PHP 8.1+ and Node.js available:

```bash
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
php tests/risk.php
php tests/weather.php
php tests/assessment.php
php tests/presentation.php
php tests/awareness.php
node --check assets/js/dashboard.js
node --check assets/js/location-address.js
node tests/map-boundary.test.cjs
```

`tests/csv.php` checks CSV encoding, formula protection and invalid input handling. `tests/integration-awareness.php` exercises database-backed reads/writes and must use a disposable database whose name ends in `_test`. The integration test changes rows and should not run on a user's real database.

For this revision, also run `php tests/csv.php`. After backing up a legacy database, rehearse `php database/migrate-awareness.php` on a disposable copy, inspect matching event/detail counts and representative copied values, and run the integration test against a fresh disposable five-table schema.

## What the checks cover

- Risk threshold boundaries, explanations, missing and invalid values.
- Hourly series gaps, duplicate times, units, numeric validity and freshness.
- Assessment status for current, old, future, incomplete and adjusted readings.
- Escaping, displayed status, map/address labels, admin-only controls and modal content.
- Irisan polygon boundary acceptance/rejection in JavaScript.
- Awareness notices, unverified susceptibility behavior, report-time conversion, caching and request limits.
- Database-backed report review, risk edit audit, history, location CSV import/export and archive behavior in a disposable database.

These tests check application behavior; they do not establish real-world landslide accuracy.

## Team verification record

On 2026-10-03, the student reported that the website's components and features had been tested and were working on an earlier `f1` commit. The shared transcript lists PHP lint plus `risk.php`, `weather.php`, `assessment.php`, `presentation.php`, and `awareness.php`, JavaScript syntax checks, and `map-boundary.test.cjs`. Commit `ec700313` then changed the schema and repository queries. That earlier test report does not verify this new migration and five-table revision. The repository's `docs/verification.md` describes older disposable database and browser checks; these are historical evidence for their tested revisions.

## Before a final classroom demonstration

- Confirm which exact `f1` commit will be presented and run the commands above on that checkout. Run the disposable DB integration test against the five-table version.
- Verify the project on the intended Herd/XAMPP instance, with the migration applied to a disposable copy first.
- Demonstrate resident report submission and admin review, refresh failure preserving history, risk edit reason/audit, and CSV export.
- Check internal-file denial on the actual web server. `.htaccess` does not apply to Nginx.
- Do not use live resident contact data in screenshots or sample records.
- State clearly that no field validation or predictive accuracy study has been completed.

For detailed edge cases, see `docs/backend-test-checklist.md` and `docs/verification.md`.
