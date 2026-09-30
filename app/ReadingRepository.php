<?php
declare(strict_types=1);

final class ReadingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function latestForActiveLocation(int $locationId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT r.reading_id, r.location_id, l.location_name, l.purok_zone,
                    l.latitude, l.longitude, r.rainfall_1h_mm, r.rainfall_24h_mm,
                    r.rainfall_72h_mm, r.risk_level, r.source_name, r.source_url,
                    r.observed_at
             FROM readings AS r
             INNER JOIN locations AS l ON l.location_id = r.location_id
             INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE r.location_id = :location_id AND r.is_archived = 0
               AND l.is_active = 1 AND b.is_active = 1
               AND b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
             ORDER BY r.observed_at DESC, r.reading_id DESC LIMIT 1"
        );
        $statement->execute(['location_id' => $locationId]);
        $reading = $statement->fetch();
        return $reading ?: null;
    }

    public function adminList(): array
    {
        $statement = $this->pdo->query(
            'SELECT r.reading_id, r.location_id, l.location_name, l.purok_zone,
                    l.is_active AS location_is_active,
                    r.rainfall_1h_mm, r.rainfall_24h_mm, r.rainfall_72h_mm,
                    r.risk_level, r.source_name, r.source_url, r.observed_at,
                    r.is_archived, r.created_at
             FROM readings AS r
             INNER JOIN locations AS l ON l.location_id = r.location_id
             INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE b.barangay_name = \'Barangay Irisan\' AND b.city_name = \'Baguio City\'
             ORDER BY r.observed_at DESC, r.reading_id DESC'
        );
        return $statement->fetchAll();
    }

    public function find(int $readingId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT r.reading_id, r.location_id, r.rainfall_1h_mm, r.rainfall_24h_mm,
                    r.rainfall_72h_mm, r.source_name, r.source_url, r.observed_at, r.is_archived
             FROM readings AS r
             INNER JOIN locations AS l ON l.location_id = r.location_id
             INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE r.reading_id = :reading_id
               AND b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
             LIMIT 1"
        );
        $statement->execute(['reading_id' => $readingId]);
        $reading = $statement->fetch();
        return $reading ?: null;
    }

    /** Save one snapshot per location/provider/hour, preserving admin edits and archives. */
    public function createFromApi(array $reading): void
    {
        $reading['risk_level'] = RiskAnalyzer::analyze(
            $reading['rainfall_1h_mm'],
            $reading['rainfall_24h_mm'],
            $reading['rainfall_72h_mm']
        );
        $statement = $this->pdo->prepare(
            'INSERT INTO readings
                (location_id, rainfall_1h_mm, rainfall_24h_mm, rainfall_72h_mm,
                 risk_level, source_name, source_url, observed_at, recorded_by_user_id)
             VALUES
                (:location_id, :rainfall_1h_mm, :rainfall_24h_mm, :rainfall_72h_mm,
                 :risk_level, :source_name, :source_url, :observed_at, NULL)
             ON DUPLICATE KEY UPDATE reading_id = reading_id'
        );
        $statement->execute($reading);
    }

    /** Save all displayed provider values. Caller owns the transaction.
     * Current interval values and hourly totals have separate identities.
     * Re-fetching an observation updates it instead of duplicating it.
     */
    public function saveWeatherObservations(int $locationId, array $current, array $hourly, string $fetchedAt): int
    {
        $fields = [
            'temperature_2m',
            'relative_humidity_2m',
            'apparent_temperature',
            'precipitation',
            'rain',
            'showers',
            'weather_code',
            'cloud_cover',
            'pressure_msl',
            'surface_pressure',
            'wind_speed_10m',
            'wind_direction_10m',
            'wind_gusts_10m',
            'soil_moisture_0_to_1cm',
            'soil_moisture_1_to_3cm',
            'soil_moisture_3_to_9cm',
            'soil_moisture_9_to_27cm',
            'soil_moisture_27_to_81cm',
        ];
        $columns = array_merge(
            ['location_id', 'source_name', 'observation_kind', 'observed_at', 'fetched_at', 'interval_seconds'],
            $fields
        );
        $updates = array_merge(['fetched_at', 'interval_seconds'], $fields);
        $statement = $this->pdo->prepare(
            'INSERT INTO weather_observations (' . implode(', ', $columns) . ') VALUES (:'
            . implode(', :', $columns) . ') ON DUPLICATE KEY UPDATE '
            . implode(', ', array_map(static fn(string $column): string => $column . ' = VALUES(' . $column . ')', $updates))
        );
        $utc = new DateTimeZone('UTC');
        $save = static function (array $row, string $kind) use ($statement, $locationId, $fetchedAt, $fields, $utc): void {
            $values = [
                'location_id' => $locationId,
                'source_name' => 'Open-Meteo',
                'observation_kind' => $kind,
                'observed_at' => (new DateTimeImmutable($row['time']))->setTimezone($utc)->format('Y-m-d H:i:s'),
                'fetched_at' => $fetchedAt,
                'interval_seconds' => $kind === 'hourly' ? 3600 : ($row['interval'] ?? null),
            ];
            foreach ($fields as $field) {
                $value = $row[$field] ?? null;
                $values[$field] = is_numeric($value) ? (float) $value : null;
            }
            $statement->execute($values);
        };
        $save($current, 'current');
        foreach ($hourly as $row) {
            $save($row, 'hourly');
        }
        return count($hourly) + 1;
    }

    public function create(array $reading, ?int $adminId): void
    {
        $reading['risk_level'] = RiskAnalyzer::analyze(
            $reading['rainfall_1h_mm'],
            $reading['rainfall_24h_mm'],
            $reading['rainfall_72h_mm']
        );
        $reading['recorded_by_user_id'] = $adminId;
        $statement = $this->pdo->prepare(
            'INSERT INTO readings
                (location_id, rainfall_1h_mm, rainfall_24h_mm, rainfall_72h_mm,
                 risk_level, source_name, source_url, observed_at, recorded_by_user_id)
             VALUES
                (:location_id, :rainfall_1h_mm, :rainfall_24h_mm, :rainfall_72h_mm,
                 :risk_level, :source_name, :source_url, :observed_at, :recorded_by_user_id)'
        );
        $statement->execute($reading);
    }

    public function update(int $readingId, array $reading): bool
    {
        $reading['risk_level'] = RiskAnalyzer::analyze(
            $reading['rainfall_1h_mm'],
            $reading['rainfall_24h_mm'],
            $reading['rainfall_72h_mm']
        );
        $reading['reading_id'] = $readingId;
        $reading['target_location_id'] = $reading['location_id'];
        $statement = $this->pdo->prepare(
            "UPDATE readings SET
                location_id = :location_id,
                rainfall_1h_mm = :rainfall_1h_mm,
                rainfall_24h_mm = :rainfall_24h_mm,
                rainfall_72h_mm = :rainfall_72h_mm,
                risk_level = :risk_level,
                source_name = :source_name,
                source_url = :source_url,
                observed_at = :observed_at
             WHERE reading_id = :reading_id AND is_archived = 0
               AND :target_location_id IN (
                   SELECT l.location_id FROM locations AS l
                   INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
                   WHERE b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
               )
               AND location_id IN (
                   SELECT l.location_id FROM locations AS l
                   INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
                   WHERE b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
               )"
        );
        $statement->execute($reading);
        $updated = $this->find($readingId);
        return $updated !== null
            && (int) $updated['is_archived'] === 0
            && (int) $updated['location_id'] === (int) $reading['location_id'];
    }

    public function setArchived(int $readingId, bool $archived): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE readings SET is_archived = :is_archived
             WHERE reading_id = :reading_id
               AND location_id IN (
                   SELECT l.location_id FROM locations AS l
                   INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
                   WHERE b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
               )"
        );
        $statement->execute([
            'is_archived' => $archived ? 1 : 0,
            'reading_id' => $readingId,
        ]);
        return $statement->rowCount() > 0 || $this->find($readingId) !== null;
    }
}
