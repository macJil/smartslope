-- One-time migration for an existing smartslope_mvp database.
-- Do not run this after importing the updated db.sql into a fresh database,
-- because the is_archived column and index are already present there.
-- Existing readings remain active (is_archived = 0) and keep their history.

USE smartslope_mvp;

ALTER TABLE readings
    ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER recorded_by_user_id,
    ADD KEY idx_readings_active_latest (location_id, is_archived, observed_at, reading_id),
    ADD CONSTRAINT chk_readings_archived CHECK (is_archived IN (0, 1));
