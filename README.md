# SmartSlope | Irisan prototype

SmartSlope is a student prototype for rainfall screening and community ground-condition reporting in Barangay Irisan, Baguio City. It uses PHP 8.1+, PDO/MySQL, locally bundled Bootstrap, Leaflet and jQuery, an offline Irisan map, and Open-Meteo model weather data.

Residents can register, select an Irisan point, refresh and inspect saved weather readings, and submit ground reports. Administrators can manage locations, review reports, correct a reading category with a reason, archive readings, and use CSV workflows. Rainfall categories are uncalibrated prototype rules, not official landslide warnings or validated predictions. `low` does not mean safe. There are no physical sensors, AI/ML model, verified Irisan hazard subset, or continuous background monitoring.

## Documentation

- [Project overview](PROJECT.md)
- [Installation and migration](docs/installation.md)
- [Resident and administrator manual](docs/user-manual.md)
- [Database, data dictionary and ERD](docs/database.md)
- [Architecture](docs/architecture.md)
- [JSON API](docs/api.md)
- [Data sources and provenance](docs/data-sources.md)
- [Risk analysis](docs/analysis.md)
- [Security](docs/security.md)
- [Testing and verification](docs/testing.md)
- [Startup concept](docs/startup.md)
- [Presentation and demo](docs/presentation-and-demo.md)
- [Defense questions](docs/defense-questions.md)

Implementation history: [risk rules](docs/risk-rules.md), [event-detail migration](docs/awareness-upgrade.md), [acceptance checklist](docs/backend-test-checklist.md), and [verification record](docs/verification.md).

## Current database

`database/schema.sql` defines five tables: `users`, `locations`, `events`, `readings`, and `reports`. An `events` row holds the shared ID, location, submitter, type, and creation time. A reading or report has one detail row in its corresponding table, linked by `event_id`. The weather provider is recorded in `readings.source`; there is no `sensors` table. Confirm any separate teacher requirement for a sensor table with the instructor.

## Run locally

1. Install PHP 8.1+ with PDO MySQL and cURL, MySQL, and a web server. Node.js is needed for the map test.
2. Copy `.env.example` to `.env` and configure the actual MySQL host, port, credentials, and database name.
3. For a new database, import `database/schema.sql`. For an existing legacy three-table database, back it up and run `php database/migrate-awareness.php` **before serving the updated application**. This migration creates reading/report detail tables, copies legacy fields, verifies parent/detail rows, then drops the old subtype columns. MySQL DDL is not fully transactional. Do not reimport the schema over existing data.
4. Rotate the seeded administrator password with `php scripts/create_admin.php admin your-email@example.com +639123456780`; enter a unique email/phone and a password of at least 12 characters at the prompt.
5. Open the Herd site root or `http://localhost/smartslope/` on XAMPP. Refresh needs internet access; bundled map assets work locally.
6. Run the [tests](docs/testing.md) and perform HTTP checks on the actual host. Herd/Nginx ignores `.htaccess`, so configure equivalent access restrictions for internal paths.

The admin screen also exposes a CSRF-protected **Migrate database** action when the detail schema is missing. Use a backup and prefer the CLI command for a larger existing database. The migration targets the earlier wide `events` schema, not an unrelated legacy schema.

## Weather and interpretation

Open-Meteo hourly precipitation feeds complete 1h, 24h and 72h totals. `RiskAnalyzer` selects the strongest reached prototype threshold. Forecast and modeled soil moisture are context, not physical sensor readings. Missing, inconsistent, stale or invalid data cannot claim a current rainfall category. GET on `api/readings.php` reads saved data; authenticated CSRF-protected POST fetches and saves one new snapshot. A failed refresh retains history.

The app uses PHP forms and sessions, PDO CRUD, an object-oriented risk class, jQuery AJAX, JSON, validation and security controls relevant to WEBSYS1 and IMDBSE2. The proposed Startup model has no measured demand, revenue or field validation. See the linked guides for precise behavior and limitations.

Documentation reviewed against branch `f1` application commit `ec7003130d1fd483dce3c167adbd07f662510407` (2026-10-03); re-run tests on the final checkout used for defense.
