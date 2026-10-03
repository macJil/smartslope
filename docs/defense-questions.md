# Defense questions and practice answers

Keep answers tied to the current `f1` implementation. If a question asks about future work, label it as a proposal.

### What problem does SmartSlope address?

We chose a local information workflow for Barangay Irisan: residents can submit a location-linked ground-condition report, while the site shows weather context and a simple rainfall screening category. We have not completed a community needs survey, so we present this as our project rationale rather than a measured service gap.

### Is SmartSlope an official landslide warning system?

No. It is an academic prototype. Its rainfall categories are not official warning thresholds and have not been validated against local landslide events. People should follow official advisories and local authorities.

### How is risk calculated?

`RiskAnalyzer` compares complete 1-hour, 24-hour and 72-hour rainfall totals with the prototype thresholds. The strongest level reached by any window is returned with an explanation. Missing, invalid or inconsistent totals prevent a current assessment.

### Why use Open-Meteo?

It provides weather model data at requested coordinates without a physical sensor installation. Those values are estimates for a model grid, not device readings at the selected property. We store the source and timestamps and separate forecast from historical rainfall.

### Why does the database have five tables?

Users and locations have their own tables. `events` stores each record's shared ID, location, submitter, type and creation time. A one-to-one `readings` or `reports` row stores fields specific to that type. This reduces empty subtype columns and keeps a shared event ID. It does not satisfy a rubric that specifically requires a sensor table; we must confirm that requirement with the instructor.

### Where are the sensors?

There are no physical sensors or sensor table in this branch. The current source is Open-Meteo. Sensor support would require an agreed schema, device identity, calibration and testing before it could be described as implemented.

### Why keep a report separate from the automatic score?

A resident report is an observation that an administrator reviews. It is not a verified event label or a measurement of rainfall, so the system does not mix it into the automatic category.

### What does “current” mean?

The default app freshness policy is three hours. It compares the provider observation timestamp with the current time. This is a display/data quality rule, not a scientific definition of dangerous rain.

### What if data is missing or Open-Meteo is down?

Missing hours block a new assessment, and the API returns an error if provider refresh fails. Existing readings remain stored and visible as history. Missing data is not converted into low risk.

### What does the susceptibility layer show?

No verified Irisan susceptibility polygons are bundled. Unknown remains unknown. The local hazard lookup code can support reviewed data, but that data is not present in this branch.

### How do you prevent common web attacks?

We use prepared PDO statements, server-side role checks, password hashing, session ID regeneration, CSRF tokens for changes, output escaping, input validation and server access rules. We still need to test the server rules on the actual host, especially because Nginx ignores `.htaccess`.

### How do you know the model is accurate?

We do not claim it is accurate. We have unit tests for the code's threshold behavior, not a scientific validation dataset. The local validation CSV has a header only. Proper validation needs sourced landslide and non-landslide periods and separate false-alarm and missed-event analysis.

### Why does refresh create more than one row for the same hour?

Each successful request is stored as a snapshot with its own retrieval time. The provider's observation hour can repeat. The system therefore uses a stable insertion order for the latest snapshot and does not sum snapshots together.

### What business model do you propose?

For later validation, we propose setup/customization plus an annual support agreement for a local government or disaster-risk office. We have not tested willingness to pay, priced the service, or earned revenue.

### What are the next steps?

Confirm rubric expectations, obtain permissioned local source data, verify map licensing, interview residents and local staff, validate rules on separate event/non-event periods, and cost a pilot. Sensors or area expansion should only follow a clear need and evaluation plan.

## Short terms to explain

- **PDO**: PHP's database interface used here to send parameterized SQL to MySQL.
- **CSRF token**: a session-specific value the server checks on a form submission to reject requests forged from another site.
- **JSON**: a text format the dashboard uses to receive reading and assessment data from PHP.
- **Snapshot**: one saved provider response and its calculated fields at a retrieval time.
- **Prototype threshold**: a demonstration limit used to classify rainfall; it has no validated local warning meaning.
