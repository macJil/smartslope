# Verification record

## Branch reviewed
Documentation review used branch `f1` at application commit `2bc89e742301721c9815b8df59c22b908a0f6db3` on 2026-10-03. The repository contains focused PHP and Node tests; this document distinguishes the student's reported local run from checks independently repeated during documentation work.

## Student-reported final test run
On 2026-10-03 the student reported that the website components and features had been tested and were working. The shared command transcript lists:

- PHP syntax checks for repository PHP files
- `php tests/risk.php`
- `php tests/weather.php`
- `php tests/assessment.php`
- `php tests/presentation.php`
- `php tests/awareness.php`
- `node --check assets/js/dashboard.js`
- `node --check assets/js/location-address.js`
- `node tests/map-boundary.test.cjs`

This is the team's local verification report. The documentation pass reviewed the branch files and test definitions; it did not provision the team's local database or re-run their browser/server session.

## Earlier recorded validation
The previous verification notes in this repository record syntax/unit checks, disposable MySQL/MariaDB integration, provider and headless-browser checks from earlier branch work. Those records are historical evidence for those tested revisions; they should not be interpreted as fresh Herd/Nginx or XAMPP checks on the current `f1` commit.

## Remaining environment-specific checks
Before deployment or a graded demonstration, run the included checks on the final checkout and verify actual HTTP behavior on the chosen host. In particular:

- Herd/Nginx access rules must block `.env` and internal directories; `.htaccess` does not apply there.
- XAMPP/Apache must have overrides enabled for its `.htaccess` protections to work.
- Confirm PDO MySQL/cURL, existing-database migration, resident/admin workflows, provider failure handling and CSV output on the actual local setup.
- Keep all test accounts and reports synthetic.

Software tests verify code paths and rendering rules, not landslide prediction accuracy. No local event catalogue or validated susceptibility subset is included. `docs/local-validation.csv` contains column headings only.
