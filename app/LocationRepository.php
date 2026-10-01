<?php
declare(strict_types=1);

final class LocationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Reuse a coordinate point so reports, observations and risk share its ID. */
    public function forMapPoint(float $latitude, float $longitude): array
    {
        $latitude = round($latitude, 5);
        $longitude = round($longitude, 5);
        if (!StudyArea::contains($latitude, $longitude)) {
            throw new InvalidArgumentException('Select a point inside Irisan.');
        }
        $name = sprintf('Irisan %.5f, %.5f', $latitude, $longitude);
        $query = $this->pdo->prepare(
            "INSERT INTO locations (barangay_id,location_name,purok_zone,latitude,longitude,susceptibility_class)
             VALUES (:barangay_id,:name,'Map point',:latitude,:longitude,'unknown')
             ON DUPLICATE KEY UPDATE location_id=LAST_INSERT_ID(location_id), is_active=1"
        );
        $query->execute(['barangay_id'=>$this->studyBarangayId(), 'name'=>$name,
            'latitude'=>$latitude, 'longitude'=>$longitude]);
        $location = $this->find((int)$this->pdo->lastInsertId());
        if (!$location || !(int)$location['is_active']) throw new DomainException('Location inactive.');
        return $location;
    }

    public function studyBarangayId(): int
    {
        $statement = $this->pdo->prepare(
            "SELECT barangay_id FROM barangays
             WHERE barangay_name = 'Barangay Irisan' AND city_name = 'Baguio City'
               AND is_active = 1 LIMIT 1"
        );
        $statement->execute();
        $barangayId = $statement->fetchColumn();

        if ($barangayId === false) {
            throw new RuntimeException('The active study barangay has not been seeded.');
        }

        return (int) $barangayId;
    }

    public function activeForStudyArea(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT l.location_id, l.location_name, l.purok_zone, l.landmark,
                    l.latitude, l.longitude, l.susceptibility_class, l.hazard_source_name
             FROM locations AS l
             INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE l.is_active = 1 AND b.is_active = 1
               AND b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
             ORDER BY l.location_name, l.purok_zone"
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    public function adminList(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT l.location_id, l.location_name, l.purok_zone, l.landmark,
                    l.latitude, l.longitude, l.susceptibility_class,
                    l.hazard_source_name, l.hazard_source_url, l.hazard_source_date,
                    l.is_active, l.created_at, l.updated_at
             FROM locations AS l
             INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
             ORDER BY l.is_active DESC, l.location_name, l.purok_zone"
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    public function find(int $locationId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT l.location_id, l.location_name, l.purok_zone, l.landmark, l.latitude, l.longitude,
                    l.susceptibility_class, l.hazard_source_name, l.hazard_source_url,
                    l.hazard_source_date, l.is_active
             FROM locations AS l INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
             WHERE l.location_id = :location_id
               AND b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
             LIMIT 1"
        );
        $statement->execute(['location_id' => $locationId]);
        $location = $statement->fetch();
        return $location ?: null;
    }

    public function create(array $location): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO locations
                (barangay_id, location_name, purok_zone, landmark, latitude, longitude,
                 susceptibility_class, hazard_source_name, hazard_source_url, hazard_source_date)
             VALUES
                (:barangay_id, :location_name, :purok_zone, :landmark, :latitude, :longitude,
                 :susceptibility_class, :hazard_source_name, :hazard_source_url, :hazard_source_date)'
        );
        $statement->execute($location + ['barangay_id' => $this->studyBarangayId()]);
    }

    public function update(int $locationId, array $location): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE locations SET
                location_name = :location_name,
                purok_zone = :purok_zone,
                landmark = :landmark,
                latitude = :latitude,
                longitude = :longitude,
                susceptibility_class = :susceptibility_class,
                hazard_source_name = :hazard_source_name,
                hazard_source_url = :hazard_source_url,
                hazard_source_date = :hazard_source_date
             WHERE location_id = :location_id
               AND barangay_id = (SELECT barangay_id FROM barangays
                                  WHERE barangay_name = 'Barangay Irisan'
                                    AND city_name = 'Baguio City' LIMIT 1)"
        );
        $location['location_id'] = $locationId;
        $statement->execute($location);
        return $statement->rowCount() > 0 || $this->find($locationId) !== null;
    }

    public function setActive(int $locationId, bool $active): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE locations SET is_active = :is_active
             WHERE location_id = :location_id
               AND barangay_id = (SELECT barangay_id FROM barangays
                                  WHERE barangay_name = 'Barangay Irisan'
                                    AND city_name = 'Baguio City' LIMIT 1)"
        );
        $statement->execute([
            'is_active' => $active ? 1 : 0,
            'location_id' => $locationId,
        ]);
        return $statement->rowCount() > 0 || $this->find($locationId) !== null;
    }

}
