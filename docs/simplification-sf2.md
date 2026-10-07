# sf2 procedural simplification

Base: `8c92d4d36624019a1d30091b13123ad5b0b60812` on `sf2`.

Pages, actions, APIs and CLI scripts include their PHP dependencies directly with
`require_once __DIR__`. There is no bootstrap loader. `app/config.php` retains
settings, environment loading, URL base detection and one shared PDO connection.
`__DIR__` resolves files, while `url()` preserves root and subdirectory installs.

The implementation follows the reference project's direct includes, session
login and ordinary function calls. It does not copy its UI or mix mysqli/PDO.
`RiskAnalyzer` stays as the small OOP example; other application modules use
functions, loops and conditionals. The database schema, rainfall thresholds,
map assets, CSS and page layouts are retained.

## Deliberate behavior changes

- Login sessions and server-side roles remain. CSRF tokens and their rejection
  checks are removed. A login session alone does not prevent forged requests.
- Password hashes, session ID regeneration, cookie settings, prepared SQL and
  HTML escaping remain. Registration/report validation is reduced to basic
  required fields and column limits. Database uniqueness still applies.
- Location exports use a seven-column header and editable rows. Signing keys,
  signature verification, formula escaping and duplicate-row tracking are gone.
  Old signed exports are not the new format: download a fresh Locations CSV.
  Spreadsheet applications may interpret formula-like text as formulas.
- Locations are saved with a single PDO insert/update statement per row. The
  existing unique coordinate key identifies the stored location, preserving
  its ID and history. Repeated coordinates are processed in file order; the
  last row wins. The CSV ID column is informational. Changing coordinates
  creates another location instead of moving the original location's history.
- Imports and bulk actions do not use transactions or rollback. A later error
  can leave earlier changes saved. Reading edits also no longer lock the row;
  simultaneous edits can overwrite each other's audit-history updates.
- Weather and address requests no longer use local caches, locks or rate
  budgets. Provider limits still apply externally, so repeated refreshes can
  fail or be throttled by the service. Optional Nominatim remains off by default;
  enabling it requires a deployment that meets its service policy.

Basic format, coordinate, database-size and weather-value checks remain because
removing them would allow invalid map points, SQL errors or misleading rainfall
results. The existing server access rules remain. These changes preserve normal
page workflows, but are not an assertion of identical security or error behavior.

## Applying

From a clean checkout of the exact base above:

```sh
git switch sf2
git rev-parse HEAD
git apply --check smartslope-sf2-simplify.patch
git apply smartslope-sf2-simplify.patch
```

The patch does not contain your `.env` or require a schema reset. Never reimport
`schema.sql` over existing project data to apply this code-only change.

## Testing

Run the PHP tests listed in `docs/testing.md`, then test the site on the actual
Herd/XAMPP installation. Existing historical docs describe older behavior;
this document and the updated security/API/testing guides describe this patch.

## Verification performed for this patch

- Clean base: six PHP suites and 16 database integration checks passed.
- Simplified code: PHP syntax checks; six PHP suites (risk, weather, assessment,
  presentation, awareness and CSV); JavaScript syntax and two map checks passed.
- Disposable MariaDB: 16 existing workflow checks, five CSV import checks and
  seven fixture-provider refresh checks passed.
- PHP development server: 51 HTTP/HTML checks passed, including registration,
  session login/logout, role restrictions, reports, review/resolve, reading edits,
  bulk actions, CSV downloads and uploads. Baseline page HTML matched after
  excluding removed hidden tokens, asset version timestamps and CSV help copy.
- Weather calculation and database persistence passed with fixture data. The
  full live Open-Meteo request failed in this environment, so successful live
  refresh and Nominatim lookup are not certified by these checks.
- Desktop/mobile browser rendering and the user's Herd/XAMPP server settings
  were not exercised here. CSS, map tiles and other visual assets are unchanged.
