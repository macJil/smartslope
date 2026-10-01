-- Run once against an existing SmartSlope database before deploying the updated app.
ALTER TABLE events
    ADD COLUMN rainfall_forecast_24h DECIMAL(7,2) NULL AFTER rainfall_72h,
    ADD COLUMN precipitation_probability_24h TINYINT UNSIGNED NULL AFTER rainfall_forecast_24h,
    ADD COLUMN soil_moisture_9_27cm DECIMAL(6,4) NULL AFTER precipitation_probability_24h,
    ADD COLUMN soil_moisture_27_81cm DECIMAL(6,4) NULL AFTER soil_moisture_9_27cm;