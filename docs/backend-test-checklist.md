# Backend acceptance checklist

Use a disposable copy of your existing legacy wide-`events` database first. Run `php database/migrate-awareness.php` on the disposable copy and then your backed-up real database before updated pages are served. Back up code and data. Do not replace your real `.env` with the example.

## Automated checks (PHP 8.1+ and Node)

```bash
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
php tests/risk.php
php tests/weather.php
php tests/assessment.php
php tests/presentation.php
php tests/awareness.php
node --check assets/js/dashboard.js
node --check assets/js/location-address.js
node tests/map-boundary.test.cjs
```

## Herd and XAMPP acceptance

Test both `/` on Herd and `/smartslope/` on XAMPP. Verify PHP PDO MySQL and cURL are enabled. Use separate browser profiles or normal/private windows for resident/admin sessions.

1. Register a unique resident; check missing/overlength fields, invalid phone/email, short password and duplicate username/email/phone. Confirm server-assigned resident role.
2. Login/logout; ensure resident requests to admin mutations cannot change data. Invalid/missing CSRF must return 403.
3. Select an Irisan point. Confirm a successful API response creates one snapshot and updates the selected list/marker. A second successful refresh appends another snapshot, even if observation time is unchanged.
4. Disconnect internet. Map tiles/boundary should remain available. Weather refresh fails visibly and preserves stored rows. Optional reverse geocoding times out after four seconds.
5. Set an observation time older than three hours in a disposable test database. Reload dashboard, admin, readings and report view. They must show outdated/last-known data and no current colored risk marker.
6. In a test database, set rainfall or risk to null. Pages and AJAX must remain usable and display incomplete/unavailable.
7. Submit reports with required phone, optional email and a map location. Review/resolve the report as admin. Open multiple report maps and confirm correct coordinates. Reporter identity must not change.
8. Edit a reading category. Weather/location/time must remain unchanged. A differing category shows administrator-adjusted. Archive rows and remove locations; ensure historical rows are retained in the database. Existing active-list filtering excludes inactive locations.
9. Test bulk removal, empty selection and malformed IDs. Database failures roll back the selected bulk transaction.
10. Export reports/readings CSV; check contacts, UTC columns, freshness, adjusted status, and formula-like report text.
11. API: logged-out GET returns JSON 401; invalid POST token 403; malformed ID 422; inactive/missing location 404; unsupported method 405; provider failure 503. GET must not insert rows.
12. Direct HTTP access to `.env`, `database/schema.sql`, `app/config.php` and `tests/risk.php` must be denied. Check server logs for errors.

## Server access protection

Apache reads the supplied `.htaccess` when overrides/mod_rewrite are enabled. Herd uses Nginx and does NOT read `.htaccess`. Before exposing the site beyond local testing, add equivalent rules to the site's Nginx server configuration, validate it, and reload through your server management workflow:

```nginx
location ~ ^/(?:app|data|database|scripts|tests|docs)(?:/|$) { deny all; }
location ~ /\.(?!well-known(?:/|$)) { deny all; }
location ~* \.(?:sql|patch|log|md)$ { deny all; }
```

Place these regex rules before the general PHP handler so internal PHP paths are denied. For Nginx installations under a subdirectory, adjust the leading path accordingly. Never replace Herd's entire generated configuration blindly. Validate the four URLs above on the actual installation; application PHP cannot prevent a server from serving static secrets.

## Administrator credential rotation

Run locally, with the username and desired email/phone of the existing admin:

```bash
php scripts/create_admin.php admin your-email@example.com 09123456789
```

The script prompts for a password; it does not promote a resident username. No password is changed by applying the patch. Do not leave the SQL seed's documented default password in use.

## Academic scope

PDO/CRUD, OOP risk class, jQuery/AJAX, JSON/API, validation and security remain. Shared event data is separated from reading and report details. This does not satisfy a separate mandatory teacher requirement for a sensors table unless its deferral is approved. Passing code tests does not validate landslide predictive accuracy.
