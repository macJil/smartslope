# SmartSlope | Irisan prototype
SmartSlope is a student prototype for rainfall screening and community ground-condition reporting in Barangay Irisan, Baguio City. It uses PHP and PDO/MySQL, locally bundled Bootstrap/Leaflet/jQuery, an offline Irisan map, and Open-Meteo weather data.

<<<<<<< HEAD
The website supports resident accounts, map-point selection, manual weather refresh and saved reading history, explainable rainfall categories, resident reports, administrator review, risk-category correction with a reason, reading archival and CSV workflows.
=======
PHP 8.1+ / PDO MySQL, local Bootstrap and Leaflet, jQuery AJAX and an authenticated JSON reading endpoint. The schema uses `users`, `locations`, shared `events`, and one-to-one `readings` and `reports` detail tables. Events own the record ID, location, submitter and creation time; subtype-specific measurements, contact data and review state live in their detail table. No physical sensors or official landslide warnings are claimed.
>>>>>>> 095cb2e (working)

**This branch is a prototype.** Its rainfall thresholds are not calibrated against local landslide events and are not official warnings or validated predictions. No physical sensors, verified Irisan susceptibility subset, or AI/ML model is included. The current database has three tables: `users`, `locations`, and `events`. Read the documentation before describing scope or limitations in a defense.

<<<<<<< HEAD
## Documentation
- [Project overview](PROJECT.md)
- [Installation guide](docs/installation.md)
- [Resident and administrator manual](docs/user-manual.md)
- [Database and ERD](docs/database.md)
- [Architecture](docs/architecture.md)
- [JSON API](docs/api.md)
- [Data sources and provenance](docs/data-sources.md)
- [Risk analysis rules](docs/analysis.md)
- [Security](docs/security.md)
- [Testing and verification](docs/testing.md)
- [Startup concept and proposed business model](docs/startup.md)
- [Presentation and demo guide](docs/presentation-and-demo.md)
- [Defense questions](docs/defense-questions.md)
- Existing implementation notes: [risk rules](docs/risk-rules.md), [awareness migration](docs/awareness-upgrade.md), [acceptance checklist](docs/backend-test-checklist.md), [verification history](docs/verification.md)
=======
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
| `database/schema.sql` | Normalized schema for new databases; **do not import into an existing DB** |
| `assets/js/dashboard.js`, `assets/js/irisan-boundary.js`, `assets/js/vendor/` | AJAX, polygon validation and local jQuery |
| `assets/vendor/leaflet/`, `assets/map/`, `assets/map-tiles/` | Offline map library, Irisan polygon and local map tiles |
| `scripts/create_admin.php`, `tests/risk.php` | Local admin password rotation and risk boundary check |
>>>>>>> 095cb2e (working)

## Run locally
1. Set up PHP 8.1+, PDO MySQL, cURL, MySQL and a web server.
2. Copy `.env.example` to `.env` and set database credentials.
3. For a **new** database, import `database/schema.sql`. For an existing current three-table database, back it up and run `php database/migrate-awareness.php`; do not reimport the schema over existing data.
4. Rotate the seeded admin password with `php scripts/create_admin.php admin your-email@example.com +639123456780`. The script prompts for a password.
5. Open the Herd site root or `http://localhost/smartslope/` on XAMPP. Weather refresh needs internet access. Local map assets remain on disk.
6. Run the checks in [testing](docs/testing.md). Nginx requires server-level rules because it does not read `.htaccess`.

## Risk screen
The analyzer uses complete 1h/24h/72h rainfall totals and the highest reached prototype threshold. `low` does not mean safe. Forecast rainfall is separate from historical totals. Old, incomplete and invalid readings are not shown as current. Details: [analysis](docs/analysis.md).

<<<<<<< HEAD
## Academic fit and open scope
The PHP/MySQL, PDO CRUD, OOP, AJAX/JSON and security features map to the supplied WEBSYS1 syllabus and the IMDBSE2 web application/database CRUD project brief. The separate sensor-table requirement must be confirmed with the instructor; this branch intentionally uses three tables and has no `sensors` table. The Startup deck's proposed business model is not implemented or validated.

Documentation snapshot: branch `f1`, commit `2bc89e742301721c9815b8df59c22b908a0f6db3`, checked 2026-10-03.
=======
1. Back up the existing MySQL database. After configuring .env, run `php database/migrate-awareness.php` before serving updated pages. The restart-safe CLI migration creates `readings` and `reports`, copies subtype data from legacy `events`, verifies the copies, then removes the old subtype columns. It retains event IDs and shared data. MySQL DDL is not fully transactional; do **not** import `database/schema.sql` over an existing database.
2. Copy `.env.example` to `.env`. Set your real MySQL host/port/login and `DB_DATABASE=smartslope_mvp`. With Herd + DBngin, use the DBngin credentials; with XAMPP, use its MySQL credentials. Leave `APP_BASE_PATH=` empty to detect the website path automatically.
3. If the DB still has the seeded `admin` account with its published `admin123` password, rotate it locally: `php scripts/create_admin.php admin admin@example.test +639123456780`. Supply your own unique email/phone and a password of at least 12 characters. The command creates an admin only when that username does not already exist.
4. Open the Herd site root or `http://localhost/smartslope/`. Register a resident, sign in, click a map point inside Irisan, use Refresh, submit a report, then check the admin list, risk-only editing, archival, and CSV export. New provider readings need an internet connection; Leaflet, map tiles, Bootstrap and jQuery load locally. Optional Nominatim lookup is disabled by default; explicitly enabling it sends selected coordinates through the server-side cached address endpoint. If lookup is unavailable, readings retain their coordinates and reports can use the house/landmark field.

