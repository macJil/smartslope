# XAMPP / MAMP installation (sf2)

Use the normal Apache localhost URL. Read [MAMP localhost setup](mamp-localhost.md)
for the stream-based network requests and server access rules without .htaccess.

Use PHP 8.1+ with PDO MySQL and OpenSSL enabled, and allow_url_fopen enabled. Node.js is only needed for JavaScript tests.

1. Back up your project and database. Put SmartSlope in your configured document
   root, usually a folder named `smartslope` inside `htdocs`.
2. Start Apache and MySQL in XAMPP or MAMP.
3. Edit root `config.php`: DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASS.
   Defaults are 127.0.0.1, 3306, smartslope_mvp, root and a blank password.
   Use the actual MySQL port/password shown by your installation. The web port
   is different from the MySQL port; do not put the web port in DB_PORT.
4. For a new installation only, import `database/schema.sql` using phpMyAdmin.
   For an existing users/locations/events database, keep its data and select
   its name in config.php. Back it up before running any migration.
5. Run `php scripts/check_database.php` using the PHP binary from XAMPP/MAMP.
   If required awareness columns are missing, run
   `php database/migrate-awareness.php` after backing up the database.
6. Open `http://localhost/smartslope/`, adding the Apache web port if needed
   (for example `http://localhost:8888/smartslope/`). No base URL setting is needed.
7. For a freshly imported database, the seed is admin/admin123. Change it using
   `php scripts/create_admin.php admin your-email@example.com +639123456780`.
   The script prompts for the new password. Existing accounts retain their passwords.

No `.env` is loaded. If you have one, copy the database values into config.php
before removing it. The patch removes the tracked example, not your untracked
local credential file. Do not commit private database passwords.

`NOMINATIM_ENABLED` controls optional address lookup. It defaults to false.
The `$config['freshness_seconds']` setting is 10800 (three hours).
Weather refresh needs internet access; saved readings and local map assets do not.

## Troubleshooting and acceptance

- Missing PDO driver: enable pdo_mysql in the PHP runtime used by Apache.
- Connection/access errors: check MySQL is running and config.php matches it.
- Missing tables/columns: confirm the selected database; do not overwrite an
  existing database with schema.sql just to repair login.
- Refresh errors: check allow_url_fopen, OpenSSL, connectivity and the PHP error log. Provider
  failure should preserve earlier saved readings.
- Include server/apache-smartslope.conf in your Apache configuration and verify
  its paths. This replaces .htaccess protection; there is no launcher or router.
- Run docs/testing.md and manually check map selection, refresh, reports,
  admin review, reading edits, CSV import/export, login and logout.

This setup targets a local classroom prototype. Existing omissions such as
CSRF protection must be addressed before public deployment.

## Apache Internal Server Error after clicking the map

The map submits to dashboard.php?action=select_location. Apache's generic
error page does not reveal its cause. Read the Apache error log for the
matching request timestamp; PHP errors are separate from server-rule errors.

The previous patch addressed an Apache rewrite configuration error. The
subsequently supplied objc_initializeAfterForkError log identifies a different
PHP process crash. The current patch replaces cURL network requests with PHP streams; use the normal localhost URL and test your MAMP runtime again.
On a typical macOS XAMPP installation the log is under
/Applications/XAMPP/xamppfiles/logs/error_log; MAMP commonly uses
/Applications/MAMP/logs/apache_error.log. Check the application's configured
log location if yours differs.

If the same generic error persists, capture its corresponding Apache log
entry and the requested URL. Parent-directory rules, PHP handler settings,
file permissions and server configuration cannot be diagnosed from that page
alone. Do not delete all access rules to hide an error.
