-- Optional index already managed by database/migrate-awareness.php.
-- Prefer that PHP script: it checks whether the index exists before adding it.
-- Execute this SQL only if SHOW INDEX FROM events has no reading_history entry.
CREATE INDEX reading_history ON events (location_id, type, archived, observed_at);
