-- Upgrade an existing database created by smartslope main 389a0b3.
-- Back up first. Run once after verifying preflight numeric ranges below.
-- DDL auto-commits in MySQL. Keep existing email values and add genuine
-- contact numbers for all accounts; both channels remain available.
USE smartslope_mvp;

-- Preflight: run and inspect these checks before migration. The guard below also stops
-- automatically if values do not fit the target types.
SELECT reading_id FROM readings WHERE reading_id > 4294967295;
SELECT report_id FROM reports WHERE report_id > 4294967295;
SELECT observation_id FROM weather_observations WHERE observation_id > 4294967295;
SELECT observation_id FROM weather_observations WHERE temperature_2m NOT BETWEEN -999.99 AND 999.99
 OR precipitation NOT BETWEEN 0 AND 99999.99 OR pressure_msl NOT BETWEEN 0 AND 99999.99;

DELIMITER //
CREATE PROCEDURE smartslope_guard_types()
BEGIN
    IF EXISTS(SELECT 1 FROM readings WHERE reading_id>4294967295)
      OR EXISTS(SELECT 1 FROM reports WHERE report_id>4294967295)
      OR EXISTS(SELECT 1 FROM weather_observations WHERE observation_id>4294967295)
      OR EXISTS(SELECT 1 FROM weather_observations WHERE
         temperature_2m NOT BETWEEN -999.99 AND 999.99
         OR apparent_temperature NOT BETWEEN -999.99 AND 999.99
         OR precipitation NOT BETWEEN 0 AND 99999.99
         OR rain NOT BETWEEN 0 AND 99999.99 OR showers NOT BETWEEN 0 AND 99999.99
         OR pressure_msl NOT BETWEEN 0 AND 99999.99
         OR surface_pressure NOT BETWEEN 0 AND 99999.99
         OR wind_speed_10m NOT BETWEEN 0 AND 9999.99
         OR wind_gusts_10m NOT BETWEEN 0 AND 9999.99
         OR relative_humidity_2m NOT BETWEEN 0 AND 100
         OR cloud_cover NOT BETWEEN 0 AND 100
         OR wind_direction_10m NOT BETWEEN 0 AND 360
         OR weather_code NOT BETWEEN 0 AND 65535
         OR weather_code<>TRUNCATE(weather_code,0)) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Some historic rows do not fit final numeric types';
    END IF;
END//
DELIMITER ;
CALL smartslope_guard_types();
DROP PROCEDURE smartslope_guard_types;

ALTER TABLE users ADD COLUMN contact_number VARCHAR(20) NULL AFTER username;
CREATE UNIQUE INDEX uq_users_contact_number ON users (contact_number);
-- Permit new registrations while legacy users await real phone numbers.
ALTER TABLE users MODIFY COLUMN email VARCHAR(254) NULL;
-- Existing users can log in. Set real, distinct numbers by account before
-- enforcing NOT NULL on both contact fields in a later cleanup.

CREATE TABLE sensors (
    sensor_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    sensor_name VARCHAR(100) NOT NULL,
    sensor_type ENUM('weather_api','physical_sensor') NOT NULL DEFAULT 'weather_api',
    provider_name VARCHAR(100) NOT NULL,
    endpoint_url VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(sensor_id),
    UNIQUE KEY uq_sensor_location_provider (location_id,sensor_type,provider_name),
    CONSTRAINT fk_sensor_location FOREIGN KEY (location_id) REFERENCES locations(location_id)
      ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sensors(location_id,sensor_name,sensor_type,provider_name,endpoint_url)
SELECT DISTINCT location_id, 'Open-Meteo weather API', 'weather_api', source_name,
       'https://api.open-meteo.com/v1/forecast'
FROM weather_observations;

ALTER TABLE weather_observations ADD COLUMN sensor_id INT UNSIGNED NULL AFTER observation_id;
UPDATE weather_observations AS w JOIN sensors AS s
 ON s.location_id=w.location_id AND s.sensor_type='weather_api' AND s.provider_name=w.source_name
 SET w.sensor_id=s.sensor_id;
-- This query must return 0; stop if any old source is not mapped.
SELECT COUNT(*) AS unmapped_observations FROM weather_observations WHERE sensor_id IS NULL;
ALTER TABLE weather_observations MODIFY COLUMN sensor_id INT UNSIGNED NOT NULL;
ALTER TABLE weather_observations DROP INDEX uq_weather_observation;
ALTER TABLE weather_observations ADD UNIQUE KEY uq_sensor_kind_observed(sensor_id,observation_kind,observed_at);
ALTER TABLE weather_observations ADD KEY idx_observation_hourly_time(sensor_id,observation_kind,observed_at);
ALTER TABLE weather_observations ADD CONSTRAINT fk_observation_sensor
 FOREIGN KEY(sensor_id) REFERENCES sensors(sensor_id) ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE weather_observations MODIFY COLUMN location_id INT UNSIGNED NULL,
 MODIFY COLUMN source_name VARCHAR(40) NULL;
-- Legacy location/source columns are kept for auditability on upgraded databases.
-- The new application uses sensor_id. The fresh schema omits them.

ALTER TABLE weather_observations
  MODIFY COLUMN temperature_2m DECIMAL(5,2) NULL,
  MODIFY COLUMN relative_humidity_2m DECIMAL(5,2) NULL,
  MODIFY COLUMN apparent_temperature DECIMAL(5,2) NULL,
  MODIFY COLUMN precipitation DECIMAL(7,2) NULL,
  MODIFY COLUMN rain DECIMAL(7,2) NULL,
  MODIFY COLUMN showers DECIMAL(7,2) NULL,
  MODIFY COLUMN weather_code SMALLINT UNSIGNED NULL,
  MODIFY COLUMN cloud_cover DECIMAL(5,2) NULL,
  MODIFY COLUMN pressure_msl DECIMAL(7,2) NULL,
  MODIFY COLUMN surface_pressure DECIMAL(7,2) NULL,
  MODIFY COLUMN wind_speed_10m DECIMAL(6,2) NULL,
  MODIFY COLUMN wind_direction_10m DECIMAL(5,2) NULL,
  MODIFY COLUMN wind_gusts_10m DECIMAL(6,2) NULL;

ALTER TABLE readings MODIFY COLUMN reading_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE reports MODIFY COLUMN report_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE weather_observations MODIFY COLUMN observation_id INT UNSIGNED NOT NULL AUTO_INCREMENT;
-- Medium/high prototype indicators; one alert record per rainfall reading.
CREATE TABLE alerts (
    alert_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reading_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    risk_level ENUM('medium','high') NOT NULL,
    status ENUM('active','resolved') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(alert_id),
    UNIQUE KEY uq_alert_reading (reading_id),
    KEY idx_alert_location_status (location_id, status, created_at),
    CONSTRAINT fk_alert_reading FOREIGN KEY (reading_id) REFERENCES readings(reading_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_alert_location FOREIGN KEY (location_id) REFERENCES locations(location_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO alerts(reading_id,location_id,risk_level,status)
SELECT reading_id,location_id,risk_level,'active' FROM readings
WHERE source_name='Open-Meteo' AND is_archived=0
  AND risk_level IN ('medium','high')
  AND observed_at>=UTC_TIMESTAMP()-INTERVAL 2 HOUR;

-- Old soil-moisture columns are retained for historical data but not queried
-- or collected by the aligned application. Fresh installations omit them.
