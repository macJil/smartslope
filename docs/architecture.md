# Application architecture

SmartSlope uses a small PHP application with browser pages, shared application code, database repositories and two JSON endpoints. There is no JavaScript build pipeline.

## Main layers

| Layer | Repository paths | Responsibility |
| --- | --- | --- |
| Browser routes and templates | Root PHP pages, `partials/navbar.php`, `partials/edit-reading.php` | Render login, dashboard, report and admin views. |
| Actions and API | Root page POST handlers, `api/readings.php`, `api/address.php` | Check session/role, process forms and return JSON or redirect. Old `actions/` and `pages/` files only redirect for compatibility. |
| Shared application logic | Direct includes of root `config.php` and `functions.php` | Database settings, PDO connection, named helper functions, RiskAnalyzer and HTML rendering. Includes use `__DIR__`; browser links are relative, with no URL base detection. |
| Storage | `database/schema.sql`, `database/migrate-awareness.php`, MySQL | Persist users, locations and reading/report events. |
| Browser assets | `assets/js/`, `assets/css/`, `assets/vendor/`, `assets/map/`, `assets/map-tiles/` | Local Bootstrap, Leaflet, jQuery, map boundary, AJAX, styling and tiles. |

Each PHP entry point loads the explicit shared bootstrap using `__DIR__`. Configuration reads `.env`; PDO connects lazily with exceptions, utf8mb4, native prepared statements and UTC session time.

## Reading refresh flow

1. A signed-in user chooses an active point inside the Irisan polygon and presses Refresh.
2. `assets/js/dashboard.js` sends a session-authenticated POST to `api/readings.php`.
3. The endpoint checks the session, method, token and positive location ID.
4. `app/weather.php` validates the active point, calls Open-Meteo through the provider helper, validates units/timestamps/ranges, and totals complete 1/24/72 hour windows.
5. `RiskAnalyzer` assigns the highest reached prototype rainfall category; `refresh_location()` stores a new `events` snapshot with a prepared INSERT.
6. The endpoint recomputes freshness and explanatory assessment, then returns JSON and rendered fragments for the dashboard.

On provider failure the endpoint returns an error and keeps earlier snapshots. GET returns saved data only and does not refresh the provider.

## Report flow

The report page obtains a point from the Irisan map, validates the report fields server-side and inserts an `events` row with `type='report'` and `status='pending'`. Admin actions move the report through `pending`, `reviewed`, and `resolved`. Review fields capture the administrator and time. The report remains distinct from calculated weather risk.

## Map and location flow

Leaflet, tiles, the GeoJSON boundary and JavaScript boundary checker are served from the repository. The server also checks coordinates against the boundary before making/refreshing locations, so the browser check is not the only validation. Optional address lookup uses `api/address.php` and is disabled unless configured.

## Failure and status behavior

Login catches PDO failures and explains connection, credentials, missing database and schema problems inside the existing form. Other unhandled failures are logged; database failures get the same setup guidance without exposing SQL or passwords. The reading API returns structured JSON errors and HTTP status codes. Assessment logic treats old, incomplete, invalid or unknown-rule data as non-current; it does not turn missing data into a low category.
