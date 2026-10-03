# Verification record

## Reviewed revision

This documentation review inspected branch `f1` at application commit `ec7003130d1fd483dce3c167adbd07f662510407` (2026-10-03). That commit added normalized `readings` and `reports` detail tables, changed repository queries and migration, and accidentally introduced Git conflict markers into `README.md`. The README and documentation were corrected in subsequent documentation commits. The review fetched the current PHP, browser JavaScript, test code and GeoJSON from GitHub. In this workspace, all top-level `assets/js/*.js` passed `node --check` and `node tests/map-boundary.test.cjs` passed 2/2. PHP is not installed here, and no PHP/MySQL or browser/server integration test was run during this review.

## Earlier team report

On 2026-10-03, before the five-table commit, the student reported that the website components worked locally and shared a transcript with PHP lint, `tests/risk.php`, `tests/weather.php`, `tests/assessment.php`, `tests/presentation.php`, `tests/awareness.php`, JavaScript syntax checks and `tests/map-boundary.test.cjs`. That is useful evidence for the earlier revision, not a passing test report for the new schema and migration.

Historical verification in this repository also describes disposable database and browser checks on earlier branches. Do not treat them as fresh Herd or XAMPP acceptance for the current commit.

## Required final checks on the chosen checkout

1. Run PHP lint on all PHP files, the PHP test commands in `docs/testing.md`, the Node boundary and syntax checks.
2. Back up the legacy wide-`events` database and run `php database/migrate-awareness.php` against a disposable copy first. Confirm five tables, matching `events`/`readings` and `events`/`reports` counts, representative copied values, event IDs, contacts, review status and audit fields. Then rehearse an interrupted/repeated migration if feasible. MySQL DDL is not fully transactional.
3. Run `php tests/integration-awareness.php` only on a disposable database ending in `_test`. It writes and deletes test rows.
4. On the actual Herd/Nginx and XAMPP/Apache setup used for demonstration, test registration/login, resident/admin boundaries, refresh and provider failure, report review, reading edit/archival, location import/export and CSV, and direct access denial for `.env`/internal paths. Nginx ignores `.htaccess`.
5. Keep synthetic test accounts/reports. `docs/local-validation.csv` contains headings only; software tests do not establish landslide prediction accuracy.

Record the final commit SHA, commands, output and test environment in the team's defense evidence after these steps pass. Until then, describe the current revision as **reviewed but not independently integration tested**.
