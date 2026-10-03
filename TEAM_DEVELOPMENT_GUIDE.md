# Build SmartSlope from an Empty Repo

A simple 9-week plan for four students. The GitHub repository starts empty. Add only the files scheduled for each week; do not upload the finished website as the first commit. Each commit should contain real work that runs or can be reviewed.

## GitHub Setup

1. Student 1 creates a **private empty repository**. Do not check GitHub's options to add a README, `.gitignore`, or license; Week 1 creates the first files.
2. Add the other students and invite the teacher under **Settings → Collaborators**.
3. Use `main` for reviewed work. Each student makes a short weekly branch, commits their assigned files, pushes the branch and opens a PR. A different student reviews before merge.
4. Never commit `.env`, passwords, database backups, real reports, or private CSV files.

Typical weekly commands:

```bash
git switch main
git pull --ff-only origin main
git switch -c feat/week-03-login
# create/change this week's assigned files and test them
git add <this-weeks-files>
git commit -m "feat(auth): add login page"
git push -u origin feat/week-03-login
```

Open a PR to `main`; include a short description and how you tested it. Use the commit messages below as examples and change them to match the work actually completed.

## Weekly Build

### Week 1: Empty Repo and First Login Screen

- **Student 1:** Create the empty private repo, invite the teacher/team, add `.gitignore` and a short `README.md`. Commit: `docs: start SmartSlope repository`.
- **Student 2:** Add the initial `users` table to `database/schema.sql`. Commit: `feat(db): add user account table`.
- **Student 3:** Add a simple login/registration layout in `index.php` and `assets/css/frontend.css`. Commit: `feat(ui): create first login screen`.
- **Student 4:** Add `tests/README.md` with account test cases. Commit: `test: plan login and registration checks`.
- **Progress:** The empty repository now shows the first login page and defines where users will be stored.

### Week 2: Functional User Accounts

- **Student 1:** Add local setup and resident account instructions to `README.md`. Commit: `docs: explain local setup and accounts`.
- **Student 2:** Add `.env.example`, `app/config.php`, `app/bootstrap.php`, and user persistence in `app/repositories.php`. Commit: `feat(users): connect accounts to MySQL`.
- **Student 3:** Complete the forms in `pages/login.php` and auth styling in `assets/css/frontend.css`. Commit: `feat(ui): finish registration and login forms`.
- **Student 4:** Add `app/helpers.php`, `app/auth.php`, `logout.php`, `tests/bootstrap.php`, and `tests/auth.php`. Commit: `feat(auth): validate accounts and sessions`.
- **Progress:** Residents can register, sign in and sign out. No admin pages yet.

### Week 3: Resident Dashboard and Locations

- **Student 1:** Add the resident dashboard flow to `README.md`. Commit: `docs: describe resident dashboard`.
- **Student 2:** Add the `locations` table and location queries in `database/schema.sql` and `app/repositories.php`. Commit: `feat(locations): store resident map points`.
- **Student 3:** Add `assets/map/irisan.geojson`, `assets/js/offline-map.js`, `assets/js/irisan-boundary.js`, and local map tiles. Commit: `feat(map): add Irisan map and boundary`.
- **Student 4:** Add `app/presentation.php`, `pages/dashboard.php`, `dashboard.php`, `actions/save_location.php`, and `tests/map-boundary.test.cjs`. Commit: `feat(dashboard): let residents select locations`.
- **Progress:** Signed-in residents can view the dashboard and select a valid point in Irisan.

### Week 4: Resident Weather and Risk

- **Student 1:** Explain weather data and prototype risk limits in `README.md`. Commit: `docs: explain weather and risk limits`.
- **Student 2:** Add shared `events` and `readings` tables to `database/schema.sql`; implement `app/RiskAnalyzer.php`, `app/assessment.php`, `app/provider.php`, `app/weather.php`, and reading queries in `app/repositories.php`. Commit: `feat(weather): calculate and save readings`.
- **Student 3:** Add refresh/results UI in `assets/js/dashboard.js`, `app/presentation.php`, and `assets/css/frontend.css`. Commit: `feat(ui): show readings and risk status`.
- **Student 4:** Add `api/readings.php`, `tests/risk.php`, `tests/assessment.php`, `tests/weather.php`, and `tests/api-readings.php`. Commit: `feat(api): add protected weather refresh`.
- **Progress:** Residents can refresh weather and see saved readings; incomplete or old data is identified.

