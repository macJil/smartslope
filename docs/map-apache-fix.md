# Map request and Apache compatibility repair

Base: sf2 at aa8d39d552707cde89e4be915791972fd7c57875.

Reproduced an Apache 500 with the former .htaccess and mod_rewrite unavailable:
Apache logged "Invalid command 'RewriteEngine'". The replacement uses
Apache 2.4 authorization rules without rewrite or Options directives.
This fixes that reproducible configuration failure; it does not establish
the exact cause on a machine whose Apache error log has not been supplied.

Apache checks: dashboard map POST and assets return 200; config.php,
includes/, scripts/ and .git/ return 403. Application checks use PHP and a
disposable MariaDB database: existing/new map points save readings; provider
failure redirects to the dashboard and preserves history; the AJAX endpoint
returns its existing JSON 503 error. The full workflow checks also cover
login, registration, role restrictions, reports, reading edits, CSV and logout.
HTML comparisons preserve the existing page output. Live provider availability
and browser map interactions still require local acceptance testing.

Removed smartslope-sf2-simple-xampp.patch, a generated artifact already applied
to this branch. Root patch files are now ignored. Compatibility routes remain
because they preserve old bookmarks and form URLs. Tests, database scripts,
documentation and application modules remain in use.
