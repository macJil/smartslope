# Normal MAMP/XAMPP localhost setup

Patch base: sf2 at 28762ffa538d134ed9de93c3408ae1dc084f22cd.

1. Keep the project in MAMP's configured document root (commonly
   /Applications/MAMP/htdocs/smartslope). Start Apache and MySQL from MAMP.
2. Keep your working database values in config.php. The MySQL port and Apache
   web port are different; this patch does not change your database settings.
3. In MAMP's PHP settings, use PHP 8.1+ with pdo_mysql and OpenSSL enabled.
   In its php.ini, enable allow_url_fopen. MAMP provides a separate php.ini
   for each installed PHP version. Restart the servers after changing PHP settings.
4. Open http://localhost:8888/smartslope/ using your actual Apache web port.
   For XAMPP this is commonly http://localhost/smartslope/.
5. Log in and select an Irisan point. Selection still fetches weather, saves a
   reading and reloads the same dashboard. The Refresh button still works.

## Access rules without .htaccess

Edit the paths in server/apache-smartslope.conf if your document root differs.
Back up MAMP's active Apache configuration (commonly
/Applications/MAMP/conf/apache/httpd.conf) and add this line outside other blocks:

    Include "/Applications/MAMP/htdocs/smartslope/server/apache-smartslope.conf"

Restart Apache and verify that config.php, database/schema.sql and
includes/data.php return 403. Public pages and assets must still load.
These are server-level authorization rules: no rewrite module or .htaccess.
If MAMP regenerates its configuration, add the Include through the active
template/configuration mechanism for your edition and verify it after restart.

## Code changes and the macOS crash

Both weather and optional address lookup now use file_get_contents() with an
HTTP context, a timeout and verified TLS, instead of cURL. The application
keeps its API parameters, unit/range checks, saved hourly inputs, risk rules,
CSV format, routes and HTML. Failed weather requests preserve earlier readings.
The exception helper now logs the full error and displays a short setup message;
the database_error_message function is replaced by one message constant.

Removed router.php, scripts/start-local.sh and their superseded setup guide.
PHP code was formatted for readability; redundant session-role expressions were
shortened. Tiny escaping/input/date/flash helpers remain because they avoid
duplicating the same useful operation throughout the pages.

The supplied objc_initializeAfterForkError log identifies a native process
crash. cURL runs on map selection, so avoiding it is a targeted compatibility
change; the log alone does not prove which native dependency caused the crash.
The stream path was verified with actual HTTP requests and a disposable database
on Linux. The specific macOS MAMP process still requires local testing.
If the same objc crash remains, a PHP/MAMP runtime update or handler change is
needed; application try/catch cannot recover a terminated FastCGI process.
Record the MAMP/PHP versions and new Apache error entry rather than disabling
TLS verification or repeatedly changing website formulas.

Verification: existing PHP suites, map tests, syntax checks, login/admin/CSV/
report workflows, map selection and JSON network success/failure checks. Apache
configuration was checked separately for public routes and private files.

References:
- https://www.php.net/manual/en/context.http.php
- https://www.php.net/manual/en/context.ssl.php
- https://github.com/php/php-src/issues/11818
