# SmartSlope frontend corrections

Base: `43a2877` (branch `su1`, which already contains the earlier `s4` frontend corrections). Existing PHP, local Bootstrap, Leaflet, jQuery, Irisan tiles, database schema and authenticated routes are retained.

## Changes

- Shared escaped presentation in `app/presentation.php` renders assessment cards and compact reading tables for dashboard, readings, admin, and AJAX responses.
- Only a current assessment receives a category color or contributes to current high/medium reading counts. Outdated readings show a neutral unavailable badge and a last-saved category. Incomplete readings remain unavailable.
- The dashboard places reading summary below the map and the risk analyzer below the summary in one column. It shows the category, data status, available address, observed rainfall, timestamps and source.
- Assessment reasons, baseline susceptibility, forecast interpretation and the prototype limitation are documented in `docs/risk-rules.md` rather than repeated in the dashboard card.
- Summary category counts cover the latest 10 saved readings for a selected location, or the latest 20 across active locations when none is selected. Only observations within the configured freshness window (3 hours by default) count as current. Counts represent readings, not distinct locations. Admin counts use the latest 50 saved readings. Page/API requests recalculate freshness.
- Weather refresh updates counts, table, panel, and marker popup; errors use a persistent inline status while preserving displayed readings. The endpoint adds a `view` object and keeps its existing JSON fields.
- Tables retain rainfall history and status; a shared accessible Bootstrap dialog displays secondary weather values. It works for rows returned by AJAX. No extra table library is used.
- Stored landmarks appear as addresses when present. If only a generated coordinate name is available, the display shows Barangay Irisan, Baguio City, Benguet, Philippines and the coordinates; it does not invent a street or house number.
- The required report location select accepts an existing location or a selected arbitrary Irisan map point. Adding `required` alone would have blocked new map points, so a dedicated selected-map-point option is provided. Server-side polygon validation remains authoritative.
- Report labels, modal labels, table keyboard scrolling, visible map legend swatches and narrow-screen wrapping are improved.

## Apply

Save the patch outside the repository, then run from the root of an unmodified `su1` checkout at `43a2877`:

```sh
git switch su1
git status --short
git apply --check ~/Downloads/smartslope-su1-frontend-dialogs.patch
git apply ~/Downloads/smartslope-su1-frontend-dialogs.patch
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
node --check assets/js/reading-modal.js
node tests/map-boundary.test.cjs
```

Results: PHP lint passed; 25 risk checks, 11 assessment checks, weather-window checks, 26 presentation checks and 2 map-boundary checks passed.

Browser checks used Chromium, PHP and a disposable MariaDB database with synthetic resident/admin accounts and current/outdated records. The weather dialog opened and closed on the dashboard, readings page and admin page, including after AJAX refresh. The checks covered the address fallback, one-column order, removed card content, access roles and a 390px viewport without document overflow. Desktop and mobile screenshots were inspected. The preceding patch verified report submission for an arbitrary Irisan map point.

Weather refresh success/failure responses were simulated using actual authenticated GET response data and a controlled provider-error response. These checks do not establish live-provider availability or landslide predictive accuracy. Verify a real weather refresh and your own Herd/XAMPP deployment after applying.

The patch is applied and checked against a separate clean checkout of its base before delivery. No test users, database fixtures, credentials, or browser dependencies are included in the patch.
