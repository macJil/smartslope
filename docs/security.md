# Security documentation

The project uses several application-level controls. These reduce common web risks but do not replace secure server configuration, HTTPS, review, or deployment testing.

## Implemented controls

- Registration checks required fields and column lengths, uses database uniqueness constraints and creates resident accounts with `password_hash()`; login checks with `password_verify()`.
- Session cookies use `HttpOnly`, `SameSite=Lax`, and `Secure` when HTTPS is detected. Login regenerates the session ID.
- The role comes from the authenticated database account. Admin routes/actions check the session role on the server.
- CSRF tokens/checks are removed as requested. Forms and JSON refresh use POST, but sessions and HTTP methods alone do not provide CSRF protection.
- PDO uses prepared statements, exception mode and native prepares. User-provided names, report messages, reasons and API text are escaped in HTML; dashboard data is rendered through escaped templates.
- The readings endpoint returns JSON 401 for unsigned users and does not expose the stored raw provider payload or adjustment log.
- Weather input is validated for units, numeric ranges, complete hourly intervals, timestamps, and freshness. Coordinates are restricted to the Irisan polygon on the server.
- Generic user-facing errors are logged server-side rather than exposing SQL/provider internals. Apache rules block internal paths and dotfiles.
- The admin creation tool is CLI-only and prompts for a password; `.env` is ignored by Git.

## Required deployment checks

1. Rotate the seeded administrator account password before retaining any non-disposable data.
2. Keep `.env`, database files, scripts, source docs, tests and logs outside public access. Check actual HTTP responses on the chosen server.
3. Apache/XAMPP reads `.htaccess` only when configured to allow overrides. Herd uses Nginx and ignores `.htaccess`; configure equivalent Nginx denials. Protect `config.php`, `functions.php`, `partials`, `data`, `database`, `scripts`, `tests`, `docs` and all dotfiles.
4. Use HTTPS for any network-exposed deployment; the local HTTP setting cannot create a Secure cookie.
5. Keep PHP/MySQL updated, use a least-privilege DB account for deployment, restrict access to backups and exports, and rotate credentials kept in `.env`.
6. Treat report contact details and CSV exports as personal information. Keep only what the project needs and restrict administrator access.

Security testing should include resident attempts to perform administrator actions, session-only requests, injection-like text in reports, literal formula-like CSV values, direct access to internal paths, and provider failure. The automated suite does not constitute a professional penetration test. See [testing](testing.md).

CSV formula escaping, signatures, provider cache/rate budgets and transaction rollback are removed. See [sf2 changes](simplification-sf2.md) for the resulting limitations.
