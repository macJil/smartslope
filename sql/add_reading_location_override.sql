-- Run ONCE on the existing smartslope_mvp database after backing it up.
-- Original weather source/coordinates stay linked through sensor_id.
USE smartslope_mvp;
ALTER TABLE weather_fetches
    ADD COLUMN location_id INT UNSIGNED NULL AFTER sensor_id,
    ADD KEY idx_fetch_location (location_id),
    ADD CONSTRAINT fk_fetch_display_location FOREIGN KEY (location_id)
        REFERENCES locations(location_id) ON UPDATE CASCADE ON DELETE RESTRICT;
