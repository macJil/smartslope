# Admin and CSV fixes (su2)

Baseline: `cebdad7590bb8ad9ce0915b8221f1c7dfa0d656b`.

## Install

Apply the patch to your `su2` checkout after checking your working tree and backing
up the database. Run `git apply --check <patch-path>`, then `git apply <patch-path>`.
Run `php database/migrate-awareness.php`, or log in as administrator and use
**Complete database setup** if the warning appears. The repeat-safe migration adds
missing awareness columns/index; it retains the same three tables and existing
records. MySQL DDL is not transactional. Do not reimport schema.sql over your data.

## Report and reading actions

All/Pending/Reviewed/Resolved filter community reports only. The selected filter
is highlighted and retained after a modal action. The reports CSV follows it.
Click **View**, then **Mark as reviewed** for pending reports or **Mark as resolved**
for reviewed reports. Resolved reports have no further status action. Stale or
repeated submissions show an error instead of claiming another change occurred.
Review status is administrative; it does not verify a reported landslide.

Reading **Edit** saves an administrator category with a required reason, reviewer
ID and UTC timestamp. Rainfall inputs and calculated category remain separate.
Missing setup or invalid input produces an actionable message.

## CSV exports and location import

All exports use UTF-8 with a BOM, RFC-compatible quoted fields and CRLF records.
The CSV writer explicitly supplies the escape argument required by PHP 8.4.
Headers use plain language and units. Dates labeled PHT are Philippine time
(UTC+8), in year-month-day hour:minute:second order. Empty measurements mean not
recorded, not zero. Audit edits are readable text; raw hourly JSON stays in the
database. Spreadsheet formula-looking user text is escaped with an apostrophe.
CSV files contain data, not spreadsheet styling or fixed column widths.

**Download locations CSV** exports active locations shown in All Locations.
**Import locations CSV** accepts only that same installation's signed location
format, up to 5 MB / 5,000 locations. Files from other websites, modified values,
wrong columns, missing signatures, report/reading exports and earlier unsigned
exports are rejected with an “Invalid/not applicable” message. Download a new
locations export after installing this update. Opening a file is fine; avoid
editing/re-saving it before import because spreadsheet programs may change data.

Imports restore name, purok, landmark and active status by coordinates. Existing
location IDs, readings, reports and susceptibility metadata remain linked.
Missing coordinates are inserted with unknown susceptibility; records absent
from the file are not removed. Validation finishes before any writes, and all
row changes commit together. Imported locations must lie within the existing
Irisan boundary. CSV location details are administrative/user-supplied data;
a signature proves site origin and integrity, not environmental verification.

The first export creates a random `CSV_SIGNING_KEY` in the site's `.env`. Back up
that key along with the database; replacing it invalidates older exports. Keep
`.env` inaccessible through your web server, as with existing database secrets.
If PHP cannot write `.env`, generate a key with
`php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` and set `CSV_SIGNING_KEY` to
that 64-character value in your deployment environment. Never commit the key.
For large imports, set PHP `upload_max_filesize` to at least `5M` and
`post_max_size` above `5M` (for example `8M`).

## Validation

Run existing PHP tests and `php tests/csv.php`; the latter treats warnings as
failures and checks quoting, Unicode, timezone conversion, formula protection,
round trips and rejection of damaged/foreign/wrong-format files.
`tests/integration-awareness.php` requires a disposable database ending in
`_test`; it covers database persistence, review provenance and reading audit logs.

This patch was exercised with PHP 8.3, MariaDB and Chromium against disposable
test data, including a pre-awareness schema repaired through the admin page.
All four filters, modal review/resolve, reading edit, CSV exports, signed import,
tampered rejection, location restoration, mobile layout, CSRF/access control,
dashboard refresh and report submission were checked. Weather refresh used
TEST/DEMO provider fixtures, not a live upstream API. The PHP 8.4 deprecation fix
uses the documented explicit escape argument; PHP 8.4 itself was not available
in the test environment. Production Herd configuration remains a deployment check.
