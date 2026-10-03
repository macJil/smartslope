# SmartSlope — Landslide Alert and Community Reporting System (Prototype)

PHP 8.1+ / PDO MySQL, local Bootstrap and Leaflet, jQuery AJAX and an authenticated JSON reading endpoint. The three tables are retained, with additive awareness metadata in `events`: `users`, `locations`, `events`. `events.type` separates API readings from ground reports; `events.source` identifies Open-Meteo. No physical sensors or official landslide warnings are claimed.

## File structure and naming

| Path | Role |
| --- | --- |
| `index.php`, `dashboard.php`, `admin.php`, `report.php`, `readings.php`, `logout.php` | Browser routes |
| `pages/*.php` | Login, dashboard and report templates |
| `actions/*.php` | Map/reading actions; mutations require POST (the edit form itself uses GET) |
| `api/readings.php` | GET saved readings as JSON; CSRF-protected POST refreshes and stores a provider reading |
| `app/bootstrap.php` | Explicit shared dependency loader |
| `app/config.php` | Environment, base path and lazy PDO connection |
| `app/auth.php`, `app/helpers.php` | Sessions, authorization, CSRF, URLs, output escaping and CSV |
| `app/repositories.php` | Database operations |
| `app/weather.php` | Weather fetching, validation, aggregation and saving |
| `app/RiskAnalyzer.php`, `app/assessment.php` | OOP rainfall rules, explanations, freshness and override detection |
| `docs/` | Risk rules, API contract, installation checks and verification record |
| `database/schema.sql` | Updated three-table SQL for new databases; **do not import into an existing DB** |
| `assets/js/dashboard.js`, `assets/js/irisan-boundary.js`, `assets/js/vendor/` | AJAX, polygon validation and local jQuery |
| `assets/vendor/leaflet/`, `assets/map/`, `assets/map-tiles/` | Offline map library, Irisan polygon and local map tiles |
| `scripts/create_admin.php`, `tests/risk.php` | Local admin password rotation and risk boundary check |

Use `snake_case` for PHP/SQL functions and filenames, `PascalCase` for PHP classes, and lower-case `kebab-case` for browser assets. Includes are anchored with `__DIR__`, while browser routes use `url()` for Herd root and XAMPP's `/smartslope` subfolder.

## Run with your **current** database

1. Back up the existing three-table MySQL database. After configuring .env, run `php database/migrate-awareness.php` before serving updated pages. The restart-safe CLI migration adds nullable metadata columns and a history index; it retains existing tables and data. Do **not** import `database/schema.sql` over an existing database.
2. Copy `.env.example` to `.env`. Set your real MySQL host/port/login and `DB_DATABASE=smartslope_mvp`. With Herd + DBngin, use the DBngin credentials; with XAMPP, use its MySQL credentials. Leave `APP_BASE_PATH=` empty to detect the website path automatically.
3. If the DB still has the seeded `admin` account with its published `admin123` password, rotate it locally: `php scripts/create_admin.php admin admin@example.test +639123456780`. Supply your own unique email/phone and a password of at least 12 characters. The command creates an admin only when that username does not already exist.
4. Open the Herd site root or `http://localhost/smartslope/`. Register a resident, sign in, click a map point inside Irisan, use Refresh, submit a report, then check the admin list, risk-only editing, archival, and CSV export. New provider readings need an internet connection; Leaflet, map tiles, Bootstrap and jQuery load locally. Optional Nominatim lookup is disabled by default; explicitly enabling it sends selected coordinates through the server-side cached address endpoint. If lookup is unavailable, readings retain their coordinates and reports can use the house/landmark field.

If you have an *older* three-table schema without these existing columns, it is not the current `s1` schema. Restore a backup and compare it with `database/schema.sql` before using the application. A nine-table `v4` database is a different schema and is not automatically converted.

## Weather and risk

