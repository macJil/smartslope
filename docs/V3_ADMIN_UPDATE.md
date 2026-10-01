# v3 registration and admin controls

Base GitHub v3 commit: dfef2507d86762fe2246025d7fced23a3b464743.

## Apply

- If the previous `smartslope-v3-dashboard-fixes.patch` is already applied, use `smartslope-v3-admin-update.patch`.
- If your files still match GitHub v3, use `smartslope-v3-complete-update.patch` (includes the prior dashboard fixes).
- Apply only one of these two patches. From your repository root, run `git apply --check /path/to/patch`, then `git apply /path/to/patch` only when the check succeeds.
- Back up the database. Import `sql/add_reading_location_override.sql` once into your existing database before opening the dashboard. Fresh installations use the updated `db.sql` instead.
- Reload the browser. Both Herd and XAMPP routes continue to use `app_url()` and filesystem includes use `__DIR__`.

## What changes

Registration now identifies actual username, email, or phone collisions. Only MySQL error 1062 is treated as a duplicate-key error. Other database failures are logged accurately. The repository schema already defines AUTO_INCREMENT, so a local schema mismatch cannot be diagnosed from the public repository alone. If unused values fail, run `sql/diagnose_registration.sql` in the configured database and inspect the PHP log entry beginning `SmartSlope registration failed`. No accounts or uniqueness constraints are removed.

The outdated `sql/finish_contact_migration.sql` used to drop email. It now keeps both contact fields. Do not rerun migrations blindly; if email was already dropped, follow the existing restore-email migration and supply genuine missing data.

The map card has an admin-only **Remove from map** form. It archives the point using `locations.is_active`, retaining reports and reading history. Re-clicking the same rounded coordinate reactivates the point. Saved logs from removed points remain in the all-readings list.

The Resident reports card has **Delete report** and **Download all reports CSV**. Delete permanently removes that report. CSV contains all remaining Irisan reports across statuses, plus reporter contacts and review details. Downloads require an administrator session. User text is escaped for CSV formulas.

Reading Edit now offers only **Location** and **Risk at fetch**. The backend accepts only those fields, and the old rainfall-edit action is rejected. Original temperature, humidity, precipitation, wind, source sensor, hourly data, and live risk computation remain provider-sourced. The nullable `weather_fetches.location_id` stores the corrected displayed location; NULL uses the original sensor location. The saved risk label can be corrected to low, normal, medium, or high. These corrections appear in the list/reading CSV; they do not change live alerts.

## Local verification

1. Register a new username/email/phone, then retry each duplicate field separately. A genuinely new account must receive role user and a hashed password. If registration reports a database conflict, inspect the logged MySQL error and the diagnostic SQL output.
2. As admin, remove a point. Its marker disappears after redirect; its stored logs/reports remain. Click its coordinates again to reactivate it.
3. Download reports CSV, including text starting with = or +; confirm it is treated as text. Delete a test report and confirm it disappears from the queue and pending marker count.
4. Edit a saved log's location and risk. Confirm temperature/rain/wind are unchanged, the row moves to the chosen location, and CSV shows the corrected values. POSTing old temperature/rainfall edit fields must not modify measurements.
5. As resident, requests to admin endpoints must be denied. Invalid CSRF must reject mutations.
6. Run `node --test tests/*.test.cjs`, `php tests/repositories.php` (needs PDO SQLite), PHP lint, and `php tests/mysql_integration.php` against a disposable MySQL database imported from the updated db.sql.

JavaScript tests and clean-patch application were run in the preparation workspace. PHP/MySQL runtime execution was unavailable there; confirm the local verification steps with Herd or XAMPP before relying on the changes.
