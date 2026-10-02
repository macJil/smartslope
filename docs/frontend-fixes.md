# s4 frontend corrections

Base: `b1fdc7e` (branch `s4`). Existing PHP, local Bootstrap, Leaflet, jQuery, Irisan tiles, database schema and authenticated routes are retained.

## Changes

- Shared escaped presentation in `app/presentation.php` renders assessment cards and compact reading tables for dashboard, readings, admin, and AJAX responses.
- Only a current assessment receives a category color or contributes to current high/medium reading counts. Outdated readings show a neutral unavailable badge and a last-saved category. Incomplete readings remain unavailable.
- The assessment card includes data status, reasons, timestamps, source, separate baseline susceptibility, forecast context and the prototype limitation.
- Counts state the displayed row limit (10/20/50), configured freshness window and that they count readings rather than distinct locations.
- Weather refresh updates counts, table, panel, and marker popup; errors use a persistent inline status while preserving displayed readings. The endpoint adds a `view` object and keeps its existing JSON fields.
- Tables retain rainfall history and status; native `details` disclosures contain secondary weather values and reasons. No extra table library is used.
- The required report location select accepts an existing location or a selected arbitrary Irisan map point. Adding `required` alone would have blocked new map points, so a dedicated selected-map-point option is provided. Server-side polygon validation remains authoritative.
- Report labels, modal labels, table keyboard scrolling, visible map legend swatches and narrow-screen wrapping are improved.

## Apply

Save the patch outside the repository, then run from the repository root:

```sh
git switch s4
git status --short
git apply --check ~/Downloads/smartslope-s4-frontend-fixes.patch
git apply ~/Downloads/smartslope-s4-frontend-fixes.patch
```

The check must succeed before applying. If it fails, keep local changes and compare the branch to the documented base; do not force or reset the working tree. Refresh the browser after applying. No database migration is needed.

## Verification

PHP 8.3 checks run during implementation:

```sh
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
php tests/risk.php
php tests/weather.php
php tests/assessment.php
php tests/presentation.php
node --check assets/js/dashboard.js
node tests/map-boundary.test.cjs
```

Results: PHP lint passed; 25 risk checks, 11 assessment checks, weather-window checks, 23 presentation checks and 2 map-boundary checks passed.

Browser checks used Chromium, PHP and a disposable MariaDB database with synthetic resident/admin accounts and current/outdated/incomplete records. Verified authenticated page loads; neutral stale/missing assessments; freshness-aware counts; escaped PHP-rendered fragments; page/AJAX content parity; AJAX error preservation; bulk selection after refresh; admin-only editing controls; expandable details; no document overflow at 390px; required empty selection; existing location selection; and successful submission of a new map-point report. Desktop and mobile screenshots were inspected.

Weather refresh success/failure responses were simulated using actual authenticated GET response data and a controlled provider-error response. These checks do not establish live-provider availability or landslide predictive accuracy. Verify a real weather refresh and your own Herd/XAMPP deployment after applying.

The patch is applied and checked against a separate clean checkout of its base before delivery. No test users, database fixtures, credentials, or browser dependencies are included in the patch.
