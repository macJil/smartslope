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

## Weather semantics

Open-Meteo model estimates are not measurements from a physical device at the clicked point. Hourly precipitation represents the preceding hour. Totals end at the last complete UTC hour at or before the current provider timestamp. Forecast totals cover the following 24 complete hourly intervals, starting from that same whole-hour boundary; they are not added to past totals. For example, at 06:15 UTC historical totals end at 06:00 and forecast accumulation spans 06:00 to 06:00 the following day.

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

Admin edits remain risk-only. A mismatch is labelled administrator-adjusted. The unchanged database cannot show who edited the reading, when, or detect an edit that equals the calculated category. Do not change threshold versions silently: stored records have no rule-version column. Future rule changes require a migration/versioning plan.

Susceptibility remains separate and unknown without verified local evidence. Forecasts, modeled soil moisture and ground reports do not secretly change the numerical category. Pending/reviewed/resolved report statuses do not establish scientific verification. Preserve resident identity when reviewing reports.

## Further evidence

Prioritize verified MGB susceptibility coverage and historical local landslide/non-event rainfall data before selecting new thresholds or weights. Record source, date, scale, license and coverage before importing a hazard dataset. No invented susceptibility layer is included.

References:
- https://open-meteo.com/en/docs
- https://www.usgs.gov/publications/developing-hydro-meteorological-thresholds-shallow-landslide-initiation-and-early
- https://controlmap.mgb.gov.ph/arcgis/rest/services/GeospatialDataInventory/GDI_Detailed_Rain_induced_Landslide_Susceptibility/FeatureServer
