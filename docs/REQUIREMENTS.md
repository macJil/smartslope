# SmartSlope academic requirements and acceptance

The supplied WEBSYS1 syllabus covers PHP/MySQL, OOP, jQuery/AJAX, JSON/API and security. IMDBSE2 requires a functional web frontend with backend database CRUD and evaluates project progress, team contribution and presentation. The Startup guidelines request a concept note, deck, market/business model, logo and stage-specific application materials. These documents are course/guideline materials, not a complete teacher scoring sheet.

| Requirement | Repository evidence | Acceptance to demonstrate |
|---|---|---|
| PHP/MySQL, OOP and CRUD | PDO repositories; admin location edits, inline saved location/risk corrections, report status | Fresh import and existing migration in real MySQL; add/edit/remove/restore locations; edit/remove saved API readings |
| jQuery and AJAX | Local jQuery, `assets/js/app.js` | Saved Irisan readings load at dashboard opening; map selection shows that point's risk and readings; Refresh requests new weather without a page reload |
| API and JSON | Open-Meteo client, `api/weather.php`, `api/readings.php` | Valid provider response and no-data/error behavior |
| Security | Hashed passwords, server roles, session ID regeneration, CSRF, escaped output, prepared queries | Anonymous/resident denied admin changes; invalid CSRF rejected; same behavior under Herd and `/smartslope` |
| Relational design and teacher's source table | `users`,`barangays`,`locations`,`sensors`,`weather_observations`,`weather_fetches`,`readings`,`alerts`,`reports` | Foreign keys, exact clicked-coordinate locations, deduplicated provider rows, an append-only fetch log, stored-observation rainfall calculation, linked alert and accurate UTC/PHT times |
| Core landslide prototype | Sourced baseline hazard + provisional rainfall status + resident observations | Verified source/coordinates, documented thresholds and freshness states; no claim of an official warning |
| Startup output | Prototype and documented business-model hypothesis | Team finishes actual deck, concept note, logo, financial assumptions, progress/contribution, peer evaluation and presentation; do not invent evidence |

The preferred business-model hypothesis is paid barangay/LGU setup and maintenance with free resident access. Payments are not part of the website. The supplied Startup competition text distinguishes prototypes from functioning MVP entries; check the appropriate category before an external competition submission.

## MySQL and HTTP acceptance steps

1. Back up existing DB; import a fresh `db.sql` into a disposable database and run staged migration on a copy of the old DB. Confirm user identities and old observations are preserved.
2. Register with contact number; duplicate number fails; role submitted by client does not create admin; correct/wrong passwords behave; login regenerates session ID.
3. Create/modify sourced Irisan location; non-Irisan locations do not appear in report queries; archive hides it but retains references.
4. Refresh selected location: one virtual source; current + hourly observations linked by sensor; totals from saved contiguous hours; summary and alert linked in same transaction. Repeat refresh: rows and alert do not duplicate.
5. Test incomplete history, stale/future time, provider failure and database write failure. Never display a fresh risk based on missing totals or a failed database write.
6. Click two points inside Irisan as a resident and as an administrator. Each exact point is reused on repeat clicks, its provider response creates a saved `weather_fetches` log row, and the risk panel/list uses that location. In the admin dashboard, edit only a saved log entry’s location and risk at fetch, archive it with Delete, and confirm the CSV reflects active rows. A later API refresh creates a new log entry. Ensure no manual Add form or separate Manage readings page.
7. Submit report; admin reviews/resolves. Test CSRF and authorization, CSV formula escaping, XSS and Herd/XAMPP routes.
8. Present relational design, contribution history, peer evaluation, concept note and business model with actual supporting evidence.

`node --test tests/resident-weather.test.cjs` checks JavaScript with mocked response and DOM. PHP SQLite tests are partial. Neither proves MySQL-specific migrations and HTTPS retrieval.