### Week 5: Resident Reports

- **Student 1:** Add report instructions to `README.md`. Commit: `docs: explain resident reports`.
- **Student 2:** Add the `reports` table and report persistence/counts in `database/schema.sql`, `app/repositories.php`, and `app/awareness.php`. Commit: `feat(reports): store resident submissions`.
- **Student 3:** Add report location feedback in `assets/js/location-address.js` and `assets/css/frontend.css`. Commit: `feat(ui): show report location feedback`.
- **Student 4:** Add `pages/report.php`, `report.php`, and `tests/reports.php`. Commit: `feat(reports): validate and submit reports`.
- **Progress:** Residents can submit reports linked to their account and location.

### Week 6: Introduce Admin and Review Reports

- **Student 1:** Explain the resident/admin role distinction in `README.md`. Commit: `docs: describe admin access`.
- **Student 2:** Add reviewer/status fields and review queries in `database/schema.sql`, `app/repositories.php`, and `app/awareness.php`. Commit: `feat(admin): store report review status`.
- **Student 3:** Add admin visual styles in `assets/css/frontend.css`. Commit: `feat(ui): style admin report queue`.
- **Student 4:** Build the report queue and protected review actions in `admin.php`; add `tests/admin-workflow.php`. Commit: `feat(admin): let admins review reports`.
- **Progress:** The first admin feature is added only after the resident login, map, readings and report flow exists.

### Week 7: Admin Reading and Location Tools

- **Student 1:** Document admin responsibilities in `README.md`. Commit: `docs: explain admin management`.
- **Student 2:** Add audit/archive support in `app/repositories.php` and complete schema indexes in `database/schema.sql`. Commit: `feat(admin): store reading corrections`.
- **Student 3:** Add reading details in `assets/js/reading-modal.js` and polish admin styles. Commit: `feat(ui): improve admin reading views`.
- **Student 4:** Add `readings.php`, `actions/save_reading.php`, `actions/delete_reading.php`, and `tests/admin-workflow.php` checks. Commit: `feat(admin): manage readings and locations`.
- **Progress:** Admin can review reports, adjust readings with a reason, and manage saved locations.

### Week 8: Exports, Address Help and Methodology

- **Student 1:** Finish setup/source/privacy/limitation notes in `README.md`. Commit: `docs: finish project explanation`.
- **Student 2:** Add `app/csv.php`, `api/address.php`, and validated location import/export/address lookup. Commit: `feat(data): add CSV and address services`.
- **Student 3:** Add address fallback UI in `assets/js/location-address.js` and responsive polish in `assets/css/frontend.css`. Commit: `feat(ui): add address fallback and mobile polish`.
- **Student 4:** Add `methodology.php` and `tests/csv.php`. Commit: `feat(methodology): explain sources and limits`.
- **Progress:** User and admin workflows are complete with exports, address fallback and source explanations.

### Week 9: Migration, Integration and Submission

- **Student 1:** Finalize `README.md` and `tests/README.md`; confirm teacher access. Commit: `docs: prepare final submission`.
- **Student 2:** Finish/review `database/migrate-awareness.php` and `app/maintenance.php`; rehearse migration only on a disposable database. Commit: `fix(db): verify normalized migration`.
- **Student 3:** Fix tested integration issues in `assets/css/`, `assets/js/`, and map assets; check licenses. Commit: `fix(ui): finish tested interface`.
- **Student 4:** Add/run `tests/migration.php` and `tests/integration.php`; run tests on the release commit. Commit: `test: verify final workflows`.
- **Progress:** Tag the tested commit. Demo resident registration, map, weather and report first, then admin review and project limitations.
