-- Existing installations: run this once in the smartslope_mvp database.
-- No existing observations or reports are deleted.
CREATE TABLE IF NOT EXISTS weather_fetches (
    fetch_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sensor_id INT UNSIGNED NOT NULL,
    observed_at DATETIME NOT NULL,
    fetched_at DATETIME NOT NULL,
    interval_seconds SMALLINT UNSIGNED NULL,
    temperature_2m DECIMAL(5,2) NULL,
    relative_humidity_2m DECIMAL(5,2) NULL,
    precipitation DECIMAL(7,2) NULL,
    rain DECIMAL(7,2) NULL,
    showers DECIMAL(7,2) NULL,
    wind_speed_10m DECIMAL(6,2) NULL,
    wind_gusts_10m DECIMAL(6,2) NULL,
    cloud_cover DECIMAL(5,2) NULL,
    weather_code SMALLINT UNSIGNED NULL,
    rainfall_1h_mm DECIMAL(7,2) NULL,
    rainfall_24h_mm DECIMAL(7,2) NULL,
    rainfall_72h_mm DECIMAL(7,2) NULL,
    risk_level ENUM('low','normal','medium','high') NULL,
    is_stale TINYINT(1) NOT NULL DEFAULT 0,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (fetch_id),
    KEY idx_fetch_sensor (sensor_id, fetched_at, fetch_id),
    CONSTRAINT fk_fetch_sensor FOREIGN KEY (sensor_id) REFERENCES sensors(sensor_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bring existing saved current observations into the list once.
INSERT INTO weather_fetches (sensor_id,observed_at,fetched_at,interval_seconds,
    temperature_2m,relative_humidity_2m,precipitation,rain,showers,
    wind_speed_10m,wind_gusts_10m,cloud_cover,weather_code)
SELECT w.sensor_id,w.observed_at,w.fetched_at,w.interval_seconds,
    w.temperature_2m,w.relative_humidity_2m,w.precipitation,w.rain,w.showers,
    w.wind_speed_10m,w.wind_gusts_10m,w.cloud_cover,w.weather_code
FROM weather_observations AS w
WHERE w.observation_kind='current' AND NOT EXISTS (
    SELECT 1 FROM weather_fetches AS f
    WHERE f.sensor_id=w.sensor_id AND f.observed_at=w.observed_at
);
