
-- SmartSlope academic MVP database
-- Target: MySQL 8.0+ / MariaDB 10.4+
-- Fresh-install script. It creates a NEW database named smartslope_mvp and
-- does not drop or overwrite the existing baguio_multi_barangay database.
--
-- Scope: one study barangay; admin/user accounts; sourced location risk data;
-- rainfall observations and rule-based risk levels; community reports.
-- No sensor telemetry, payments, subscriptions, AI/ML, or geographic expansion.

CREATE DATABASE IF NOT EXISTS smartslope_mvp
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE smartslope_mvp;

-- 1. Accounts used by administrators and (if enabled) registered community users.
-- Never store a plain-text password. Store PHP password_hash() output here.
CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Administrative study area. Seed only the selected barangay for the MVP.
-- Additional barangays can be inserted later without changing the schema.
CREATE TABLE IF NOT EXISTS barangays (
    barangay_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    barangay_name VARCHAR(100) NOT NULL,
    city_name VARCHAR(100) NOT NULL DEFAULT 'Baguio City',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (barangay_id),
    UNIQUE KEY uq_barangay_city_name (city_name, barangay_name),
    KEY idx_barangays_active (is_active, barangay_name),
    CONSTRAINT chk_barangays_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Named slope/area locations within the chosen barangay.
-- Baseline susceptibility is separate from a current rainfall-based reading.
-- Coordinates and susceptibility source details may remain NULL until verified.
CREATE TABLE IF NOT EXISTS locations (
    location_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    barangay_id SMALLINT UNSIGNED NOT NULL,
    location_name VARCHAR(150) NOT NULL,
    purok_zone VARCHAR(100) NULL,
    landmark VARCHAR(255) NULL,
    latitude DECIMAL(9, 6) NULL,
    longitude DECIMAL(9, 6) NULL,
    susceptibility_class ENUM(
        'very_high', 'high', 'moderate', 'low', 'debris_flow', 'unknown'
    ) NOT NULL DEFAULT 'unknown',
    hazard_source_name VARCHAR(150) NULL,
    hazard_source_url VARCHAR(500) NULL,
    hazard_source_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (location_id),
    UNIQUE KEY uq_location_name_in_area
        (barangay_id, location_name, purok_zone),
    KEY idx_locations_area_active (barangay_id, is_active, location_name),
    CONSTRAINT fk_locations_barangay
        FOREIGN KEY (barangay_id) REFERENCES barangays (barangay_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_locations_coordinates_pair CHECK (
        (latitude IS NULL AND longitude IS NULL)
        OR (latitude IS NOT NULL AND longitude IS NOT NULL)
    ),
    CONSTRAINT chk_locations_latitude CHECK (
        latitude IS NULL OR latitude BETWEEN -90 AND 90
    ),
    CONSTRAINT chk_locations_longitude CHECK (
        longitude IS NULL OR longitude BETWEEN -180 AND 180
    ),
    CONSTRAINT chk_locations_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Time-stamped, sourced rainfall observations and the rule-based result.
-- Store only values supported by the selected API or another verified source.
-- observed_at is stored in UTC; convert to Asia/Manila for display in PHP.
CREATE TABLE IF NOT EXISTS readings (
    reading_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    rainfall_1h_mm DECIMAL(7, 2) NULL,
    rainfall_24h_mm DECIMAL(7, 2) NULL,
    rainfall_72h_mm DECIMAL(7, 2) NULL,
    risk_level ENUM('low', 'normal', 'medium', 'high') NOT NULL,
    source_name VARCHAR(150) NOT NULL,
    source_url VARCHAR(500) NULL,
    observed_at DATETIME NOT NULL,
    -- NULL means imported/system-collected (API); an admin ID means manual entry.
    recorded_by_user_id INT UNSIGNED NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reading_id),
    UNIQUE KEY uq_reading_source_time (location_id, observed_at, source_name),
    KEY idx_readings_latest (location_id, observed_at, reading_id),
    KEY idx_readings_active_latest (location_id, is_archived, observed_at, reading_id),
    KEY idx_readings_recorded_by (recorded_by_user_id),
    CONSTRAINT fk_readings_location
        FOREIGN KEY (location_id) REFERENCES locations (location_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_readings_recorded_by
        FOREIGN KEY (recorded_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_readings_rainfall_present CHECK (
        rainfall_1h_mm IS NOT NULL OR rainfall_24h_mm IS NOT NULL
        OR rainfall_72h_mm IS NOT NULL
    ),
    CONSTRAINT chk_readings_rainfall_1h CHECK (
        rainfall_1h_mm IS NULL OR rainfall_1h_mm >= 0
    ),
    CONSTRAINT chk_readings_rainfall_24h CHECK (
        rainfall_24h_mm IS NULL OR rainfall_24h_mm >= 0
    ),
    CONSTRAINT chk_readings_rainfall_72h CHECK (
        rainfall_72h_mm IS NULL OR rainfall_72h_mm >= 0
    ),
    CONSTRAINT chk_readings_archived CHECK (is_archived IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Community reports and their admin review state.
-- reported_by_user_id is nullable so a public report can be anonymous.
CREATE TABLE IF NOT EXISTS reports (
    report_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    reported_by_user_id INT UNSIGNED NULL,
    house_landmark VARCHAR(255) NULL,
    message VARCHAR(2000) NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved') NOT NULL DEFAULT 'pending',
    reviewed_by_user_id INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (report_id),
    KEY idx_reports_status_created (status, created_at),
    KEY idx_reports_location_created (location_id, created_at),
    KEY idx_reports_reporter (reported_by_user_id),
    KEY idx_reports_reviewer (reviewed_by_user_id),
    CONSTRAINT fk_reports_location
        FOREIGN KEY (location_id) REFERENCES locations (location_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reports_reporter
        FOREIGN KEY (reported_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_reports_reviewer
        FOREIGN KEY (reviewed_by_user_id) REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Base seed: one selected study barangay. The repository's current prototype
-- points to Barangay Irisan; change this one value if the team selects another.
INSERT INTO barangays (barangay_name, city_name)
VALUES ('Barangay Irisan', 'Baguio City')
ON DUPLICATE KEY UPDATE barangay_id = LAST_INSERT_ID(barangay_id);

-- No default administrator is created. Create the first admin through a
-- protected setup step and store a PHP password_hash() result in password_hash.
-- Public registration code must always assign role='user'; never accept a role
-- value supplied by a registration form.

-- Useful MVP queries ---------------------------------------------------------

-- Active locations and their latest reading (one row per location).
SELECT
    b.barangay_name,
    l.location_id,
    l.location_name,
    l.purok_zone,
    l.latitude,
    l.longitude,
    l.susceptibility_class,
    r.rainfall_1h_mm,
    r.rainfall_24h_mm,
    r.rainfall_72h_mm,
    r.risk_level,
    r.source_name,
    r.observed_at
FROM locations AS l
JOIN barangays AS b ON b.barangay_id = l.barangay_id
LEFT JOIN readings AS r
    ON r.reading_id = (
        SELECT r2.reading_id
        FROM readings AS r2
        WHERE r2.location_id = l.location_id
        ORDER BY r2.observed_at DESC, r2.reading_id DESC
        LIMIT 1
    )
WHERE l.is_active = 1
  AND b.is_active = 1
ORDER BY l.location_name;

-- Admin report queue.
SELECT
    r.report_id,
    l.location_name,
    r.house_landmark,
    r.message,
    r.status,
    r.created_at,
    reporter.full_name AS reporter_name,
    reviewer.full_name AS reviewed_by
FROM reports AS r
JOIN locations AS l ON l.location_id = r.location_id
LEFT JOIN users AS reporter ON reporter.user_id = r.reported_by_user_id
LEFT JOIN users AS reviewer ON reviewer.user_id = r.reviewed_by_user_id
WHERE r.status IN ('pending', 'reviewed')
ORDER BY r.created_at DESC;

-- Example parameterized CRUD statements for PDO (bind values in PHP).
-- Create a location:
-- INSERT INTO locations (barangay_id, location_name, purok_zone, latitude, longitude)
-- VALUES (:barangay_id, :location_name, :purok_zone, :latitude, :longitude);
--
-- Create a sourced reading:
-- INSERT INTO readings
--   (location_id, rainfall_1h_mm, rainfall_24h_mm, rainfall_72h_mm, risk_level,
--    source_name, source_url, observed_at, recorded_by_user_id)
-- VALUES
--   (:location_id, :rainfall_1h_mm, :rainfall_24h_mm, :rainfall_72h_mm, :risk_level,
--    :source_name, :source_url, :observed_at, :recorded_by_user_id);
--
-- Submit a report:
-- INSERT INTO reports (location_id, reported_by_user_id, house_landmark, message)
-- VALUES (:location_id, :reported_by_user_id, :house_landmark, :message);
--
-- Update report status during admin review:
-- UPDATE reports
-- SET status = :status, reviewed_by_user_id = :admin_user_id,
--     reviewed_at = UTC_TIMESTAMP()
-- WHERE report_id = :report_id;
--
-- Archive a location instead of deleting its history:
-- UPDATE locations SET is_active = 0 WHERE location_id = :location_id;

-- Full provider readings, separate from admin-reviewed risk summaries.
CREATE TABLE IF NOT EXISTS weather_observations (
    observation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    source_name VARCHAR(40) NOT NULL DEFAULT 'Open-Meteo',
    observation_kind ENUM('current', 'hourly') NOT NULL,
    observed_at DATETIME NOT NULL COMMENT 'UTC provider time',
    fetched_at DATETIME NOT NULL COMMENT 'UTC latest successful fetch',
    interval_seconds INT UNSIGNED NULL,
    temperature_2m DECIMAL(12, 4) NULL,
    relative_humidity_2m DECIMAL(12, 4) NULL,
    apparent_temperature DECIMAL(12, 4) NULL,
    precipitation DECIMAL(12, 4) NULL,
    rain DECIMAL(12, 4) NULL,
    showers DECIMAL(12, 4) NULL,
    weather_code DECIMAL(12, 4) NULL,
    cloud_cover DECIMAL(12, 4) NULL,
    pressure_msl DECIMAL(12, 4) NULL,
    surface_pressure DECIMAL(12, 4) NULL,
    wind_speed_10m DECIMAL(12, 4) NULL,
    wind_direction_10m DECIMAL(12, 4) NULL,
    wind_gusts_10m DECIMAL(12, 4) NULL,
    soil_moisture_0_to_1cm DECIMAL(12, 4) NULL,
    soil_moisture_1_to_3cm DECIMAL(12, 4) NULL,
    soil_moisture_3_to_9cm DECIMAL(12, 4) NULL,
    soil_moisture_9_to_27cm DECIMAL(12, 4) NULL,
    soil_moisture_27_to_81cm DECIMAL(12, 4) NULL,
    PRIMARY KEY (observation_id),
    UNIQUE KEY uq_weather_observation (location_id, source_name, observation_kind, observed_at),
    CONSTRAINT fk_weather_observation_location FOREIGN KEY (location_id)
        REFERENCES locations (location_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
