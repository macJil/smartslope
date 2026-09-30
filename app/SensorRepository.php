<?php
declare(strict_types=1);

/** A source registry: an API entry is virtual and is not physical hardware. */
final class SensorRepository
{
    public function __construct(private PDO $pdo) {}

    public function openMeteoForLocation(int $locationId): int
    {
        $query = $this->pdo->prepare(
            "INSERT INTO sensors (location_id, sensor_name, sensor_type, provider_name, endpoint_url)
             VALUES (:location_id, 'Open-Meteo weather API', 'weather_api', 'Open-Meteo',
                     'https://api.open-meteo.com/v1/forecast')
             ON DUPLICATE KEY UPDATE sensor_id = LAST_INSERT_ID(sensor_id)"
        );
        $query->execute(['location_id' => $locationId]);
        return (int) $this->pdo->lastInsertId();
    }
}
