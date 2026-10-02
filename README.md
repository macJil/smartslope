# SmartSlope — Barangay Irisan academic prototype

PHP 8.1+ / PDO MySQL, local Bootstrap and Leaflet, jQuery AJAX and an authenticated JSON reading endpoint. The three-table database is **unchanged**: `users`, `locations`, `events`. `events.type` separates API readings from ground reports; `events.source` identifies Open-Meteo. No physical sensors or official landslide warnings are claimed.

## File structure and naming

| Path | Role |
| --- | --- |
| `index.php`, `dashboard.php`, `admin.php`, `report.php`, `readings.php`, `logout.php` | Browser routes |
| `pages/*.php` | Login, dashboard and report templates |
| `actions/*.php` | POST-only map/reading changes |
| `api/readings.php` | GET saved readings as JSON; CSRF-protected POST refreshes and stores a provider reading |
| `app/config.php`, `app/RiskAnalyzer.php` | Shared PDO/helpers and OOP risk rule |
| `database/schema.sql` | Copy of the original s1 three-table SQL; **do not import into an existing DB** |
| `assets/js/dashboard.js`, `assets/js/irisan-boundary.js`, `assets/js/vendor/` | AJAX, polygon validation and local jQuery |
| `assets/vendor/leaflet/`, `assets/map/`, `assets/map-tiles/` | Offline map library, Irisan polygon and local map tiles |
| `scripts/create_admin.php`, `tests/risk.php` | Local admin password rotation and risk boundary check |

Use `snake_case` for PHP/SQL functions and filenames, `PascalCase` for PHP classes, and lower-case `kebab-case` for browser assets. Includes are anchored with `__DIR__`, while browser routes use `url()` for Herd root and XAMPP's `/smartslope` subfolder.

## Run with your **current** database

1. Back up the existing MySQL database. Keep its `users`, `locations` and `events` tables and data. Do **not** run a migration or import `database/schema.sql` over it. The current schema already has the forecast, soil-moisture, contact, risk, `archived`, and `stale` columns used by this code.
2. Copy `.env.example` to `.env`. Set your real MySQL host/port/login and `DB_DATABASE=smartslope_mvp`. With Herd + DBngin, use the DBngin credentials; with XAMPP, use its MySQL credentials. Leave `APP_BASE_PATH=` empty to detect the website path automatically.
3. If the DB still has the seeded `admin` account with its published `admin123` password, rotate it locally: `php scripts/create_admin.php admin admin@example.test +639123456780`. Supply your own unique email/phone and a password of at least 12 characters. The command creates an admin only when that username does not already exist.
4. Open the Herd site root or `http://localhost/smartslope/`. Register a resident, sign in, click a map point inside Irisan, use Refresh, submit a report, then check the admin list, risk-only editing, archival, and CSV export. New provider readings need an internet connection; Leaflet, map tiles, Bootstrap and jQuery load locally. Selecting a dashboard or report map point sends its coordinates to OpenStreetMap Nominatim for optional street-address lookup. If lookup is unavailable, readings retain their coordinates and reports can use the house/landmark field.

If you have an *older* three-table schema without these existing columns, it is not the current `s1` schema. Restore a backup and compare it with `database/schema.sql` before using the application. A nine-table `v4` database is a different schema and is not automatically converted.

## Weather and risk

Open-Meteo hourly precipitation feeds 1/24/72-hour totals. Complete windows are required for a new category; a missing window never becomes `low`. `normal`: 10/25/50 mm; `medium`: 25/50/100 mm; `high`: 50/100/150 mm (1/24/72 h); otherwise `low`. These are explicitly prototype thresholds, not official alert criteria. Soil moisture and next-day rain are model context, not sensor readings. The MySQL observation time is UTC and displayed as Philippine time. A failed or stale provider response does not erase previously stored readings. Every successful click/refresh adds a reading, even during the same provider hour.

## Security and verification

Registration validates inputs; passwords use `password_hash()` and `password_verify()`. Login regenerates the session ID and roles come from the server. All changes use POST with a small session-bound CSRF token; read-only exports and JSON GET are GET. PDO binds user inputs and map/report text is escaped before HTML insertion. The Apache `.htaccess` blocks `.env` and `.sql`; configure the same rule in another server and verify these URLs are denied. Do not commit `.env`.

Check PHP syntax with `php -l` on modified PHP files, run `php tests/risk.php` and `node --test tests/map-boundary.test.cjs`, and test the real MySQL/HTTP flows under Herd and XAMPP. Try login/registration, a resident requesting admin actions, a forged POST without CSRF, two Irisan points, repeat refresh, provider failure, report review, reading correction/archival and CSV. `node --check assets/js/dashboard.js` checks JavaScript syntax. The project's Startup business-model deck and team presentation are separate deliverables.

WEBSYS1: PHP/MySQL CRUD, PDO, one OOP risk class, jQuery AJAX, JSON API and web security. IMDBSE2: linked frontend and database CRUD. **Teacher-specific sensor-table note:** this deliberately retains three tables and records the virtual weather provider in `events.source`; if your teacher explicitly grades a separate `sensors` table, a no-schema-change rule cannot satisfy that particular table requirement.
