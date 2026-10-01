# SmartSlope v4 audit and simplification plan

Reviewed base: `f6d74cffbc3a0fb0bf71673fb41691bf1c6a80bc` (v4).
Reference: Umbalin `8ee5b5d958267c44d3dbf792924fd7597414671b`.
Reviewed available project conversation context, supplied WEBSYS1 syllabus pages 5–7, IMDBSE2 final-project instructions, and the supplied Startup guidelines. This is not a claim to have every historical message or a complete teacher scoring rubric.

## Decision

Keep the current plain PHP/PDO/MySQL structure. Simplify duplicated operations and obsolete files before changing tables. The application has a reasonable academic architecture; a full rewrite would add migration risk. It is a rainfall-based academic prototype, not a validated landslide warning service.

The audit found working source paths for authentication, server-assigned roles, CSRF, report contacts, map selection, provider ingestion, saved readings, admin review, CSV and risk colors. Sixteen mocked JavaScript tests pass. PHP/MySQL and a real browser were not executed in this workspace, so end-to-end correctness is not certified. In particular, the public repository does not establish which migrations have run in the local database.

## Requested change implemented

The saved-reading editor now exposes only Risk at fetch. The handler and repository update only `weather_fetches.risk_level`; an extra posted target location cannot move the record. Location, source and weather measurements remain unchanged. Delete and CSV remain available. This edits the stored review label, not the separately calculated live risk/marker. Existing location overrides are retained for historical consistency. No new SQL migration is needed for this change.

Modified implementation files: `admin/save_reading.php`, `app/ReadingRepository.php`, `assets/js/app.js`, `resident/weather_readings.php`. Updated tests: `tests/resident-weather.test.cjs`, `tests/mysql_integration.php`. Updated documentation: `README.md`, `docs/REQUIREMENTS.md`, this audit.

## Findings that should be addressed next

| Priority | Finding | Exact code | Recommendation |
|---|---|---|---|
| High | Freshness of the current observation is used for the rainfall result even if the latest hourly observation is older. The stored-window calculation also anchors on its newest returned row instead of verifying the requested final hour. | `api/weather.php`; `app/ReadingRepository.php::rainfallFromStoredHours()` | Check the final hourly time and continuity against the requested endpoint; do not label an old rainfall window fresh because current temperature is fresh. Add a provider fixture with fresh current data but old hourly data. |
| Medium | Earlier alerts can remain active when a later reading at the same location is low. The dashboard queries by the newest reading, so this is primarily a stored alert-status inconsistency. | `app/AlertRepository.php::synchronize()` | Resolve older active alerts for that location before opening the newest alert; document whether active means current or historical. |
| Medium | Selected-location display defaults to 30 logs; its CSV exports all logs. | `app/ReadingRepository.php::currentForLocation()`, `admin/download_readings.php` | Use the same filter and limit, or label the export explicitly as all records for the location. Add pagination when the log grows. |
| Medium | All-location loading has no limit and each click creates a fetch snapshot; data and DOM size grow indefinitely. | `api/saved_readings.php`, `assets/js/app.js` | Preserve the required snapshot per successful refresh, but add simple server pagination and an explicit all-record CSV export. Do not silently delete history. |
| Medium | Several documents describe removed features: public overview, no map dependency, and edits to rainfall measurements. | `PROJECT.md`, `docs/REFACTOR.md`, historical update notes | Make README plus one current design/requirements document authoritative; mark old notes historical. |

## Requirements to preserve

| Source | Evidence and constraint |
|---|---|
| WEBSYS1 syllabus | PHP/MySQL CRUD, OOP, jQuery/AJAX, API/JSON and security are explicit course coverage. Keep PDO repositories, RiskAnalyzer, an actual jQuery AJAX call and the JSON endpoint. The provided pages are not a complete scoring sheet. |
| IMDBSE2 instructions | Functional frontend and database CRUD, progress/contribution, presentation and final output. Fewer tables are not inherently better; relationships and integrity need to be explainable. |
| Teacher source-table feedback in the project history | Keep `sensors` as a clearly labeled virtual API source registry. Do not claim physical sensors are installed. |
| Startup guidelines | Concept note, deck, problem/SDG, market, value proposition, business model, operations, financial needs, logo and genuine progress evidence. Payment functionality is not required to explain the business model. |
| Latest user scope | One barangay, offline Leaflet map, provider refresh with persisted logs, risk colors, required report phone/optional email, admin report review/delete/CSV, saved-reading risk edit/delete/CSV, Herd/XAMPP paths. |

## How to use Umbalin as a guide

