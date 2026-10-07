<?php
// Simple Reading Model
class Reading {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Get latest reading for a location
    public function getLatestByLocation($locationId) {
        $sql = "SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*
                FROM events e JOIN readings r ON r.event_id = e.id
                WHERE e.location_id = :location_id AND e.type = 'reading' AND r.archived = 0
                ORDER BY r.observed_at DESC, e.id DESC LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['location_id' => $locationId]);
        return $stmt->fetch() ?: null;
    }
    
    // Get readings for a location
    public function getByLocation($locationId, $limit = 50) {
        $sql = "SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*
                FROM events e JOIN readings r ON r.event_id = e.id
                WHERE e.location_id = :location_id AND e.type = 'reading' AND r.archived = 0
                ORDER BY r.observed_at DESC, e.id DESC LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':location_id', $locationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Get all recent readings
    public function getAllRecent($limit = 100) {
        $sql = "SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*,
                        l.name as location_name, l.purok, l.lat, l.lng
                FROM events e
                JOIN readings r ON r.event_id = e.id
                JOIN locations l ON l.id = e.location_id
                WHERE e.type = 'reading' AND r.archived = 0 AND l.active = 1
                ORDER BY r.observed_at DESC, e.id DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', max(1, (int)$limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Create new reading
    public function create($data) {
        $this->pdo->beginTransaction();
        try {
            // Create event
            $event = $this->pdo->prepare("INSERT INTO events (location_id, user_id, type) VALUES (:location_id, :user_id, 'reading')");
            $event->execute([
                'location_id' => $data['location_id'],
                'user_id' => $data['user_id'] ?? null
            ]);
            $eventId = $this->pdo->lastInsertId();
            
            // Create reading
            $sql = "INSERT INTO readings (event_id, rainfall_1h, rainfall_24h, rainfall_72h, risk_level,
                    rainfall_forecast_24h, precipitation_probability_24h, soil_moisture_9_27cm,
                    soil_moisture_27_81cm, temperature, humidity, wind_speed, weather_code, observed_at,
                    source, rule_version, rainfall_window_end, provider_payload)
                    VALUES (:event_id, :rainfall_1h, :rainfall_24h, :rainfall_72h, :risk_level, 
                    :rainfall_forecast_24h, :precipitation_probability_24h, :soil_moisture_9_27cm,
                    :soil_moisture_27_81cm, :temperature, :humidity, :wind_speed, :weather_code, 
                    :observed_at, :source, :rule_version, :rainfall_window_end, :provider_payload)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'event_id' => $eventId,
                'rainfall_1h' => $data['rainfall_1h'] ?? null,
                'rainfall_24h' => $data['rainfall_24h'] ?? null,
                'rainfall_72h' => $data['rainfall_72h'] ?? null,
                'risk_level' => $data['risk_level'] ?? null,
                'rainfall_forecast_24h' => $data['rainfall_forecast_24h'] ?? null,
                'precipitation_probability_24h' => $data['precipitation_probability_24h'] ?? null,
                'soil_moisture_9_27cm' => $data['soil_moisture_9_27cm'] ?? null,
                'soil_moisture_27_81cm' => $data['soil_moisture_27_81cm'] ?? null,
                'temperature' => $data['temperature'] ?? null,
                'humidity' => $data['humidity'] ?? null,
                'wind_speed' => $data['wind_speed'] ?? null,
                'weather_code' => $data['weather_code'] ?? null,
                'observed_at' => $data['observed_at'],
                'source' => $data['source'] ?? 'openmeteo',
                'rule_version' => $data['rule_version'] ?? '1.0',
                'rainfall_window_end' => $data['rainfall_window_end'] ?? null,
                'provider_payload' => $data['provider_payload'] ?? null
            ]);
            
            $this->pdo->commit();
            return $eventId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Archive reading
    public function archive($eventId) {
        $sql = "UPDATE readings r JOIN events e ON e.id = r.event_id
                SET r.archived = 1 WHERE e.id = :event_id AND e.type = 'reading' AND r.archived = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);
        return $stmt->rowCount() === 1;
    }
}
