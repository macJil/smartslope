-- 1. USERS TABLE
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_created_at ON users(created_at);

-- 2. LOCATIONS TABLE
CREATE INDEX idx_locations_susceptibility ON locations(susceptibility);
CREATE INDEX idx_locations_active ON locations(active);
CREATE INDEX idx_locations_purok ON locations(purok);

-- 3. READINGS TABLE (Critical for monitoring)
CREATE INDEX idx_readings_risk_level ON readings(risk_level);
CREATE INDEX idx_readings_observed_at ON readings(observed_at);
CREATE INDEX idx_readings_source ON readings(source);

-- 4. REPORTS TABLE
CREATE INDEX idx_reports_status ON reports(status);
CREATE INDEX idx_reports_report_type ON reports(report_type);
CREATE INDEX idx_reports_occurred_at ON reports(occurred_at);
