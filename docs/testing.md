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

On 2026-10-03, the student reported that the website's components and features had been tested and were working. The shared test transcript lists PHP lint plus `risk.php`, `weather.php`, `assessment.php`, `presentation.php`, and `awareness.php`, JavaScript syntax checks, and `map-boundary.test.cjs`. The repository's `docs/verification.md` also describes earlier disposable database and headless-browser checks, with Herd/Nginx and XAMPP acceptance still identified as local checks. Treat the team report as local manual testing, not as an independent run in this documentation session.

## Before a final classroom demonstration

- Confirm which exact `f1` commit will be presented and run the commands above on that checkout.
- Verify the project on the intended Herd/XAMPP instance, with the migration applied to a disposable copy first.
- Demonstrate resident report submission and admin review, refresh failure preserving history, risk edit reason/audit, and CSV export.
- Check internal-file denial on the actual web server. `.htaccess` does not apply to Nginx.
- Do not use live resident contact data in screenshots or sample records.
- State clearly that no field validation or predictive accuracy study has been completed.

For detailed edge cases, see `docs/backend-test-checklist.md` and `docs/verification.md`.
