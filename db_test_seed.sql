-- LOCAL TEST DATA ONLY. Do not run this file on a public or production database.
-- The active location is clearly marked TEST ONLY so the report form can be tested.
USE smartslope_mvp;

INSERT INTO locations (
    barangay_id,
    location_name,
    purok_zone,
    susceptibility_class,
    is_active
)
SELECT
    barangay_id,
    'TEST ONLY - report flow location',
    'LOCAL TEST',
    'unknown',
    1
FROM barangays
WHERE barangay_name = 'Barangay Irisan'
  AND city_name = 'Baguio City'
ON DUPLICATE KEY UPDATE is_active = 1;

-- Synthetic display data for testing the resident reading panel and JSON/AJAX flow.
INSERT INTO readings (
    location_id,
    rainfall_1h_mm,
    rainfall_24h_mm,
    rainfall_72h_mm,
    risk_level,
    source_name,
    observed_at
)
SELECT
    l.location_id,
    12.50,
    30.00,
    60.00,
    'normal',
    'TEST ONLY - synthetic sample',
    '2026-09-29 00:00:00'
FROM locations AS l
INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
WHERE l.location_name = 'TEST ONLY - report flow location'
  AND l.purok_zone = 'LOCAL TEST'
  AND b.barangay_name = 'Barangay Irisan'
  AND b.city_name = 'Baguio City'
  AND NOT EXISTS (
      SELECT 1
      FROM readings AS existing_reading
      WHERE existing_reading.location_id = l.location_id
        AND existing_reading.observed_at = '2026-09-29 00:00:00'
        AND existing_reading.source_name = 'TEST ONLY - synthetic sample'
  );
