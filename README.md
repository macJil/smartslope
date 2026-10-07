# SmartSlope

SmartSlope is a PHP/MySQL academic prototype for rainfall screening and resident ground-condition reports in Barangay Irisan, Baguio City. It shows saved weather context on a local map and lets an administrator review reports. Its categories are demonstration rules, **not official landslide alerts or validated predictions**. A `low` category does not establish safety.

## Technology and design

| Part | Used in this repository |
| --- | --- |
| Server | PHP 8.1+, PDO with MySQL, cURL, sessions and server-rendered pages |
| Browser | HTML, CSS, Bootstrap 5, local Leaflet and map tiles, GeoJSON, jQuery AJAX and JavaScript |
| Data | Five InnoDB tables: `users`, `locations`, `events`, `readings`, `reports` |
| Interface | Responsive Bootstrap layout with a green and brown theme in `assets/css/frontend.css` |

No JavaScript framework or build process is required. PHP routes live in the project root and `pages/`; `actions/` handles forms, `api/` handles JSON, and `app/` holds authentication, database access, weather processing and analysis.

## Code organization

- `app/bootstrap.php` loads the shared application code.
- `app/repositories.php` loads four function-based modules in `app/repositories/`: users, locations, readings and reports. Existing function names and SQL are retained.
- `app/helpers.php` provides form, URL, escaping and date helpers; `app/geography.php` checks the Irisan boundary.
- `app/http.php` provides the shared JSON response function.
- `app/maintenance.php` loads `app/schema.php` (schema checks) and `app/migration.php` (legacy migration).
- `app/RiskAnalyzer.php` remains the rainfall screening class. Pages, routes, database structure and interface assets retain their existing roles.

## Main features

- Resident registration/login, an Irisan map, saved reading history and a manually triggered weather refresh.
- Resident reports with a location, ground-condition type, description and contact details.
- Administrator location management, report review, reading category correction with a reason, archival and CSV workflows.
- Authenticated `GET api/readings.php?location_id=ID` for saved readings and assessment; CSRF-protected `POST api/readings.php` to fetch Open-Meteo and save a new snapshot. GET does not contact the provider.
- Local Bootstrap, Leaflet, GeoJSON and 110 map tiles. Saved dashboard data and reports can work without internet while the local PHP server and MySQL are available. Weather refresh needs internet. Location labels show a saved address or landmark with coordinates; a user-selected point can request a mapped address from Nominatim when online.

## Install and run

1. Install PHP 8.1+ with `pdo_mysql` and cURL, MySQL and a local web server. Use Herd with DBngin on macOS or XAMPP with Apache/MySQL.
2. Copy `.env.example` to `.env`; set the database host, port, name, username and password. Keep `.env` private. `APP_BASE_PATH` can remain empty for automatic detection.
3. For a **new** database, import `database/schema.sql`. For an **existing legacy three-table database**, back it up, rehearse on a disposable copy, and run `php database/migrate-awareness.php` before serving this version. The migration creates `readings` and `reports`, copies legacy subtype values, checks matching rows and removes old subtype columns. It does not convert unrelated older schemas. Do not import the new schema over an existing database.
4. Rotate the administrator password seeded by the SQL file before use with real data. Generate a local PHP hash without putting the password in the command text:

   ```bash
   php -r 'fwrite(STDERR, "New admin password: "); $p=rtrim(fgets(STDIN), "\r\n"); if (strlen($p)<12 || strlen($p)>72) exit(1); echo password_hash($p, PASSWORD_DEFAULT), PHP_EOL;'
   ```

   Enter a private password of 12–72 bytes at the prompt. In your local MySQL tool, update the seeded administrator's `users.password` to the generated hash and set unique real `email` and `phone` values. Never store the plaintext password in SQL or Git. The input may be visible in the terminal, so use a private console. Public registration always creates a resident account.
5. Open the Herd site root or `http://localhost/smartslope/` on XAMPP. Register a resident, sign in, select a point inside Irisan, refresh, submit a test report, and review it as admin.

