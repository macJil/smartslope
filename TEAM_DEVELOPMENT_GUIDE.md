# SmartSlope: simple PHP structure

Every page directly includes `config.php` and `functions.php` using `__DIR__`.
Configuration contains database settings and a reusable PDO connection, with no
URL detection or base-path constants. Browser links are ordinary relative URLs.
Open the project at its site root, or at `/smartslope/` on XAMPP.

## Reading the code

`index.php` follows the reference project's straightforward login sequence:
prepared account query, `password_verify()`, session values, then a role-based
redirect. SmartSlope keeps PDO throughout. Failed login or registration stays on
the same form and displays a local error variable; successful submissions redirect.
Login and registration are separate branches, so one request handles one form.

Pages call `session_start()` and read `$_SESSION['user_id']` and
`$_SESSION['role']` directly. There are no authentication wrapper functions.
Each page explicitly includes only the modules it uses, with `__DIR__`.

Use ordinary `if` blocks with braces and put separate statements on separate lines.
Avoid adding new wrapper functions that only forward one call. Keep required checks
close to the operation they protect and use prepared statements for user values.

- `index.php`: registration and a direct prepared-query/password/session login.
- `dashboard.php`: map, reading history and location selection.
- `admin.php`: administrator actions, CSV workflows and reading edits.
- `report.php`, `readings.php`, `logout.php`, `methodology.php`: their named pages.
- `functions.php`: small shared input/output, error, date and flash helpers (about 3 KB).
- `includes/data.php`: prepared PDO operations grouped by Users, Locations, Readings and Reports.
- `includes/risk.php`: RiskAnalyzer and assessment helpers.
- `includes/geography.php`: boundary and susceptibility lookups.
- `includes/http.php`: one small JSON request function using PHP streams.
- `includes/weather.php`: weather fetching and reading refresh.
- `includes/csv.php`: CSV import and download.
- `includes/setup.php`: schema checks and migration helpers.
- `includes/awareness.php`: report types and awareness notices.
- `includes/views.php`: reusable HTML shared by pages and AJAX responses.
- `partials/navbar.php`: the existing navigation markup.
- `partials/edit-reading.php`: the existing reading-edit form used by Admin.
- `api/`: JSON endpoints. Their returned HTML uses links relative to dashboard.php.
- `assets/`: existing styling, JavaScript, Leaflet, boundary data and map tiles.
- `database/`, `scripts/`, `tests/`, `docs/`, `data/`: schema, tools, tests and evidence.

The old `pages/` and `actions/` files are tiny compatibility redirects only.
They preserve old links and POST submissions; edit the root pages instead.
Shared logic is split by responsibility under `includes/`; there is no bootstrap loader.

## If login fails

1. Start Apache and MySQL in XAMPP or MAMP.
2. Edit `config.php`. Check DB_HOST, DB_PORT, DB_NAME,
   DB_USER and DB_PASS against your actual local database server.
   The supplied constants are local defaults; enter your actual settings. Your database is not changed by applying the patch.
3. Run `php scripts/check_database.php`. It checks the connection and required
   columns without changing data or printing the password.
4. An invalid-password message means the account was not authenticated. A
   connection/schema message means setup must be fixed before login can work.
5. To reset a known administrator account, use the existing interactive tool:
   `php scripts/create_admin.php admin your-email@example.com +639123456780`.
   It prompts for a new password. Do not replace a user's password hash manually.

The shipped new-database seed is admin/admin123; rotate it before real use.
For an existing database, do not reimport schema.sql to fix login. Back it up
before migrations. This branch expects users, locations and events; another
branch's five-table database is not automatically interchangeable.

## Testing

Run the commands in `docs/testing.md`. Check login/logout, resident and admin
permissions, reports, CSV, weather failure fallback and map selection locally.
Use only a disposable database ending in `_test` for database tests.

The earlier requested reductions remain: no CSRF tokens, signed CSVs, formula
escaping, provider cache/budget, or transaction rollback. Session roles, prepared
SQL, password hashing, escaping, necessary field checks and risk-data checks remain.
