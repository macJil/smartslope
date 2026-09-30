# SmartSlope academic prototype

Plain PHP, MySQL, Bootstrap, local jQuery and one weather provider. The study area is Barangay Irisan, Baguio City. Risk is a provisional rainfall indicator, not a validated landslide forecast or official warning.

## Requirements

PHP 8.1+ with PDO MySQL and cURL (or HTTPS URL streams), MySQL 8 or compatible MariaDB, and Apache/XAMPP or Herd. Node is only needed for developer tests. No Composer/npm installation is needed to run the website.

## Fresh setup

1. Put this entire folder in XAMPP's `htdocs/landslide`, or link it in Herd.
2. Copy `.env.example` to `.env`. Set your MySQL credentials. Use `APP_BASE_PATH="/landslide"` for that XAMPP folder; use an empty value for a Herd domain.
3. Import `db.sql` in phpMyAdmin/MySQL Workbench. It creates `smartslope_mvp` and seeds Irisan. It does not create a default administrator or claim any location coordinates are verified.
4. Open the root URL and register your administrator's intended account. In your local database console, promote that exact account:
   ```sql
   UPDATE users SET role = 'admin' WHERE username = 'your_chosen_username';
   ```
   Sign out and sign in again. Public registration always creates a resident account.
5. Add a real study location through **Manage locations**, using independently checked coordinates within Irisan. Create a separate resident account to demonstrate reports and weather refresh.
6. As a resident, choose the location and refresh. Current/hourly Open-Meteo provider data are saved; complete 1/24/72-hour rainfall totals produce a prototype indicator. If data or persistence fail, the page explains that condition.
7. As administrator, add/edit a sourced reading, archive/restore it, export CSV, and review a resident report.

Do not import the synthetic test seed into a public deployment. Existing installations should back up their database and use the relevant scripts in `sql/` and `db_migration_archive_readings.sql` only if the corresponding columns/tables are missing. This refactor itself changes no SQL schema and needs no migration from the reviewed `ver1` schema. `CREATE TABLE IF NOT EXISTS` does not upgrade old tables.

Apache/XAMPP uses the included `.htaccess` to block configuration, SQL and development files. Herd uses Nginx and ignores `.htaccess`; keep it local or configure equivalent restrictions before hosting publicly.

## Layout and flow

| Path | Responsibility |
|---|---|
| `index.php`, `configs/` | Login, registration, logout and environment settings |
| `admin/` | Report review, locations, reading create/edit/archive/export |
| `resident/` | Risk/weather dashboard and report form |
| `app/` | One bootstrap, PDO repositories, weather client, risk calculation and helpers |
| `api/` | Read-only latest reading JSON and authenticated weather refresh |
| `assets/` | Only the CSS/JS used by the pages; local jQuery and Bootstrap |
| `sql/`, `db.sql` | Fresh schema and existing database repair scripts |
| `tests/`, `docs/` | Verification and requirement evidence |

The browser sends a selected location and CSRF token via jQuery AJAX to `api/weather.php`. PHP validates the session/location, requests provider data, calculates rainfall totals and saves observations plus a risk summary in a transaction. JSON updates the dashboard. Normal administrative forms use POST/redirect/GET and PDO classes. Sessions contain identity, CSRF and flash messages; MySQL owns persistent records. There is no client-side global store or framework.

URLs changed from `components/admins/...` and `components/users/...` to `admin/...` and `resident/...`; update saved bookmarks. Replace the old application folder with this clean tree rather than overlaying it and leaving obsolete pages accessible. Preserve your local `.env` and database separately.

## Verification

```sh
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
node --test tests/*.test.cjs
php tests/repositories.php
```

The repository contract test requires PDO SQLite and uses an in-memory synthetic fixture. It does not validate MySQL-specific upserts, constraints or migrations. Complete the MySQL/HTTP acceptance steps in `docs/REQUIREMENTS.md` before submission.

## Scope and remaining decisions

No sensors, AI/ML, payment/subscription functionality, geographic expansion or map library is included. Provider soil-moisture fields are preserved because the existing dashboard displays them; they do not affect risk. This refactor does not silently replace the database design: email/contact number, persistent alerts, provider-table naming and numeric field sizing remain separate pending database decisions.

See `docs/REFACTOR.md` for reference analysis and changes; `docs/REQUIREMENTS.md` for the minimal requirements matrix and remaining submission work.
