-- Existing smartslope_mvp database only. No rows are deleted or replaced.
-- Users and locations must already exist. For a fresh database use db.sql instead.
USE smartslope_mvp;

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


SET @weather_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'readings'
             AND column_name = 'rainfall_72h_mm'),
    'SELECT 1',
    'ALTER TABLE readings ADD COLUMN rainfall_72h_mm DECIMAL(7,2) NULL AFTER rainfall_24h_mm'
);
PREPARE weather_migration FROM @weather_ddl;
EXECUTE weather_migration;
DEALLOCATE PREPARE weather_migration;

SET @weather_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'readings'
             AND column_name = 'is_archived'),
    'SELECT 1',
    'ALTER TABLE readings ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER recorded_by_user_id'
);
PREPARE weather_migration FROM @weather_ddl;
EXECUTE weather_migration;
DEALLOCATE PREPARE weather_migration;
