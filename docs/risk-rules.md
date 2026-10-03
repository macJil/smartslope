# SmartSlope landslide-alert prototype: rules and data contract

Rule version: `prototype-1`. These rules demonstrate rainfall screening; they have not been calibrated against Irisan landslide events. They are not official warnings, probabilities, or an assurance of safety. Physical sensors, AI and wider geographic coverage are later phases.

## Classification

The highest threshold reached by any window wins. All three rainfall totals must be valid, finite and nonnegative.

| Level | 1h mm | 24h mm | 72h mm |
|---|---:|---:|---:|
| high | 50 | 100 | 150 |
| medium | 25 | 50 | 100 |
| normal | 10 | 25 | 50 |
| low | Below every threshold | | |

Normal is the second category. Low/normal do not guarantee slope safety. The explanation identifies the window(s) triggering the winning level.

### Reading the assessment

For a low reading, the analyzer may explain: “Rainfall is below all prototype thresholds. Low does not mean the slope is safe.” Each higher category is triggered by one or more rainfall windows in the table above. The explanation remains available in the JSON assessment, while the dashboard shows the category, data status and observed rainfall.

Baseline susceptibility is separate from the current rainfall category. If a location shows **Unknown**, no baseline susceptibility is recorded for that point. A recorded baseline still needs its own verified source; the rainfall category does not validate it.

SmartSlope is an academic prototype. Its categories are not official warnings or validated landslide predictions. A low value is never a claim that a location is safe.

## Weather semantics

Open-Meteo model estimates are not measurements from a physical device at the clicked point. Hourly precipitation represents the preceding hour. Totals end at the last complete UTC hour at or before the current provider timestamp. Forecast totals cover the following 24 complete hourly intervals, starting from that same whole-hour boundary; they are not added to past totals. For example, at 06:15 UTC historical totals end at 06:00 and forecast accumulation spans 06:00 to 06:00 the following day.

The saved 24-hour forecast outlook contains forecast rainfall and the maximum hourly rain chance. A reading might show **5.30 mm** and **100%**, for example; those numbers describe that saved forecast, not a landslide probability. The period starts at the last whole hour of the weather request. Forecast and modeled soil moisture are context; the current category uses only the saved 1h, 24h and 72h rainfall history. The outlook belongs to the saved reading and may become outdated. Secondary weather values remain available through the weather details dialog.

All historical windows require consecutive, distinct timestamps and valid precipitation. A missing historical window blocks saving a new assessed snapshot. An incomplete forecast becomes null; it does not invalidate otherwise complete history. Missing/invalid optional soil moisture becomes null. Current weather units and numeric ranges are checked. Units are mm, degrees Celsius, km/h, percent and volumetric water content m3/m3. Soil moisture is not a saturation percentage.

Each successful click/refresh appends a snapshot. Repeated provider observation times are expected. Never sum saved accumulated snapshots. `observed_at` is the provider timestamp; `created_at` is database retrieval/save time in UTC. Views display Philippine time. The legacy `stale` database column is retained for schema compatibility but is ignored as a freshness clock.

## Assessment and freshness

- `category`: valid stored category, including last-known outdated categories.
- `calculated_category`: baseline recomputed from saved rainfall under prototype-1.
- `current_category`: null unless the reading is complete and current; maps use this field.
- `data_status`: current, outdated, incomplete, unavailable.
- `adjusted`: stored category differs from calculated category.
- `reasons`, `rule_version`, `susceptibility`, `observed_at`, `retrieved_at`: context.

Default maximum age is 10800 seconds; configure `READING_MAX_AGE_SECONDS`. Future timestamps beyond 300 seconds are invalid. These are application data policies, not scientific warning thresholds. Status is recalculated on every page/API request; a page left open must be reloaded/refreshed to update its display.

Admin edits remain risk-only and require a reason. New edits append actor, UTC time,
previous/new category and reason to adjustment_log; legacy edits have no reconstructed
audit history. New snapshots retain rule_version, hourly input JSON and window end.
Do not change threshold versions silently: evaluate revised rules separately and
plan any reassessment of existing records. Unknown stored rule versions cannot
produce a current assessment.

Susceptibility remains separate and unknown without verified local evidence. Forecasts, modeled soil moisture and ground reports do not secretly change the numerical category. Pending/reviewed/resolved report statuses do not establish scientific verification. Preserve resident identity when reviewing reports.

## Further evidence

Prioritize verified MGB susceptibility coverage and historical local landslide/non-event rainfall data before selecting new thresholds or weights. Record source, date, scale, license and coverage before importing a hazard dataset. No invented susceptibility layer is included.

References:
- https://open-meteo.com/en/docs
- https://www.usgs.gov/publications/developing-hydro-meteorological-thresholds-shallow-landslide-initiation-and-early
- https://controlmap.mgb.gov.ph/arcgis/rest/services/GeospatialDataInventory/GDI_Detailed_Rain_induced_Landslide_Susceptibility/FeatureServer


## Awareness upgrade contract

New readings retain normalized provider input JSON, provider retrieval time,
returned grid metadata, window endpoint and rule version. New edits retain an
append-only adjustment history; previous edits cannot be reconstructed. The
existing three tables remain but events has additive metadata columns.
Saved-window consistency is enforced during assessment. Prototype notices use
calculated rainfall; administrator category and community reports stay separate.
Only provenance-reviewed local MGB polygons provide the VERIFIED baseline;
legacy locations.susceptibility alone is not evidence. No subset is bundled.
Optional context with absent/unexpected unit metadata becomes unavailable.
Nominatim is opt-in and server-throttled; weather caching reuses valid timestamps.
See docs/awareness-upgrade.md for installation and acceptance checks.
