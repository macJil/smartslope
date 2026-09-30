# SmartSlope academic prototype

A PHP 8.1+/MySQL application for Barangay Irisan, Baguio City. It has resident and administrator accounts, sourced location susceptibility, resident reports, Open-Meteo weather ingestion, stored rainfall analysis and prototype alerts. The `sensors` table registers Open-Meteo as a **virtual weather API source**; there is no physical hardware. Risk thresholds are provisional classroom rules, not official safety warnings.

## Setup (Herd / XAMPP)

1. Use PHP 8.1+, PDO MySQL and PHP cURL with verified HTTPS (or verified HTTPS streams). Use MySQL 8+ for the migration script.
2. Copy `.env.example` to `.env`; set MySQL credentials and `APP_BASE_PATH`: empty for a Herd domain, `/landslide` when installed at `http://localhost/landslide` in XAMPP. Keep `.env` private.
3. **New database:** import `db.sql`. **Database created from `smartslope` main `389a0b3`:** back it up and run `sql/migrate_existing_to_aligned.sql` once, after inspecting its preflight queries. Do not run `db.sql` over a populated database; `CREATE TABLE IF NOT EXISTS` cannot upgrade old tables.
4. Existing accounts retain their email and can log in by username. The upgrade adds nullable `contact_number` until genuine numbers have been supplied. New registration requires a unique email and phone number. After supplying real contact details for existing users, run the updated `sql/finish_contact_migration.sql` to enforce both fields. If an earlier migration already dropped `email`, run `sql/restore_email_for_contact_only.sql` first, obtain genuine emails, and then finish. Never fabricate contact details.
5. Register the first account and promote only that chosen account locally with `UPDATE users SET role='admin' WHERE username='chosen_name';`. Log out and log back in. All public registrations get role `user`.
6. Add a verified Irisan location with coordinates and an independently sourced baseline susceptibility using `admin/locations.php`. The location CRUD page is kept for administrator maintenance but no longer appears in the main menu. Click its map marker to see saved readings; Refresh saves new provider observations.

## What each record means

- `sensors`: one registered provider source per location. Its `sensor_type='weather_api'` does not imply physical measurement.
- `weather_observations`: current interval and historical hourly model data, stored at actual observation time in UTC; repeated fetches update the same row.
- `readings`: 1/24/72-hour rainfall totals calculated **from stored hourly observations**, plus the derived prototype risk. An admin may correct an existing API summary.
- `alerts`: medium/high prototype indicators linked to a reading and location. A corrected reading synchronizes its alert.
- `reports`: resident ground observations and administrator review; these reports are not silently used as weather measurements.

The application uses a single PHP bootstrap, PDO repositories and jQuery AJAX for the weather endpoint. Both dashboards use local Leaflet 1.9.4 assets and an Irisan outline extracted from the `weather` reference repository; **the outline's original provenance must be independently checked before operational use**. This lightweight map does not include raster tiles, road names or an official hazard layer. It works locally without a map service, while live weather still needs internet. Marker selection reads saved observations via `api/location_dashboard.php`; the Refresh button persists new observations using `api/weather.php`. Administrators see pending report counts at location markers and reporter email/phone in the existing review table. Admin reading pages still edit, archive and export API readings. Other forms use POST/redirect/GET. `app_url()` handles Herd and XAMPP browser routes; `__DIR__` handles filesystem includes.

## Verification

Run `node --test tests/resident-weather.test.cjs` and `php -l` for PHP files. Then on Herd or XAMPP test both contact fields, login, marker selection, saved dashboard, refresh, saved sensor/observation/reading/alert rows, second refresh without duplicates, mapped report submission, admin pending marker count and contact display, report review and CSV. Verify a failed provider request, incomplete hourly data and a stale observation do not show a fresh low-risk result. See `docs/REQUIREMENTS.md` for the course trace and `docs/SmartSlope_Progress2_Design.md` for schema documentation.

The older scripts `sql/add_weather_observations.sql`, `sql/repair_weather_readings.sql` and `db_migration_archive_readings.sql` are retained only for installations predating the current `smartslope` baseline. Do not run them after the aligned migration. `.htaccess` protects development/configuration files in Apache/XAMPP; Herd's Nginx ignores `.htaccess`, so do not expose the entire repository as a public document root without equivalent restrictions.