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
             WHERE r.location_id = :location_id AND r.is_archived = 0 AND r.source_name = 'Open-Meteo'
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
               AND r.is_archived = 0 AND r.source_name = \'Open-Meteo\'
             ORDER BY r.observed_at DESC, r.reading_id DESC'
        );
        return $statement->fetchAll();
    }

    /** Saved provider current observations for one active Irisan location. */
    public function currentForLocation(int $locationId, ?int $limit = 30): array
    {
        $limitSql = $limit === null ? '' : ' LIMIT ' . max(1, min(100, $limit));
        $statement = $this->pdo->prepare(
            "SELECT w.observation_id, w.observed_at, w.fetched_at, w.interval_seconds,
                    w.temperature_2m, w.relative_humidity_2m, w.precipitation,
                    w.rain, w.showers, w.wind_speed_10m, w.wind_gusts_10m,
                    w.cloud_cover, w.weather_code, l.location_name, l.purok_zone
             FROM weather_observations AS w
             JOIN sensors AS s ON s.sensor_id = w.sensor_id
             JOIN locations AS l ON l.location_id = s.location_id
             JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE l.location_id = :location_id AND l.is_active = 1 AND b.is_active = 1
               AND b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
               AND s.sensor_type = 'weather_api' AND s.provider_name = 'Open-Meteo'
               AND w.observation_kind = 'current'
             ORDER BY w.observed_at DESC, w.observation_id DESC" . $limitSql
        );
        $statement->execute(['location_id' => $locationId]);
        return $statement->fetchAll();
    }

    /** Active stored rainfall summaries for the selected map location. */
    public function forActiveLocation(int $locationId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT r.reading_id, r.location_id, r.rainfall_1h_mm,
                    r.rainfall_24h_mm, r.rainfall_72h_mm, r.risk_level,
                    r.observed_at, r.source_name
             FROM readings AS r JOIN locations AS l ON l.location_id = r.location_id
             JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE r.location_id = :location_id AND r.is_archived = 0
               AND r.source_name = 'Open-Meteo' AND l.is_active = 1 AND b.is_active = 1
               AND b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
             ORDER BY r.observed_at DESC, r.reading_id DESC LIMIT 50"
        );
        $statement->execute(['location_id' => $locationId]);
        return $statement->fetchAll();
    }

    /** Change a saved current observation only; hourly data and risk summaries remain provider sourced. */
    public function updateCurrent(int $locationId, int $observationId, array $values): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE weather_observations AS w
             JOIN sensors AS s ON s.sensor_id = w.sensor_id
             JOIN locations AS l ON l.location_id = s.location_id
             JOIN barangays AS b ON b.barangay_id = l.barangay_id
             SET w.temperature_2m = :temperature, w.relative_humidity_2m = :humidity,
                 w.precipitation = :precipitation, w.rain = :rain, w.showers = :showers,
                 w.wind_speed_10m = :wind, w.wind_gusts_10m = :gusts
             WHERE w.observation_id = :observation_id AND l.location_id = :location_id
               AND w.observation_kind = 'current' AND l.is_active = 1 AND b.is_active = 1
               AND b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
               AND s.sensor_type = 'weather_api' AND s.provider_name = 'Open-Meteo'"
        );
        $statement->execute($values + ['location_id'=>$locationId,'observation_id'=>$observationId]);
        return $statement->rowCount() > 0 || $this->hasCurrent($locationId,$observationId);
    }

    public function hasCurrent(int $locationId, int $observationId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM weather_observations AS w
             JOIN sensors AS s ON s.sensor_id = w.sensor_id
             JOIN locations AS l ON l.location_id = s.location_id
             JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE w.observation_id = :observation_id AND l.location_id = :location_id
               AND w.observation_kind = 'current' AND l.is_active = 1 AND b.is_active = 1
               AND b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
               AND s.sensor_type = 'weather_api' AND s.provider_name = 'Open-Meteo'"
        );
        $statement->execute(['observation_id'=>$observationId,'location_id'=>$locationId]);
        return (bool)$statement->fetchColumn();
    }

    public function deleteCurrent(int $locationId, int $observationId): bool
    {
        if (!$this->hasCurrent($locationId,$observationId)) return false;
        $statement = $this->pdo->prepare('DELETE FROM weather_observations WHERE observation_id = :observation_id AND observation_kind = \'current\'');
        $statement->execute(['observation_id'=>$observationId]);
        return $statement->rowCount() > 0;
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

    /** Return the saved summary, preserving an administrator's corrections. */
    public function createFromApi(array $reading): ?array
    {
        $reading['risk_level'] = RiskAnalyzer::analyze(
            $reading['rainfall_1h_mm'], $reading['rainfall_24h_mm'], $reading['rainfall_72h_mm']
        );
        $query = $this->pdo->prepare(
            "INSERT INTO readings (location_id,rainfall_1h_mm,rainfall_24h_mm,rainfall_72h_mm,
                     risk_level,source_name,source_url,observed_at,recorded_by_user_id)
             VALUES (:location_id,:rainfall_1h_mm,:rainfall_24h_mm,:rainfall_72h_mm,
                     :risk_level,:source_name,:source_url,:observed_at,NULL)
             ON DUPLICATE KEY UPDATE
                reading_id=LAST_INSERT_ID(reading_id),
                rainfall_1h_mm=IF(recorded_by_user_id IS NULL AND is_archived=0,
                    VALUES(rainfall_1h_mm),rainfall_1h_mm),
                rainfall_24h_mm=IF(recorded_by_user_id IS NULL AND is_archived=0,
                    VALUES(rainfall_24h_mm),rainfall_24h_mm),
                rainfall_72h_mm=IF(recorded_by_user_id IS NULL AND is_archived=0,
                    VALUES(rainfall_72h_mm),rainfall_72h_mm),
                risk_level=IF(recorded_by_user_id IS NULL AND is_archived=0,
                    VALUES(risk_level),risk_level)"
        );
        $query->execute($reading);
        $saved = $this->find((int) $this->pdo->lastInsertId());
        return $saved && (int)$saved['is_archived'] === 0 ? $saved : null;
    }

    /** Caller owns the transaction; current and hourly readings have distinct keys. */
    public function saveWeatherObservations(int $sensorId, array $current, array $hourly, string $fetchedAt): int
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
        ];
        $columns = array_merge(['sensor_id','observation_kind','observed_at','fetched_at','interval_seconds'], $fields);
        $updates = array_merge(['fetched_at','interval_seconds'], $fields);
        $query = $this->pdo->prepare(
            'INSERT INTO weather_observations ('.implode(',', $columns).') VALUES (:'.implode(',:',$columns).') '
            .'ON DUPLICATE KEY UPDATE '.implode(',', array_map(
                static fn(string $field): string => $field.'=VALUES('.$field.')', $updates
            ))
        );
        $save = static function (array $row, string $kind) use ($query,$sensorId,$fetchedAt,$fields): void {
            $values = [
                'sensor_id'=>$sensorId, 'observation_kind'=>$kind,
                'observed_at'=>(new DateTimeImmutable($row['time']))
                    ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'fetched_at'=>$fetchedAt,
                'interval_seconds'=>$kind === 'hourly' ? 3600 : ($row['interval'] ?? null),
            ];
            foreach ($fields as $field) {
                $value=$row[$field] ?? null;
                $values[$field]=is_numeric($value) ? (float)$value : null;
            }
            $query->execute($values);
        };
        $save($current,'current');
        foreach ($hourly as $row) $save($row,'hourly');
        return count($hourly)+1;
    }

    /** Complete contiguous rainfall windows come from stored hourly provider rows. */
    public function rainfallFromStoredHours(int $sensorId, string $latestUtc): array
    {
        $query=$this->pdo->prepare(
            "SELECT observed_at,precipitation FROM weather_observations
             WHERE sensor_id=:sensor_id AND observation_kind='hourly' AND observed_at<=:latest
             ORDER BY observed_at DESC LIMIT 72"
        );
        $query->execute(['sensor_id'=>$sensorId,'latest'=>$latestUtc]);
        $rows=$query->fetchAll();
        $total = static function (array $rows,int $hours): ?float {
            if (count($rows)<$hours) return null;
            $sum=0.0;
            $expected=strtotime($rows[0]['observed_at'].' UTC');
            for ($i=0;$i<$hours;$i++) {
                if (strtotime($rows[$i]['observed_at'].' UTC') !== $expected-$i*3600
                    || !is_numeric($rows[$i]['precipitation']) || (float)$rows[$i]['precipitation']<0) return null;
                $sum+=(float)$rows[$i]['precipitation'];
            }
            return round($sum,2);
        };
        return [$total($rows,1),$total($rows,24),$total($rows,72)];
    }

    /** Administrators may correct API totals; original location/time/source stay intact. */
    public function update(int $readingId, array $rainfall, int $adminId): bool
    {
        $reading=$this->find($readingId);
        if (!$reading || (int)$reading['is_archived']===1 || $reading['source_name']!=='Open-Meteo') return false;
        $level=RiskAnalyzer::analyze(
            $rainfall['rainfall_1h_mm'],$rainfall['rainfall_24h_mm'],$rainfall['rainfall_72h_mm']
        );
        $query=$this->pdo->prepare(
            "UPDATE readings SET rainfall_1h_mm=:rainfall_1h_mm,
             rainfall_24h_mm=:rainfall_24h_mm,rainfall_72h_mm=:rainfall_72h_mm,
             risk_level=:risk_level,recorded_by_user_id=:admin_id
             WHERE reading_id=:reading_id AND source_name='Open-Meteo' AND is_archived=0"
        );
        $query->execute($rainfall+['risk_level'=>$level,'admin_id'=>$adminId,'reading_id'=>$readingId]);
        (new AlertRepository($this->pdo))->synchronize($readingId,(int)$reading['location_id'],$level);
        return true;
    }

    public function setArchived(int $readingId, bool $archived): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE readings SET is_archived = :is_archived
             WHERE reading_id = :reading_id AND source_name='Open-Meteo'
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
