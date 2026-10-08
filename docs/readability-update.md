# Login and readability update

Patch base: `730e4f0f67eab79586e6b5ff208e8c788523c679` on `sf2`.

The `macJil/umbalin` login page is the reference for the procedural flow, not a
replacement for SmartSlope's design or database. Its login uses mysqli; SmartSlope
continues to use PDO prepared statements with the existing password hashes.

Changes:

- Login is a visible sequence: fetch user, verify password, assign session values,
  then choose the admin or resident destination with ordinary if statements.
- Failed login/registration displays an error in the current response. Previously
  it stored a flash message and redirected before showing the error. Success still
  redirects, which avoids resubmitting a successful registration on page reload.
- Login and registration are exclusive branches. Session ID regeneration, cookie
  settings, resident-only registration and server-side role checks remain.
- PDO functions are grouped by users, locations, readings and reports. Compressed
  conditionals and statements are expanded, with consistent PHP indentation.
- Config remains root-level with direct __DIR__ includes and no URL-path detection.

The database schema, SQL queries, risk thresholds, page layout, assets and API
response fields are unchanged. More readable formatting can use more lines; the
goal is simpler control flow and easier navigation, not an arbitrary file size.

Validation covers the existing PHP suites, syntax checks, database/CSV workflows,
fixture-provider refresh, login and registration, database failure messages,
administrator access, and both site-root and subdirectory URLs. Rendered page HTML
is compared with the base. Live external provider availability and visual browser
rendering are not certified by these checks.

Apply only to the base above or an equivalent working tree:

```sh
git apply --check smartslope-sf2-readable-login.patch
git apply smartslope-sf2-readable-login.patch
php scripts/check_database.php
```

Keep the existing .env and database; this patch requires no schema reset.
