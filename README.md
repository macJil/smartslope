# SmartSlope | Irisan prototype

**MAMP macOS crash workaround:** keep MySQL running, then run
`bash scripts/start-local.sh` from this folder and open http://127.0.0.1:8000/.
This uses MAMP's PHP directly and avoids Apache/FastCGI. It includes a small
router to keep internal files private without .htaccess. See
[the local launch guide](docs/mamp-fastcgi-crash.md) before using the old Apache URL.

This `sf2` version uses root-level `config.php`, small `functions.php`, focused `includes/` modules and page files, with direct `__DIR__` includes and ordinary relative browser links. Read the [team development guide](TEAM_DEVELOPMENT_GUIDE.md) for the structure and login troubleshooting. The earlier [behavior differences](docs/simplification-sf2.md), including editable CSVs and partial saves, still apply.
SmartSlope is a student prototype for rainfall screening and community ground-condition reporting in Barangay Irisan, Baguio City. It uses PHP and PDO/MySQL, locally bundled Bootstrap/Leaflet/jQuery, an offline Irisan map, and Open-Meteo weather data.

The website supports resident accounts, map-point selection, manual weather refresh and saved reading history, explainable rainfall categories, resident reports, administrator review, risk-category correction with a reason, reading archival and CSV workflows.

**This branch is a prototype.** Its rainfall thresholds are not calibrated against local landslide events and are not official warnings or validated predictions. No physical sensors, verified Irisan susceptibility subset, or AI/ML model is included. The current database has three tables: `users`, `locations`, and `events`. Read the documentation before describing scope or limitations in a defense.

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

## Run locally
1. Set up PHP 8.1+, PDO MySQL, cURL, MySQL and a web server.
2. Edit DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASS in `config.php`. No `.env` file is used.
3. For a **new** database, import `database/schema.sql`. For an existing current three-table database, back it up and run `php database/migrate-awareness.php`; do not reimport the schema over existing data.
4. Rotate the seeded admin password with `php scripts/create_admin.php admin your-email@example.com +639123456780`. The script prompts for a password.
5. Keep MySQL running, run `bash scripts/start-local.sh`, then open `http://127.0.0.1:8000/`. Weather refresh needs internet access. Local map assets remain on disk.
6. Run the checks in [testing](docs/testing.md). Apache/Nginx hosting requires separate server-level access rules; the included router is for the local PHP server.

## Risk screen
The analyzer uses complete 1h/24h/72h rainfall totals and the highest reached prototype threshold. `low` does not mean safe. Forecast rainfall is separate from historical totals. Old, incomplete and invalid readings are not shown as current. Details: [analysis](docs/analysis.md).

## Academic fit and open scope
The PHP/MySQL, PDO CRUD, OOP, AJAX/JSON and security features map to the supplied WEBSYS1 syllabus and the IMDBSE2 web application/database CRUD project brief. The separate sensor-table requirement must be confirmed with the instructor; this branch intentionally uses three tables and has no `sensors` table. The Startup deck's proposed business model is not implemented or validated.

Documentation snapshot: branch `f1`, commit `2bc89e742301721c9815b8df59c22b908a0f6db3`, checked 2026-10-03.
