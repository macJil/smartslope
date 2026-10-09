# MAMP macOS FastCGI crash

Base: sf2 at 14da683e57a639415c4db786449959cd7c45294b.

The supplied log reports objc_initializeAfterForkError followed by FastCGI
incomplete headers from /Applications/MAMP/fcgi-bin/php.fcgi. PHP is being
terminated by the Objective-C runtime, so a PHP try/catch cannot recover it.
This is distinct from the previously tested missing-mod_rewrite failure.
Deleting .htaccess does not itself repair a native PHP process crash.

## Run locally without FastCGI

1. Keep MAMP MySQL running. Apache is not used by this launch method.
2. Keep the database credentials and actual MySQL port in config.php.
3. From the project folder run `bash scripts/start-local.sh`.
4. Open http://127.0.0.1:8000/ and log in again. Do not use the old :8888 URL
   for this test: it still uses MAMP Apache/FastCGI.
5. Click an Irisan map point, refresh, submit a report, and test admin CSV.
   Stop the local PHP server with Ctrl+C; stop MySQL in MAMP when finished.

The launcher selects an available compatible MAMP/XAMPP PHP binary, or accepts
one explicitly: `bash scripts/start-local.sh /full/path/to/php`.
It disables PHP_CLI_SERVER_WORKERS and binds only to 127.0.0.1. It changes no
MAMP installation files, database settings or operating-system environment.
This is a local development workaround, not a repair to MAMP's FastCGI binary.

The PHP router allows existing public pages and assets and returns 404 for
internal files. Start with the launcher; plain `php -S` without router.php
does not provide those restrictions. PHP's development server is for local
use, not public hosting. An Apache installation will need equivalent rules
in its server configuration before exposing this repository; it does not run
router.php automatically. Removing .htaccess means the old Apache URL no
longer has the repository's internal-file download restrictions.

If the CLI launch also crashes, retain its terminal output and PHP version.
The Linux test environment cannot reproduce or certify a macOS-native crash.
Use a compatible updated MAMP/PHP build or XAMPP for Apache hosting and confirm
the PHP handler with that vendor. Do not add putenv() to application code:
it cannot reliably fix runtime initialization that happened before the request.

References:
- https://github.com/php/php-src/issues/11818 (related macOS fork crash)
- https://www.php.net/manual/en/features.commandline.webserver.php (router and single-process local server)

Cleanup: .htaccess is removed. The generated patch was already removed in
the previous commit. Existing routes, modules, assets, SQL, tests and project
documentation are retained because they still have a purpose.
