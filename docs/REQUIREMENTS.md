# Requirements matrix and verification

Sources inspected: WEBSYS1 Syllabus, supplied pages 5–7; IMDBSE2 RELATIONAL ALGEBRA PRELIMS, “What to expect / Final project”; Startup.pdf, eight supplied screenshot pages. These files are course coverage/project guidance and competition guidelines, not a complete point-by-point instructor marking rubric. Full grading compliance cannot be certified from them alone.

Only work needed for the requirements is listed. No optional product features are added to fill priority categories.

| Source requirement | Minimum implementation/deliverable | Priority | Evidence / remaining work |
|---|---|---|---|
| SmartSlope concept | One-area location selection, sourced weather readings, explainable rainfall indicator and timestamps | MUST HAVE | Resident dashboard, weather API, RiskAnalyzer; thresholds still require validation |
| WEBSYS1 PHP/MySQL and OOP | Small PHP classes with PDO prepared statements | MUST HAVE | app/Database.php and repositories |
| WEBSYS1 jQuery and AJAX | Refresh selected-location readings asynchronously | MUST HAVE | Actual local jQuery 3.7.1 and assets/js/app.js |
| WEBSYS1 REST API/JSON integration | External provider request and local JSON endpoints | MUST HAVE | api/weather.php, api/readings.php; live provider/MySQL acceptance pending |
| WEBSYS1 secure coding | Hashed passwords, server roles, CSRF, escaped output, validated forms and session protection | MUST HAVE | Authentication, bootstrap, helpers and role-gated actions; HTTP acceptance pending |
| IMDBSE2 functional frontend and backend CRUD | Locations and readings create/read/update/archive; resident reports and admin review | MUST HAVE | admin/ and resident/; SQLite contract checks; instructor must confirm archival meets deletion criterion if physical DELETE is specifically required |
| IMDBSE2 relational database | Related users, barangays, locations, readings, reports, provider observations; keys and joins | MUST HAVE | db.sql and repositories; MySQL schema/migration acceptance pending |
| IMDBSE2 progress/contribution and final evaluation | Git progress, contribution log, peer evaluation and final presentation | MUST HAVE | Local change history; actual team contribution/peer evaluation must be completed by the team |
| Startup software concept/prototype | Demonstrable user problem, proposed solution and target users | MUST HAVE | Demo flow below; no monetization implementation needed |
| Startup presentation deck | Problem, SDG, market, business model, solution, features, funding asks, progress, founders/rationale/achievements | MUST HAVE | Team must provide/complete deck; do not invent traction, funding, achievements or founder details |
| Startup concept note | Summary, background, solution, objectives, beneficiaries, value proposition, business model, market analysis, operations and finances | MUST HAVE | Team must complete concept note and evidence; proposed service model below is a hypothesis |
| Startup application materials | PNG logo, registration; school clearance and NDA at applicable competition stages | MUST HAVE (if entering competition) | Submission deliverables outside application code |

The syllabus covers both PDO and MySQLi; it does not say the final application must duplicate its data layer in both. PDO supplies the application's database integration. Likewise, course coverage of file upload is not evidence that this particular application must invent an upload feature; CSV download already demonstrates a relevant file operation. Verify additional teacher-specific instructions separately.

## Final academic MVP boundary

One Irisan PHP/MySQL website: resident/admin accounts, managed locations, provider weather ingestion and storage, sourced manual readings, transparent rainfall status, timestamps and refresh, resident reports and admin review, CRUD with archival, CSV export, OOP/PDO, jQuery/AJAX/JSON, security and documented verification. Startup business and presentation evidence accompany the website. No sensors, payments/subscriptions, expansion, AI/ML or map dependency.

The requested contact-number conversion and alerts/provider-table schema revision are not introduced by this structural refactor. They require the database-design deliverables and migration described in the database chat. Soil moisture already appears on the website and is retained only as provider context.

## Startup interpretation

The provided guideline page 4 accepts ideation/prototyping entries and explicitly excludes MVP-stage startups in its footnote. “Academic MVP” is the course project's working label; it is not a claim of competition eligibility. Confirm with the instructor/organizer how a functioning academic prototype should be classified before applying. IoT/AI are examples of technology categories, not required additions to this software prototype.

A possible business-model hypothesis is paid implementation, onboarding and maintenance for a barangay/LGU, with residents using the dashboard without charge. Describe this in the deck/concept note; do not build billing or subscriptions. Validate buyer interest, operating costs and pricing through actual interviews/research. No customer validation or revenue is claimed here.

## Minimum demonstration and acceptance record

1. Fresh MySQL import succeeds; an existing database is backed up and checked against the supplied migration scripts without data loss.
2. Registration produces only a resident role even when a role is supplied manually. Correct login succeeds; wrong login fails. Login changes the session ID.
3. Anonymous/resident requests cannot perform admin mutations; absent/invalid CSRF is rejected; logout requires POST and CSRF.
4. Add/edit a verified Irisan location. Archive excludes it from resident selection and readings without deleting history; restore returns it.
5. Add a sourced reading with complete rainfall totals; edit it and confirm the risk recalculates; archive/restore and download CSV. Confirm spreadsheet text cannot become a formula.
6. Resident selects a location, refreshes weather and sees provider observation time and fetch time in PHT. Confirm current/hourly rows persist, repeated refresh does not duplicate observations, and a new provider hour saves a new summary.
7. Missing coordinates, incomplete rainfall, provider errors and database write failures produce explicit unavailable/warning states, never fabricated low risk.
8. Resident submits a report; admin reviews/resolves it; related rows and reviewer metadata remain intact.
9. Test login, registration, links, API calls and logout both at a Herd domain root and XAMPP `/landslide`. Test mobile-width usability.
10. Record team member contributions and prepare the required presentation, concept note and evaluation evidence.

Do not mark these end-to-end steps passed solely from syntax checks or mocked/SQLite tests.
