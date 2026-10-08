# Rainfall analysis and interpretation

The `RiskAnalyzer` section in `functions.php` contains the explainable rule class (`prototype-1`). It reads saved totals for the preceding 1, 24 and 72 hours. The strongest threshold met by any one window determines the category.

| Category | 1 hour | 24 hours | 72 hours |
| --- | ---: | ---: | ---: |
| High | 50 mm | 100 mm | 150 mm |
| Medium | 25 mm | 50 mm | 100 mm |
| Normal | 10 mm | 25 mm | 50 mm |
| Low | Below every listed threshold | | |

For example, 27 mm in the 1-hour window reaches `medium` even if the longer windows remain below their medium values. A complete, finite, nonnegative value is required for all three historical windows. Missing hours or invalid values mean there is no current category. The application checks for consistent totals (1h ≤ 24h ≤ 72h). It never silently converts missing rainfall to low.

## Data timing

Hourly precipitation is treated as the preceding hour labeled by interval end. Totals end at the last completed UTC hour; the forecast is computed separately across the next 24 hours. Historical totals and forecast rainfall are not added together. `observed_at` is the provider valid time, while `created_at` records retrieval/save time. Interface times are shown in Philippine time.

Freshness defaults to 10,800 seconds. Older observations remain visible as last known history, but they cannot be presented as current. Future timestamps beyond a five-minute tolerance and unrecognized rule versions cannot support a current assessment. Freshness values are application policies, not scientific thresholds.

## Susceptibility and reports

Susceptibility is stored/displayed separately from short-term rainfall. A point without reviewed evidence remains unknown. The report queue captures resident observations for administrator review; it does not automatically alter the rainfall score or prove a landslide occurred.

## Scientific limit

The numerical limits are demonstration rules. They have not been calibrated against verified Irisan landslide and non-landslide periods and are not probabilities, official warning thresholds, or validated predictions. “Low” and “Normal” cannot be interpreted as safety. Users should follow current official advisories and local authorities.

No accuracy, sensitivity, specificity, false-alarm rate, missed-event rate, or advance warning claim is supported by the current repository. The validation CSV has headings only. See [data sources](data-sources.md) and `docs/risk-rules.md` for the detailed implementation contract.
