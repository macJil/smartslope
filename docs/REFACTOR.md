# Structure and migration history

The current layout uses `admin/`, `resident/`, `app/`, `api/`, `configs/`, `assets/` and `sql/`. It keeps the simple OOP/PDO structure adapted from the Umbalin reference without copying its multi-disaster scope. The latest database design supersedes earlier `landslide` and `smartslope` drafts.

## What changed from smartslope main 389a0b3

- Users register with contact numbers. Existing users are preserved during a staged data migration.
- `sensors` registers Open-Meteo API sources; `weather_observations` links via `sensor_id`. Data are provider model estimates, not physical sensor measurements.
- Stored hourly rainfall is summarized into `readings`; medium/high risk creates `alerts`. Missing or stale provider data are labeled.
- Unused soil-moisture collection/display was removed. Existing historic soil columns are retained on upgraded databases to avoid deleting data; new databases omit them.
- Admin reading Add and Restore controls were removed. Delete hides a reading from active views and exports but retains history; correction changes risk and alert in a transaction.
- A minimal public summary shows sourced baseline susceptibility and the latest API reading. Report queries consistently restrict to the study barangay.

The original `sql/add_weather_observations.sql` and `sql/repair_weather_readings.sql` apply to older `landslide` schemas and do not produce the new final schema. Run `sql/migrate_existing_to_aligned.sql` for an existing `smartslope` main installation, or import `db.sql` for a fresh database. Confirm historic values fit smaller field types before migrating. The final nullable-contact transition must be completed with verified contact numbers.

No live provider, MySQL migration or Herd/XAMPP browser acceptance is claimed by syntax and mocked JavaScript tests alone.
