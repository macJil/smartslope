<?php
// Simple Location Model
class Location {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Get all active locations
    public function getAllActive() {
        $sql = "SELECT * FROM locations WHERE active = 1 ORDER BY name";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    // Get location by ID
    public function getById($id) {
        $sql = "SELECT * FROM locations WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    // Create new location
    public function create($name, $purok, $landmark, $lat, $lng, $susceptibility = 'unknown') {
        $sql = "INSERT INTO locations (name, purok, landmark, lat, lng, susceptibility, active)
                VALUES (:name, :purok, :landmark, :lat, :lng, :susceptibility, 1)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'purok' => $purok,
            'landmark' => $landmark,
            'lat' => $lat,
            'lng' => $lng,
            'susceptibility' => $susceptibility
        ]);
        return $this->pdo->lastInsertId();
    }
    
    // Deactivate location
    public function deactivate($id) {
        $sql = "UPDATE locations SET active = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }
    
    // Check if coordinates are in Irisan
    public function isInIrisan($lat, $lng) {
        $geo = json_decode((string)file_get_contents(__DIR__ . '/assets/map/irisan.geojson'), true);
        if (!$geo) return false;
        
        $geometry = $geo['type'] === 'FeatureCollection'
            ? ($geo['features'][0]['geometry'] ?? null)
            : ($geo['geometry'] ?? $geo);
        
        $rings = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : ($geometry['coordinates'] ?? []);
        
        foreach ($rings as $polygon) {
            $inside = false;
            foreach ($polygon as $ringIndex => $ring) {
                $crosses = false;
                for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
                    $x1 = $ring[$i][0]; $y1 = $ring[$i][1];
                    $x2 = $ring[$j][0]; $y2 = $ring[$j][1];
                    if (($y1 > $lat) !== ($y2 > $lat) &&
                        $lng < ($x2 - $x1) * ($lat - $y1) / ($y2 - $y1) + $x1) {
                        $crosses = !$crosses;
                    }
                }
                if ($ringIndex === 0) $inside = $crosses;
                elseif ($crosses) $inside = false;
            }
            if ($inside) return true;
        }
        return false;
    }
}