Use its clear dashboard-to-action flow and recognizable page names. Do not duplicate its implementation patterns blindly. Its `index.php` is roughly 1,030 lines and it duplicates substantial admin/resident JavaScript. `config.php` opens MySQLi and provides a separate PDO connection. Examined mutation routes such as `api/delete_disaster.php`, `api/update_disaster.php` and `api/purge_out_of_bounds.php` have no authentication or CSRF checks in those handlers or their included config. SmartSlope's shared guards and one PDO connection are stronger foundations.

## Safe cleanup plan (recommendations; not deleted by this patch)

| Action | Files | Reason |
|---|---|---|
| DELETE after reference check | `assets/js/app .js` | Obsolete script with an embedded space; active pages load `assets/js/app.js`. |
| DELETE after preserving any desired settings | `env.example`, `gitignore` | Misleading alternatives to the real `.env.example` and `.gitignore`; they are not byte-identical, so review differences first. |
| DELETE duplicate | root `db_test_seed.sql` | Byte-identical to `sql/db_test_seed.sql`; keep the SQL-directory copy. |
| REMOVE from application distribution | root `smartslope-v2-map-click.patch`, `smartslope-v2-click-readings-final.patch`, `smartslope-v3-login-only.patch`, `smartslope-v4-reconciliation.patch`, `smartslope-v4-report-contacts-map-colors.patch` | Old delivery files invite accidental reapplication. Preserve releases in Git history/download storage. |
| ARCHIVE documentation | old refactor/update instructions | Keep one current setup path; do not ask users to replay every old patch. |
| KEEP until database upgrades are confirmed | `sql/*.sql` migrations | Existing installations may still need these. Replace with numbered, documented migrations later. |
| KEEP | bootstrap, helpers, PDO, repositories, RiskAnalyzer, provider client, role guards, CSRF, escaping, local Leaflet/jQuery, map tiles | These serve active functions or explicit coursework. |
| REVIEW before removal | `admin/locations.php` | Hidden from navigation but still reachable; it is the remaining named-location/source maintenance UI. Remove only after deciding where those editing functions should live and checking the CRUD demonstration. |

## Simplify code first

1. Remove unused response data (`risk_readings` and raw hourly/current details no longer consumed by active `app.js`) and the corresponding duplicate SELECTs after confirming no external consumers.
2. Move shared dashboard-response assembly into one small service or helper used by `api/weather.php` and `api/location_dashboard.php`. Keep their GET-versus-POST roles clear.
3. Remove unused ReadingRepository methods after call-site review: old `adminList()`, rainfall `update()` and `setArchived()` are not used by current application routes. Keep source-history behavior needed by existing rows.
4. Keep reusable resident/admin partials, but eventually place shared reading/risk views in `components/` instead of making admin depend on a directory named resident.
5. Keep the public `api/readings.php` JSON contract for coursework even though it is not the dashboard endpoint.

## Database recommendation

Keep the nine tables for the next working release: users, barangays, locations, sensors, weather_observations, weather_fetches, readings, alerts and reports. Their current purposes differ: hourly facts, click/refresh snapshots, calculated summaries, and linked alerts must not be merged casually.

The largest safe reduction is data width and duplicated current observations, not table count. A later migration can make `weather_observations` an hourly-rainfall store containing source, observation time, fetch time and precipitation. Current dashboard measurements already live in `weather_fetches`. This requires adjusting ingestion, retrieval and tests together. Fields such as apparent temperature, pressure and wind direction are currently requested/stored but are not displayed by the active dashboard.

Do not drop `sensors` or `alerts` simply to resemble Umbalin. Do not drop historical `weather_fetches.location_id` overrides while old rows use them. Do not merge users and per-report contacts: the latter preserves the contact supplied for a particular report. A future eight-table design is possible by merging computed summaries with fetch snapshots, but it requires changing alert foreign keys and distinct observation/fetch semantics; it is not the simplest next step.

## Local acceptance gate

Before calling the project ready, test a disposable MySQL database imported from current db.sql and a migrated copy of existing data. Run PHP lint, PHP repository tests (PDO SQLite required), MySQL integration tests, and the JavaScript tests. Demonstrate account registration/login and denial of resident access to admin mutations; missing-phone rejection and optional-email reports; map click/refresh persistence and PHT display; risk-only edits that cannot change location; report review/delete/CSV; provider failure with saved data still visible; and the same routes under Herd and an XAMPP subfolder. Verify offline map tiles in a real browser. These remaining runtime checks are necessary; passing mocked JavaScript tests alone is not proof of a perfect website.