`READING_MAX_AGE_SECONDS` defaults to 10800 (three hours). Address lookup is enabled by default for user-selected points in new installations. **If your existing `.env` has `NOMINATIM_ENABLED=0`, change it to `1` to enable mapped addresses.** This requires internet and follows the [public Nominatim policy](https://operations.osmfoundation.org/policies/nominatim/): the application uses user-triggered, server-side lookup with a shared rate limit and cache. Set `NOMINATIM_ENABLED=0` for offline-only use. Select an existing map point again to fill its missing saved address; no bulk geocoding runs. An administrator can enter a known full address in Admin → All Locations, including a house number, street, subdivision or purok, which is then shown with its coordinates in the other views. Reverse lookup returns only mapped detail and may omit house numbers or fail. A resident's report landmark also helps identify a location. Local map images alone do not contain a searchable address database. Location CSV export may generate `CSV_SIGNING_KEY` in `.env`; keep and back up that file. The admin page can offer a protected database migration button when required detail tables are missing, but use the CLI for a larger database.

## Database relationship

`users` holds accounts and server-assigned roles. `locations` holds saved Irisan points. `events` holds the shared ID, location, submitter, event type and creation time. Each reading event has one `readings` detail row; each report event has one `reports` detail row, both linked through `event_id`. The report reviewer ID links to `users`. The SQL schema is the authoritative field and foreign-key definition. No `sensors` or `alerts` table exists; confirm any separate teacher sensor-table requirement with the instructor.

## Rainfall assessment and data sources

PHP requests Open-Meteo model weather for the selected coordinates. Complete hourly precipitation supplies preceding 1-hour, 24-hour and 72-hour totals; a 24-hour forecast is shown separately. `app/RiskAnalyzer.php` selects the strongest reached threshold:

| Category | 1 hour | 24 hours | 72 hours |
| --- | ---: | ---: | ---: |
| Normal | 10 mm | 25 mm | 50 mm |
| Medium | 25 mm | 50 mm | 100 mm |
| High | 50 mm | 100 mm | 150 mm |

Values below all thresholds are `low` only when all three historical windows are complete and valid. Missing, inconsistent, future or old observations cannot support a current category. The freshness policy defaults to three hours. A failed refresh leaves saved history intact. Provider values are model estimates, not physical sensor readings at a home. Soil moisture and forecast are context; susceptibility and resident reports are separate from the rainfall score.

The repository includes the Irisan GeoJSON boundary and local tiles. Their original provenance and redistribution rights still require review before public deployment. No verified Irisan hazard polygon subset or historical landslide validation catalog is bundled. Open-Meteo terms: https://open-meteo.com/en/terms. Optional Nominatim policy: https://operations.osmfoundation.org/policies/nominatim/. Keep attribution visible and review current provider terms before commercial use. Bundled Leaflet and jQuery license files remain with the assets.

## Security, checks and limits

Passwords use PHP hashing, login regenerates the session ID, roles are checked on the server, changing requests require POST and CSRF tokens, PDO uses prepared statements, and user content is escaped for HTML. `.htaccess` restricts internal paths on Apache when overrides are enabled. Herd/Nginx ignores `.htaccess`; configure equivalent denials for `.env`, `app/`, `data/`, `database/` and other internal files before exposing the site. Use HTTPS for network access and protect report contact data and CSV exports.

The repository includes deterministic PHP and JavaScript tests under `tests/`; see [tests/README.md](tests/README.md) for commands. Database tests refuse to run unless the effective database name ends in `_test`. `tests/migration.php` checks an already-normalized schema and reruns the idempotent migration; it does **not** replace a legacy-data migration rehearsal. Before submission or defense, rehearse `database/migrate-awareness.php` on a disposable copy of the legacy database, compare event/detail counts and representative values, then test login, report submission/review, address lookup and offline fallback, refresh failure, reading correction, CSV and server access rules under the actual Herd/XAMPP setup. Keep a backup; never run migration or integration fixtures against real data.

For the four-student weekly ownership, commit conventions and final acceptance plan, see [TEAM_DEVELOPMENT_GUIDE.md](TEAM_DEVELOPMENT_GUIDE.md). Test commands and disposable-database rules are in [tests/README.md](tests/README.md).

SmartSlope has no physical sensors, continuous monitoring, AI/ML prediction, official warning integration, calibrated accuracy, field adoption or revenue claim. Follow official advisories and local authorities for real safety decisions.
