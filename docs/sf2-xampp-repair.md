# sf2 simplification and XAMPP/MAMP repair

Patch base: `58c1e9eec0800f4a59477b5e8824721f3a1000ae`.

The incomplete split left endpoints requiring the deleted functions.php,
omitted report, rendering, awareness and setup functions, and introduced
output before PHP in map/redirect files. This patch repairs the includes and
restores the existing operations in separate includes/ modules.

Pages call session_start() directly and check $_SESSION user_id and role.
The start_session, is_logged_in, is_admin, require_login and require_admin
wrappers are removed. Login follows the reference project's query/password/
session/redirect sequence, using PDO, password_verify and session regeneration.

config.php contains plain database constants. No environment loader remains.
The tracked .env.example is removed. Copy the actual database settings from
any existing local .env into config.php before deleting that local file.
Use your XAMPP/MAMP MySQL port, not its Apache web port.

functions.php holds only small common helpers. Database operations, risk,
weather, geography, CSV, schema checks, awareness and HTML rendering have
separate modules. Every entry point includes its dependencies with __DIR__.
The schema, assets, risk rules and existing UI remain unchanged.

Verification: 40 PHP syntax checks; six PHP test suites; two map-boundary tests;
JavaScript syntax checks; 157 database-suite/HTTP/HTML/path assertions including
login, registration, access control, report review, reading edits, CSV round
trips, logout, subfolder URLs and database failures. HTML comparisons use the
last working version because the current branch has broken dependencies.
Weather refresh also passed with fixture data and a real disposable database.

Actual XAMPP/MAMP installation, browser interactions and live weather provider
availability require local acceptance checks. The existing lack of CSRF
protection remains a limitation of this local academic prototype.
