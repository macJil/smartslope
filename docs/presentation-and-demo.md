# Presentation and demonstration guide

Use this guide for an 8 to 10 minute class presentation. Replace the founder placeholders with the real team roster. Do not add fabricated adoption, accuracy, investment or partnership claims.

## Slide sequence

1. **SmartSlope: Irisan prototype**. Introduce the team and the project scope.
2. **The problem we chose**. Explain the need for location-linked community observations and weather context. Label this as the team's problem framing, not measured evidence.
3. **Who it serves and SDG fit**. Residents and local disaster-risk staff; proposed links to SDG 11 and 13.
4. **What the prototype does**. Map point, weather refresh, rainfall screen, history, community report, admin review.
5. **How data moves**. User selects a point; PHP requests Open-Meteo; validated values are saved; rules create a category; the dashboard displays it.
6. **Database design**. Show the five tables: `users`, `locations`, shared `events`, and linked `readings`/`reports` detail tables. Explain the common event ID and `type` field.
7. **Risk categories and limits**. Explain the rainfall windows and state that thresholds are uncalibrated and not official alerts.
8. **Security and testing**. Summarize sessions/roles, CSRF, PDO, output escaping and automated/local checks.
9. **Progress, business proposal and ask**. Working prototype; no traction or revenue claim. Ask for a pilot adviser, data access guidance and mentorship.
10. **Next validation steps**. Verify local datasets and terms, test with users, evaluate false alarms/missed events, then decide whether sensors or a wider area are justified.

## Demo sequence (about 4 minutes)

Prepare one resident account and one rotated administrator account. Use a disposable database and non-personal report text.

1. Open the dashboard and point out that the map/boundary/tiles are local.
2. Select the supplied Irisan pilot point. Show the latest reading state and observation/retrieval times.
3. Press Refresh once. Explain that the browser calls the JSON endpoint and the server saves a snapshot. Show the category explanation and history.
4. Submit a typed ground-condition report with a non-sensitive test message.
5. Sign in as admin. Find the pending report and mark it reviewed.
6. Open a reading in the admin interface. Change only its risk category with a reason, then point out the separate calculated baseline and audit note.
7. Export a CSV and show the headers without opening or projecting personal data.
8. Close by stating limits: no physical sensors, no official alert integration, no verified hazard subset, no calibrated predictor.

## Demo contingencies

- If Open-Meteo is unavailable, show previously saved reading history and explain that failed refresh preserves it. Do not pretend an old record is live.
- If map selection fails, use the seeded Irisan pilot point. Do not use a point outside the polygon.
- If database state is dirty, restore the prepared disposable copy before the demo rather than deleting real records on stage.
- Never include real resident contacts in the demo database, screenshots or exported files.

## Team speaking roles

Use actual names and assign one speaker to the problem/Startup framing, one to the live workflow, and one to the database/security/testing and limitations. Everyone should be able to explain the risk rules and limitations without reading from this document.
