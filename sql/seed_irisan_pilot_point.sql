-- Run once on an existing smartslope_mvp installation after backing up.
-- Adds one approximate pilot marker only when no active map location exists.
-- This point is not an official hazard classification or a sensor measurement.
USE smartslope_mvp;

INSERT INTO locations (barangay_id, location_name, purok_zone, latitude, longitude, susceptibility_class)
SELECT b.barangay_id, 'Irisan pilot point', 'Pilot', 16.421000, 120.559500, 'unknown'
FROM barangays AS b
WHERE b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
  AND b.is_active = 1
  AND NOT EXISTS (
      SELECT 1 FROM locations AS existing
      WHERE existing.barangay_id = b.barangay_id AND existing.is_active = 1
        AND existing.latitude BETWEEN 16.407 AND 16.435
        AND existing.longitude BETWEEN 120.543 AND 120.576
  )
  AND NOT EXISTS (
      SELECT 1 FROM locations AS existing
      WHERE existing.barangay_id = b.barangay_id
        AND existing.location_name = 'Irisan pilot point' AND existing.purok_zone = 'Pilot'
  );
