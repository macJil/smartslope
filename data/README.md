# Local susceptibility evidence

No verified Irisan hazard polygons are bundled. Unknown remains unknown.

After documenting edition, specific map scale, reuse terms and Irisan coverage,
prepare an EPSG:4326 FeatureCollection and run:

```bash
php scripts/import-susceptibility.php /absolute/path/to/reviewed.geojson
```

The top-level `metadata` object must have `reviewed: true`, `crs: "EPSG:4326"`,
and nonempty strings `source_url` (official mgb.gov.ph host), `edition`, `scale`,
`reuse_terms`, `coverage_review`, `reviewed_by`, `reviewed_at`.
Every feature must retain original `OBJECTID` and `LndslideSusc` properties:
VHL, HL, ML, LL, DF. Polygon rings must be closed; MultiPolygon is supported;
holes are excluded; boundary/conflicting classes stay unknown. The importer
prints a checksum; record it with the reviewed source evidence.

These metadata are a human provenance attestation, not automatic scientific
validation. Do not fill unknown edition/license fields with guesses. Do not
install synthetic test fixtures. Existing locations.susceptibility values alone
are not treated as verified. Keep reviewed source evidence with the project.

The official public service was inspected, but direct retrieval timed out in
this environment. Full Irisan coverage, edition and redistribution permission
remain unresolved. No fabricated data or classes are substituted.
