# Reference review and applied refactor

Baseline: macJil/landslide `ver1`, commit `2a9a8b6`.
Reference: macJil/umbalin, commit `8ee5b5d958267c44d3dbf792924fd7597414671b`.
Local work branch: `refactor/simple-academic-mvp`. No remote push or deployment performed.

## Reference structure, architecture and state

Umbalin has top-level login/register/logout and admin/resident pages, shared config.php, small action-named api/ scripts, assets/js and assets/css, db.sql plus migrations. Its persistent records are users, disasters and user_reports. PHP sessions hold identity/role; JavaScript holds map markers, selected report and filters in memory. Forms call JSON endpoints, which validate inputs, query MySQL and return results; the UI refreshes lists and map layers. Resident reports enter a pending queue; administrator approval inserts a disaster and changes report status.

The useful blueprint is direct role pages → focused endpoint → database → JSON/UI, with predictable snake_case filenames and assets separated from server code. SmartSlope adopts that small-project structure while retaining a handful of OOP classes required by WEBSYS1.

The reference is not uniformly simpler or safe: it mixes PDO and MySQLi, repeats configuration, has large embedded styles/templates, inconsistent role/method checks, and destructive disaster endpoints without session authorization. Report approval performs multiple writes without a transaction. Its README still says authentication is absent despite login/roles in code. These patterns were not copied. Leaflet, geocoding, multi-disaster features and geographic coverage are outside SmartSlope's scope.

## Applied changes

- Role pages moved out of components into admin/ and resident/; both dashboards are index.php. locations.php and reading.php describe their create/edit roles. Internal redirects, links, includes and tests updated.
- Kept explicit PHP classes and one PDO connection. Configuration is read once per bootstrap; validated base path is reused for sessions and URLs.
- Both JSON endpoints use app/json.php rather than duplicate response functions.
- Removed the redundant configs/db.php wrapper and the broken sql_import.sql shell-command fragment.
- Removed unused current_weather.php, duplicate user-weather.js, the obsolete implementation patch, unused Bootstrap variants/maps and map images. Kept existing functional dashboard and provider fields.
- Supplied actual licensed jQuery 3.7.1; the repository's previous file was empty. Weather refresh now uses jQuery.ajax with timeout, cancellation and late-response protection.
- One reading form creates and edits sourced readings. Fixed missing source parameters in updates. Complete rainfall totals are required for manual entry; invalid inputs are retained on errors.
- Replaced the call to nonexistent ReadingRepository::delete with archive/restore. Location history is also archived/restored instead of deletion blocked by foreign keys. Existing hazard metadata is preserved during location edits.
- Consolidated CSV export into its existing dedicated endpoint and fixed the call to nonexistent currentApiReadings. Kept formula escaping and PHT display.
- Removed tracked local .env, supplied correct .env.example and ignore rules. Existing private local settings are not included in exports.
- Added setup documentation, minimal requirement traceability and reproducible verification commands.

## Database scope

CURRENT DESIGN: ver1's users/email, locations, readings, reports and weather_observations schema.
PROBLEM: UI/repository mismatches, duplicated assets/configuration and missing jQuery implementation.
PROPOSED DESIGN: reuse the current schema, repair and simplify its application workflows.
WHY: avoid introducing an unrelated schema redesign while moving and fixing code.
MIGRATION IMPACT: none from ver1. Earlier installations still need their already-existing schema repair scripts. Contact-number/alerts/schema sizing changes remain pending database work and must be synchronized with SQL, ERD and data dictionary together.

## Verification limits

Dashboard tests exercise provider success/failure, invalid selection, persistence warning, incomplete rainfall, refresh, timestamps and late responses. PHP repository tests use an isolated SQLite fixture for create/read/update/archive/restore, report review, authentication and threshold boundaries. PHP syntax checks use a portable PHP WebAssembly runtime because native PHP is unavailable here. These do not replace MySQL-specific upsert/schema tests or a full deployed browser acceptance run.

## Recorded checks

- 12/12 Node dashboard tests passed.
- 16/16 PHP repository/risk checks passed against an isolated SQLite fixture.
- All application PHP files passed PHP-WASM syntax checks.
- Actual jQuery 3.7.1 passed a jsdom XMLHttpRequest smoke test against a local HTTP server: POST, CSRF body, risk rendering and refresh. This is not a full browser test.
- Literal filesystem include and application URL target scan found no missing files.
- No MySQL server or native browser runtime was available; the end-to-end acceptance list remains open.
