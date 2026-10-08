# Flattened sf2: verification and limits

Patch base: bf33d74541a2c41d2481e0def34ff31c89174714.

The branch already contained the earlier simplification. This patch consolidates
its PHP into root config.php/functions.php and the root page files. The application
schema and every asset file are unchanged. RiskAnalyzer remains a class, within
functions.php. Existing page/action URLs redirect to the root handlers.

The reported catch-all message did not identify the cause of the user's local
failure. Login works against the supplied schema with the unmodified seeded
administrator password, an existing test resident, and a newly registered account.
Actual PDO connection/schema errors are now shown as setup guidance inside the
existing login form; raw SQL, passwords and stack traces are not displayed.
The patch cannot start or configure the user's local MySQL service. Run
`php scripts/check_database.php` if local login still fails.

Checks performed:

- Syntax checks on all 32 PHP files, all bundled JavaScript syntax checks and
  both Irisan boundary tests passed.
- All six existing PHP suites passed: risk, weather, assessment, presentation,
  awareness and CSV.
- Disposable MariaDB: 16 workflow, five CSV import and seven fixture-provider
  refresh checks passed on the flattened version. Baseline tests also passed.
- 154 assertions in the combined verification harness passed, including suite
  completion, HTTP workflows, HTML comparisons and relative path checks.
- Login/logout, registration, wrong passwords, role restrictions, reports,
  review/resolve, reading edits, bulk actions and CSV download/import passed.
- Both site-root and `/smartslope/` installations were exercised. HTML links,
  assets, AJAX GET and old compatibility URLs stayed within the correct folder.
- Missing-database and stopped-database scenarios returned explanatory messages
  on the login form instead of the old generic catch-all screen.
- Page HTML matched the current branch after normalizing equivalent browser
  paths, internal route moves and stylesheet version timestamps.

The headless browser download failed, so pixel rendering and interactive mobile
layout were not independently inspected. Actual Herd/XAMPP configuration and
successful live Open-Meteo/Nominatim responses remain local verification steps.
The rainfall calculation and saving workflow used deterministic provider fixtures.
The earlier security/CSV/provider simplifications and their documented limitations
are unchanged by this patch.