Open-Meteo hourly precipitation feeds 1/24/72-hour totals. Complete windows are required for a new category; a missing window never becomes `low`. `normal`: 10/25/50 mm; `medium`: 25/50/100 mm; `high`: 50/100/150 mm (1/24/72 h); otherwise `low`. These are explicitly prototype thresholds, not official alert criteria. Soil moisture and next-day rain are model context, not sensor readings. The MySQL observation time is UTC and displayed as Philippine time. A failed or stale provider response does not erase previously stored readings. Every successful click/refresh adds a reading, even during the same provider hour.

## Security and verification

Registration validates inputs; passwords use `password_hash()` and `password_verify()`. Login regenerates the session ID and roles come from the server. All changes use POST with a small session-bound CSRF token; read-only exports and JSON GET are GET. PDO binds user inputs and map/report text is escaped before HTML insertion. The Apache `.htaccess` blocks secret files and internal directories. Herd uses Nginx and does not read `.htaccess`; follow `docs/backend-test-checklist.md` for equivalent rules and verify them locally. Do not commit `.env`.

Check PHP syntax with `php -l` on modified PHP files, run `php tests/risk.php`, `php tests/weather.php`, `php tests/assessment.php` and `node --test tests/map-boundary.test.cjs`, and test the real MySQL/HTTP flows under Herd and XAMPP. Try login/registration, a resident requesting admin actions, a forged POST without CSRF, two Irisan points, repeat refresh, provider failure, report review, reading correction/archival and CSV. `node --check assets/js/dashboard.js` checks JavaScript syntax. The project's Startup business-model deck and team presentation are separate deliverables.

WEBSYS1: PHP/MySQL CRUD, PDO, one OOP risk class, jQuery AJAX, JSON API and web security. IMDBSE2: linked frontend and database CRUD. **Teacher-specific sensor-table note:** this deliberately retains three tables and records the virtual weather provider in `events.source`; if your teacher explicitly grades a separate `sensors` table, a no-schema-change rule cannot satisfy that particular table requirement.

## Backend stabilization release

Patch base: branch `s4`, commit `f8e7c5002814afe8d8b416f8a55aafe8e2c9733d`.

No table/column changes or SQL import are required. Preserve your existing `.env` and database. `READING_MAX_AGE_SECONDS` is optional and defaults to 10800. Every PHP entry point loads the shared bootstrap directly or through its page. Existing browser routes remain stable.

Read `docs/risk-rules.md` for rainfall intervals, data status, adjustment detection and source limitations. Read `docs/backend-test-checklist.md` before the frontend handoff. `docs/verification.md` distinguishes completed automated checks from local browser/server checks still required.

The readings JSON endpoint adds a top-level `assessment` and an `assessment` on each reading while retaining existing weather fields. GET reads only; POST requires a session-bound CSRF token and saves a new validated snapshot. Status codes are 401 (signed out), 403 (invalid CSRF), 422 (invalid ID), 404 (missing/inactive location), 405 (method), and 503 (database/provider failure). Observation and retrieval times are UTC strings; PHP and JavaScript display Philippine time.

Freshness is calculated on each request from observation time. An open page updates when reloaded/refreshed. Outdated history remains visible, with neutral map markers. Missing/invalid data never silently becomes low. Administrator adjustments are identified by comparison with the calculated baseline; new edits retain an audit trail; legacy edits cannot be reconstructed.

Keep this prototype scoped to Irisan and API-based rainfall alerts/community reporting. No new physical sensors, AI or unverified susceptibility dataset is included. The existing teacher-specific sensors-table requirement needs a separate scope agreement if still mandatory.


## Awareness upgrade (su1)

This upgrade adds nullable fields to the existing events table. **Existing databases
must run `php database/migrate-awareness.php` before updated pages are served.**
The migration is restart-safe; back up first. See docs/awareness-upgrade.md.
New installations may import database/schema.sql. No sensors or AI are included.
Alerts remain computed notices on refresh; no continuous monitoring is claimed.
Read docs/awareness-upgrade.md for migration, source review, and local acceptance checks.
