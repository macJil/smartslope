# User manual

## Resident

### Create an account and sign in

Open the home page, choose registration, and enter the requested name, username, email, phone and password. Registration assigns the resident role on the server. Sign in with the username and password. Use Logout when finished.

### View an Irisan point

On the dashboard, click a point inside the Barangay Irisan map boundary. The dashboard selects or creates a saved monitoring location for that point. Use the reading summary to see provider time, retrieval time, rainfall totals, current assessment status, and any explanation. Open weather details for secondary weather values. The map and bundled tiles load locally.

### Refresh weather

Press **Refresh** for the selected point. The browser sends an authenticated AJAX POST to SmartSlope. The server requests Open-Meteo, checks the response, calculates the rainfall indicators and saves a new reading. A successful click creates a snapshot even when the provider observation hour repeats. Refresh needs an internet connection. If the provider fails, the interface reports the failure and retains prior readings.

### Submit a ground report

Choose **Submit a ground report**, select or confirm the map point, select a report type, describe what was observed, and provide the required contact phone. Email, landmark and occurrence time are optional where the form allows them. Submit the report. New reports enter the pending queue for administrator review. A report describes a resident observation; it does not change the rainfall category.

### Read categories responsibly

Categories are prototype rainfall screens. `Low` and `Normal` do not mean the slope is safe, and SmartSlope is not an official warning service. Check current official advisories and local authorities when making safety decisions.

## Administrator

The team provisions administrator accounts from the command line with `scripts/create_admin.php`; public registration cannot choose the admin role. Sign in with that account and open the admin panel.

- Review pending reports, then mark a report reviewed or resolved. The application records the reviewer and time.
- Add, update, deactivate, or import/export locations using the admin controls. Locations must fall within the Irisan boundary for map reading refresh.
- Inspect reading history. An administrator can change only the stored risk category through the reading edit flow and must supply a reason. The calculated rainfall category remains visible separately. New edits are logged.
- Archive readings to remove them from active views while keeping the event record. The UI also offers deletion where implemented; confirm the selected record before using destructive controls.
- Export readings, reports, or locations as CSV. Treat contact information as personal data. Protect location exports with the generated signing key and keep `.env` private.

The API and form actions require sign-in. Residents do not gain administrator rights by editing browser data; the server checks the session role.

## Status terms

| Status | Meaning |
| --- | --- |
| Current | Observation is within the configured age window and passes validation. |
| Outdated | The last saved observation is too old to represent current conditions. |
| Incomplete or unavailable | Required rainfall, timestamps, or saved data cannot support a current assessment. |
| Adjusted | An administrator changed the displayed category from the calculated rainfall category, with a reason recorded for new edits. |

See [analysis](analysis.md) for the exact rules and [installation](installation.md) for setup.
