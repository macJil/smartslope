# Verification record

Base: s4 f8e7c5002814afe8d8b416f8a55aafe8e2c9733d, checked against GitHub during implementation.

Completed during implementation:
- PHP 8.3 lint: all 25 PHP files passed after the main backend changes.
- 25 risk checks: thresholds, invalid/missing values, highest-category selection, explanations.
- 11 assessment checks: current/outdated/future timestamps, missing data, overrides.
- Weather tests: complete intervals, missing/duplicate hours, invalid values, complete forecasts, units, provider freshness.
- Disposable MySQL 8.0.46 integration: original schema import, resident account creation/authentication, readings, freshness, risk override, report linkage/review, CSV and archival/deletion. Integration changes were rolled back.
- One live Open-Meteo request passed validation and saved a reading in the disposable database. This confirms one successful provider response, not future provider availability or landslide accuracy.
- Node syntax and polygon boundary tests.

Not completed:
- End-to-end HTTP forms/API test: the first attempt lacked an HTTP client dependency; the standard-library rerun was blocked when automatic approval review reached its usage limit. That was a review-service failure, not a finding that the operation was unsafe.
- Visual browser testing, actual Herd/Nginx access rules, and XAMPP/Apache configuration.

The temporary PHP runtime was no longer available when packaging resumed. The original lint/unit/integration results above describe completed checks; they are not claims of a fresh final runtime test. Run the included PHP tests and local checklist on the final files before marking the frontend handoff accepted.

No live user database or GitHub files were modified. See backend-test-checklist.md for the remaining acceptance steps. The rule set remains an uncalibrated prototype.


## su1 awareness upgrade validation

Baseline a8ae411. PHP lint and existing risk/weather/assessment/presentation/map
checks passed, plus 22 awareness checks. A disposable MariaDB 10.11 database
imported the original schema, ran the additive migration twice, and passed 11
integration checks for input retention, repeated timestamps, adjustments, report
review/UTC occurrence, export, archival and invalid-window behavior.
A headless browser passed authenticated dashboard/AJAX, JSON 401/403, typed report
submission, pending queue and methodology flows with no page errors. Desktop
1280px and mobile 390px screenshots were inspected; no whole-page horizontal
overflow was detected. Weather used explicitly synthetic TEST/DEMO provider
fixtures in disposable state: this is not a live-provider or accuracy test.
Herd/MySQL 8 and XAMPP acceptance still need local verification. MGB coverage,
source edition and reuse terms remain unresolved; no hazard subset is bundled.
