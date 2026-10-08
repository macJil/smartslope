# SmartSlope: simple PHP structure

Every page directly includes `config.php` and `functions.php` using `__DIR__`.
Configuration contains database settings and a reusable PDO connection, with no
URL detection or base-path constants. Browser links are ordinary relative URLs.
Open the project at its site root, or at `/smartslope/` on XAMPP.

- `index.php`: registration and a direct prepared-query/password/session login.
- `dashboard.php`: map, reading history and location selection.
- `admin.php`: administrator actions, CSV workflows and reading edits.
- `report.php`, `readings.php`, `logout.php`, `methodology.php`: their named pages.
- `functions.php`: helpers, PDO functions, weather, CSV and the RiskAnalyzer class,
  grouped into named sections. It is about 60 KB; preserving existing analysis
  and HTML rendering takes more than the illustrative 32 KB in the target tree.
- `partials/navbar.php`: the existing navigation markup.
- `partials/edit-reading.php`: the existing reading-edit form used by Admin.
- `api/`: JSON endpoints. Their returned HTML uses links relative to dashboard.php.
- `assets/`: existing styling, JavaScript, Leaflet, boundary data and map tiles.
- `database/`, `scripts/`, `tests/`, `docs/`, `data/`: schema, tools, tests and evidence.

The old `pages/` and `actions/` files are tiny compatibility redirects only.
They preserve old links and POST submissions; edit the root pages instead.
The old `app/` implementation has been consolidated into `functions.php`.

## If login fails

1. Start MySQL in dbngin/MySQL on your Mac, or MySQL in XAMPP.
2. Keep your existing `.env`. Check DB_HOST, DB_PORT, DB_DATABASE,
   DB_USERNAME and DB_PASSWORD against your actual local database server.
   The patch does not replace your database credentials or your database.
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
