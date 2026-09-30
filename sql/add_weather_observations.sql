-- Run once on your existing database; safe to rerun. No existing rows are removed.
USE smartslope_mvp;

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
