-- SmartSlope database for Barangay Irisan, Baguio City.

CREATE DATABASE IF NOT EXISTS smartslope_mvp
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE smartslope_mvp;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY (username),
    UNIQUE KEY (email),
    UNIQUE KEY (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Locations table (all in Barangay Irisan)
CREATE TABLE IF NOT EXISTS locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    purok VARCHAR(100),
    landmark VARCHAR(255),
    lat DECIMAL(9,6),
    lng DECIMAL(9,6),
    susceptibility ENUM('very_high','high','moderate','low','debris_flow','unknown') DEFAULT 'unknown',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY (lat, lng)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shared event identity, ownership, location, and creation time.
CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    location_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED,
    type ENUM('reading','report') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY event_location_type (location_id, type),
    KEY event_user (user_id),
    KEY event_type_created (type, created_at),
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-to-one reading details; event_id is the reading's public ID.
CREATE TABLE IF NOT EXISTS readings (
    event_id INT UNSIGNED NOT NULL,
    rainfall_1h DECIMAL(7,2),
    rainfall_24h DECIMAL(7,2),
    rainfall_72h DECIMAL(7,2),
    rainfall_forecast_24h DECIMAL(7,2),
    precipitation_probability_24h TINYINT UNSIGNED,
    soil_moisture_9_27cm DECIMAL(6,4),
    soil_moisture_27_81cm DECIMAL(6,4),
    risk_level ENUM('low','normal','medium','high') NULL,
    temperature DECIMAL(5,2),
    humidity DECIMAL(5,2),
    wind_speed DECIMAL(6,2),
    weather_code INT,
    observed_at DATETIME,
    source ENUM('openmeteo','manual') DEFAULT 'openmeteo',
    rule_version VARCHAR(40) NULL,
    rainfall_window_end DATETIME NULL,
    provider_payload LONGTEXT NULL,
    adjustment_log LONGTEXT NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    stale TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (event_id),
    KEY reading_history (archived, observed_at, event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-to-one community report details.
CREATE TABLE IF NOT EXISTS reports (
    event_id INT UNSIGNED NOT NULL,
    message TEXT,
    contact_phone VARCHAR(20),
    contact_email VARCHAR(254),
    house_landmark VARCHAR(255),
    status ENUM('pending','reviewed','resolved') DEFAULT 'pending',
    report_type VARCHAR(30) NULL,
    occurred_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    PRIMARY KEY (event_id),
    KEY report_status (status, event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: Initial admin user (password: admin123)
INSERT INTO users (full_name, username, email, phone, password, role)
VALUES ('Administrator', 'admin', 'admin@smartslope.test', '+639123456789', 
        '$2y$12$lepdU1co7FTTCIgEi/j/t.hUT1Yyqwi.puuJE62ju7Xj0DI/Tztqy', 'admin')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);

-- Seed: Initial location (Irisan pilot point)
INSERT INTO locations (name, purok, lat, lng, susceptibility)
VALUES ('Irisan pilot point', 'Pilot', 16.421000, 120.559500, 'unknown')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
