# SmartSlope project guide

**SmartSlope is a student prototype for rainfall screening and community ground-condition reporting in Barangay Irisan, Baguio City.** It combines saved weather-provider snapshots, explainable rainfall categories, an offline map, and an administrator-reviewed report queue.

## What works in this branch

- Residents can register, sign in, select a point within the Irisan boundary, refresh weather, inspect saved readings, and send a ground-condition report.
- Administrators can review reports, manage locations and saved readings, adjust a reading's risk category with a reason, archive readings, and export data as CSV.
- The weather refresh calls Open-Meteo from PHP, validates the response and timestamps, derives rainfall windows, applies the current prototype rules, and saves a snapshot.
- A session-protected JSON endpoint returns readings, their assessment and notices. The dashboard refresh uses jQuery AJAX.
- Five MySQL tables support the current application: `users`, `locations`, `events`, `readings`, and `reports`. `events.type` distinguishes shared records; one-to-one detail tables hold readings and reports.

The branch does not contain physical sensors, an official alert feed, AI/ML, an independently validated landslide prediction model, or verified Irisan susceptibility polygons. “Low” is not a safety statement. See [analysis](docs/analysis.md) and [data sources](docs/data-sources.md).

## Scope and audience

The prototype is limited to one study area. Residents are report submitters and viewers; an administrator manages locations, reading corrections, and the report workflow. The likely institutional buyer and any future revenue approach belong to a proposed Startup model, not to the current website. See [Startup notes](docs/startup.md).

## Technology

PHP 8.1 or later, PDO with MySQL, locally stored Bootstrap 5, Leaflet and map tiles, jQuery, browser JavaScript, and GeoJSON. No build step or JavaScript framework is required. `.env` supplies database and deployment settings.

## Where to start

- [README](README.md): current branch summary and quick setup
- [Installation guide](docs/installation.md): new and existing database setup, Herd and XAMPP
- [User manual](docs/user-manual.md): resident and administrator tasks
- [Database and ERD](docs/database.md): tables, links, migration and limitations
- [Architecture](docs/architecture.md): request and data flow
- [API](docs/api.md): authentication, methods, response shape and status codes
- [Data sources](docs/data-sources.md): provider, map, optional geocoding and provenance
- [Analysis rules](docs/analysis.md): thresholds, freshness and interpretation
- [Security](docs/security.md): protections and deployment checks
- [Testing](docs/testing.md): automated checks, reported manual tests and remaining gates
- [Startup concept](docs/startup.md): proposed business case and required pitch content
- [Presentation and demo](docs/presentation-and-demo.md): slide plan and repeatable sequence
- [Defense questions](docs/defense-questions.md): answers the team can rehearse

## Academic fit

The current feature set gives the team concrete examples for PHP forms and sessions, PDO CRUD, object-oriented code (`RiskAnalyzer`), AJAX, JSON, and security. The relational design demonstrates primary/foreign keys and joined records while retaining a deliberately small schema. Course alignment does not establish scientific validity or compliance with a separate requirement for a dedicated sensor table. See [testing and limitations](docs/testing.md).

## Current snapshot

Documentation is written against branch `f1`, application commit `ec7003130d1fd483dce3c167adbd07f662510407` (“done”), checked 2026-10-03. Record a new commit SHA and rerun checks for the final defense build.