The migration targets the legacy `users`/`locations`/wide-`events` schema. A nine-table `v4` database is a different schema and is not automatically converted.

## Weather and risk

Open-Meteo hourly precipitation feeds 1/24/72-hour totals. Complete windows are required for a new category; a missing window never becomes `low`. `normal`: 10/25/50 mm; `medium`: 25/50/100 mm; `high`: 50/100/150 mm (1/24/72 h); otherwise `low`. These are explicitly prototype thresholds, not official alert criteria. Soil moisture and next-day rain are model context, not sensor readings. The MySQL observation time is UTC and displayed as Philippine time. A failed or stale provider response does not erase previously stored readings. Every successful click/refresh adds a reading, even during the same provider hour.

## Security and verification

Registration validates inputs; passwords use `password_hash()` and `password_verify()`. Login regenerates the session ID and roles come from the server. All changes use POST with a small session-bound CSRF token; read-only exports and JSON GET are GET. PDO binds user inputs and map/report text is escaped before HTML insertion. The Apache `.htaccess` blocks secret files and internal directories. Herd uses Nginx and does not read `.htaccess`; follow `docs/backend-test-checklist.md` for equivalent rules and verify them locally. Do not commit `.env`.

Check PHP syntax with `php -l` on modified PHP files, run `php tests/risk.php`, `php tests/weather.php`, `php tests/assessment.php` and `node --test tests/map-boundary.test.cjs`, and test the real MySQL/HTTP flows under Herd and XAMPP. Try login/registration, a resident requesting admin actions, a forged POST without CSRF, two Irisan points, repeat refresh, provider failure, report review, reading correction/archival and CSV. `node --check assets/js/dashboard.js` checks JavaScript syntax. The project's Startup business-model deck and team presentation are separate deliverables.

WEBSYS1: PHP/MySQL CRUD, PDO, one OOP risk class, jQuery AJAX, JSON API and web security. IMDBSE2: linked frontend and database CRUD. **Teacher-specific sensor-table note:** the weather provider is recorded in `readings.source`; if your teacher explicitly grades a separate `sensors` table, that remains a separate requirement.

## Backend stabilization release

Patch base: branch `s4`, commit `f8e7c5002814afe8d8b416f8a55aafe8e2c9733d`.

Preserve your existing `.env`. The event-detail schema migration is required before serving this version; see “Run with your current database” above. `READING_MAX_AGE_SECONDS` is optional and defaults to 10800. Every PHP entry point loads the shared bootstrap directly or through its page. Existing browser routes remain stable.

Read `docs/risk-rules.md` for rainfall intervals, data status, adjustment detection and source limitations. Read `docs/backend-test-checklist.md` before the frontend handoff. `docs/verification.md` distinguishes completed automated checks from local browser/server checks still required.

The readings JSON endpoint adds a top-level `assessment` and an `assessment` on each reading while retaining existing weather fields. GET reads only; POST requires a session-bound CSRF token and saves a new validated snapshot. Status codes are 401 (signed out), 403 (invalid CSRF), 422 (invalid ID), 404 (missing/inactive location), 405 (method), and 503 (database/provider failure). Observation and retrieval times are UTC strings; PHP and JavaScript display Philippine time.

Freshness is calculated on each request from observation time. An open page updates when reloaded/refreshed. Outdated history remains visible, with neutral map markers. Missing/invalid data never silently becomes low. Administrator adjustments are identified by comparison with the calculated baseline; new edits retain an audit trail; legacy edits cannot be reconstructed.

Keep this prototype scoped to Irisan and API-based rainfall alerts/community reporting. No new physical sensors, AI or unverified susceptibility dataset is included. The existing teacher-specific sensors-table requirement needs a separate scope agreement if still mandatory.


## Awareness upgrade (su1)

The schema migration creates one-to-one reading and report detail tables and
backfills existing records. **Existing databases must run
`php database/migrate-awareness.php` before updated pages are served.** Back up
first. See docs/awareness-upgrade.md. New installations may import database/schema.sql.
No sensors or AI are included.
Alerts remain computed notices on refresh; no continuous monitoring is claimed.
Read docs/awareness-upgrade.md for migration, source review, and local acceptance checks.
>>>>>>> 095cb2e (working)
