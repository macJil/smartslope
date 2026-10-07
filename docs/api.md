# JSON API documentation

## `api/readings.php`

Requires an authenticated session for both methods. Responses use `application/json`, `Cache-Control: no-store`, and `X-Content-Type-Options: nosniff`.

### GET saved data

```http
GET /api/readings.php?location_id=1
```

Reads the saved latest reading, assessment, baseline lookup, notices, report counts, up to ten history rows, and server-rendered view fragments. GET does not call Open-Meteo or write to the database.

### POST refresh and save

```http
POST /api/readings.php
Content-Type: application/x-www-form-urlencoded

location_id=1
```

The server fetches and validates current provider data, computes rainfall totals and category, and appends one `events` reading row. Repeated provider timestamps still produce separate snapshots.

### Successful response

The JSON object includes:

- `location`: public location fields only
- `latest`: latest saved reading, with private raw provider payload and adjustment log removed
- `assessment`: category, calculated/current category, status, explanation, provenance and timestamps
- `baseline`: susceptibility lookup result, unknown when a verified source match is unavailable
- `notices`: current computed notice content
- `report_counts`: report workflow counts for the location
- `readings`: latest history rows, with raw provider payload and adjustment log removed
- `view`: escaped HTML fragments used to update the dashboard

Do not use `view` as a general public HTML API; it exists for this application client. JSON fields such as `observed_at` and server timestamps are UTC strings.

### Status codes

| Code | Meaning |
| --- | --- |
| 200 | Read succeeded or refresh was saved. |
| 401 | No signed-in session. |
| 404 | Location is absent or inactive. |
| 405 | Method other than GET/POST. `Allow: GET, POST` is returned. |
| 422 | Missing or invalid positive `location_id`. |
| 503 | Provider, database, or save operation failed; previous rows remain available. |

For an unrecognized rule version, invalid timestamp, stale observation, or incomplete rainfall, the request does not claim a current assessment. Details stay in the PHP log; the response contains a generic explanation.

## `api/address.php`

The optional server-side reverse-geocoding aid is disabled by default. It is used only when `NOMINATIM_ENABLED=1`. It applies input bounds, a timeout but no local cache or request budget, and the UI keeps its coordinate or landmark fallback when the lookup fails. Read [data sources](data-sources.md) and the Nominatim usage policy before enabling it.

POST refresh requires a login session; no CSRF token is sent or checked in this branch.
