# SmartSlope awareness upgrade

Baseline: su1 a8ae411204c785e18c0159c94b395c8a437e4f38.
Sensors and AI are excluded. Manual weather refresh remains. Three tables remain:
users, locations, events. No alert/sensor table or background task is introduced.

## Install on Herd / XAMPP

1. Back up your database and working tree. Switch to su1 and check git status.
2. Download the patch outside the repository, then run `git apply --check` and
   `git apply` as described in the delivered setup guide.
3. Before serving updated pages, run `php database/migrate-awareness.php` from
   the repository root, using the PHP version whose PDO MySQL/cURL modules are
   enabled and the project's existing .env configuration. The CLI-only migration
   checks each column/index before adding it and can be rerun after interruption.
   MySQL DDL is not transactional; take a backup first. Do not reimport schema.sql
   into an existing database. New installations may import the updated schema.
4. Restart PHP if needed. Load dashboard, select Irisan point, refresh, open
   Sources & methodology, submit a typed report with Philippine occurrence time,
   review pending reports, make an administrator edit with reason, export CSV.
5. Weather failure must keep previous readings. Outdated data must not show a
   current high/medium notice. A low category must never say the slope is safe.

## Changes

- Complete saved totals must satisfy 1h <= 24h <= 72h; mismatched rule versions
  cannot silently receive a current assessment. Legacy version/input provenance
  is labeled unavailable rather than reconstructed.
- New readings save rule_version, rainfall_window_end and provider_payload with
  the full normalized hourly input series, returned grid metadata, request point,
  provider retrieval time and policy. provider_payload is omitted from routine
  AJAX JSON to keep responses small. Existing weather columns are unchanged.
- Optional context with unrecognized/missing units is discarded and becomes null;
  required rainfall units and current weather validation still apply.
- Shared local-host provider locks, request budgets and caches reduce calls.
  Cached weather can append another snapshot, preserving the provider valid time.
  Multiple PHP processes share the state. Multiple deployment hosts do not:
  configure shared rate storage before running more than one host. Temp-cache
  resets reset budgets; these safeguards are not a guarantee against all quotas.
- Alert notices use the calculated rainfall category. Administrator categories
  remain visibly separate. Existing synthetic prototype thresholds are unchanged.
- Each new risk edit appends actor ID, UTC time, previous/new value and reason to
  adjustment_log. Legacy edits have no backfilled audit. Report reviews record
  the last reviewer/time; reports preserve original user_id.
- Report type and occurred_at are distinct fields, not encoded inside messages.
  Occurrence is optional, parsed strictly in Asia/Manila and stored UTC; future
  input is rejected. Resident location summaries reveal counts, not contacts.
- History collapses repeated provider times for display. CSV exports retain
  snapshots and include source/version/window/input-retention and audit JSON.
- Verified susceptibility uses a reviewed local GeoJSON subset only; existing
  hand-entered location classes do not become VERIFIED. See data/README.md.

## Optional Nominatim

Automatic external geocoding is disabled by default. User landmarks and coordinate
labels work without it. To deliberately enable the existing address aid, review
https://operations.osmfoundation.org/policies/nominatim/ and set
NOMINATIM_ENABLED=1 in .env. The server identifies SmartSlope, enforces shared
1.1s spacing, caches 24h, and preserves fallback on errors. Attribution is shown.
Change api/address.php to switch provider if requested; never bulk/geocode a grid.

## Evidence and scientific limits

No verified local susceptibility subset or historical event catalog was obtained.
No new threshold calibration was claimed. docs/local-validation.csv contains
headers only. Fill it with sourced events AND non-event periods, uncertainty and
comparable rainfall; evaluate false alarms/missed events on separate periods.
No fabricated environmental records or demo layers are installed.

Data contract and all eleven source-review questions appear in methodology.php:
need, reason, origin, reliability, geography, spatial/temporal resolution, cost,
limits, live vs saved use, and analysis effect. Official advisory links open the
publishers; they are not live advisory feeds and make no freshness claim.

Apache denies internals via .htaccess (now includes data/). Herd/Nginx does not
read .htaccess: configure equivalent denial for app, data, database, scripts,
tests and docs and .env before external hosting. PHP's development server is
for local tests only and does not enforce these Apache rules.
