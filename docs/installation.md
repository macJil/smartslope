# Installation guide

This guide matches branch `f1`. Use PHP 8.1+, MySQL with the PDO MySQL driver, cURL, and a local web server. Node.js is only needed for the map test. Herd uses Nginx; XAMPP uses Apache and commonly serves the project under `/smartslope`.

## New local installation

1. Clone or download branch `f1` and place it in the web root.
2. Copy `.env.example` to `.env` only if you do not already have one. Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for the local server. The sample database name is `smartslope_mvp`. No base-path setting is needed: browser URLs are relative and includes use `__DIR__`.
3. Import `database/schema.sql` into MySQL. It creates the database and tables and inserts the initial administrator and one Irisan pilot location.
4. Start PHP and MySQL, then open the site root in Herd or `http://localhost/smartslope/` in XAMPP.
5. Rotate the seeded administrator credential immediately. From the project root, run:

   ```bash
   php scripts/create_admin.php admin your-email@example.com +639123456780
   ```

   Enter a unique email and phone and choose a password of at least 12 characters when prompted. The script updates the existing `admin` account or creates it if absent. Never use the sample password outside a disposable database.

## Existing database

1. Back up the database and project files.
2. Confirm that the existing database is the current three-table `users`, `locations`, `events` design. Do not import `database/schema.sql` over data you want to keep.
3. Set `.env` to the existing database, then run `php database/migrate-awareness.php` from the repository root. The CLI migration adds the awareness fields and history index to `events` if they are missing, and can be rerun. MySQL DDL may commit one change at a time, so keep the backup.
4. Open the app and test sign-in, a read-only dashboard load, a refresh, report submission and admin review before using real records.

The migration does not convert a different legacy schema such as the older nine-table design. Compare it and plan a data migration separately before pointing this branch at that database.

## Local settings

`READING_MAX_AGE_SECONDS` defaults to 10800 (three hours). It controls whether a saved observation is shown as current. It is not a landslide threshold. `NOMINATIM_ENABLED` defaults to `0`; leave it off unless the team has reviewed the provider policy. `CSV_SIGNING_KEY` is optional; location CSV export creates a key in `.env` if needed, so back up `.env` after using that feature.

## Deployment notes

Do not commit `.env`. Apache reads the supplied `.htaccess` when overrides are enabled. Herd/Nginx ignores `.htaccess`, so block `.env` and internal directories (`app`, `data`, `database`, `scripts`, `tests`, `docs`) in the site configuration and test the resulting HTTP responses. Do not expose the PHP development server to the internet. See [security](security.md).

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Database connection error | Confirm MySQL is running, `.env` credentials and database name, and `pdo_mysql` is enabled. |
| Migration says setup incomplete | Run `php database/migrate-awareness.php` with the same `.env` and PHP runtime the site uses. |
| Refresh fails but saved readings remain | Check internet access, cURL, provider response, and PHP error log. A provider failure should not erase history. |
| Wrong links on XAMPP | Open `/smartslope/` or `/smartslope/index.php`; ensure the root page files, config.php, functions.php and assets are deployed together. |
| Internal PHP/SQL files open in browser | Fix server-level access rules. `.htaccess` alone does not protect Nginx. |

See [testing](testing.md) for local acceptance steps after installation.
