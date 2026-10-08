<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/assessment.php';

// Users
function create_user(string $fullName, string $username, string $email, string $phone, string $password): int {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, username, email, phone, password, role)
         VALUES (?, ?, ?, ?, ?, 'user')"
    );
    $stmt->execute([$fullName, $username, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
    return (int)$pdo->lastInsertId();
}

// Locations
function get_locations(): array {
    $pdo = db();
    return $pdo->query("SELECT * FROM locations WHERE active = 1 ORDER BY name")->fetchAll();
}

function get_location(int $id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_or_create_location(float $lat, float $lng, ?string $landmark = null): array {
    if (!is_finite($lat) || !is_finite($lng) || !is_in_irisan($lat, $lng) || strlen((string)$landmark) > 255) {
        throw new InvalidArgumentException('Invalid Irisan location or address.');
    }
    $pdo = db();
    $lat = round($lat, 5);
    $lng = round($lng, 5);

    $stmt = $pdo->prepare("SELECT * FROM locations WHERE lat = ? AND lng = ?");
    $stmt->execute([$lat, $lng]);
    if ($loc = $stmt->fetch()) {
        if (!$loc['active']) throw new InvalidArgumentException('This monitoring point was removed by an administrator.');
        if (empty($loc['landmark']) && trim((string)$landmark) !== '') {
            $stmt = $pdo->prepare("UPDATE locations SET landmark = ? WHERE id = ? AND (landmark IS NULL OR landmark = '')");
            $stmt->execute([trim((string)$landmark), $loc['id']]);
            return get_location((int)$loc['id']) ?? $loc;
        }
        return $loc;
    }

    $name = sprintf('Irisan %.5f, %.5f', $lat, $lng);
    $stmt = $pdo->prepare(
        "INSERT INTO locations (name, purok, landmark, lat, lng, susceptibility, active)
         VALUES (?, 'Map point', ?, ?, ?, 'unknown', 1)"
    );
    $landmark = trim((string)$landmark);
    $stmt->execute([$name, $landmark !== '' ? $landmark : null, $lat, $lng]);
    return get_location((int)$pdo->lastInsertId());
}

function deactivate_location(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE locations SET active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}

// Readings
function get_latest_reading(int $locationId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT * FROM events
         WHERE location_id = ? AND type = 'reading' AND archived = 0
         ORDER BY observed_at DESC, id DESC LIMIT 1"
    );
    $stmt->execute([$locationId]);
    $row = $stmt->fetch();
    return $row ? assess_reading($row) : null;
}

function get_readings(int $locationId, int $limit = 50): array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT * FROM events
         WHERE location_id = ? AND type = 'reading' AND archived = 0
         ORDER BY observed_at DESC, id DESC LIMIT ?"
    );
    $stmt->bindValue(1, $locationId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as $index => $row) {
        $rows[$index] = assess_reading($row);
    }
    return $rows;
}

function get_all_readings(int $limit = 100): array {
    $pdo = db();
    $rows = $pdo->query(
        "SELECT e.*, l.name as location_name, l.purok, l.lat, l.lng
         FROM events e
         JOIN locations l ON l.id = e.location_id
         WHERE e.type = 'reading' AND e.archived = 0 AND l.active = 1
         ORDER BY e.observed_at DESC, e.id DESC
         LIMIT " . max(1, (int)$limit)
    )->fetchAll();
    foreach ($rows as $index => $row) {
        $rows[$index] = assess_reading($row);
    }
    return $rows;
}

function create_reading(array $data): int {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO events (
            location_id, type, rainfall_1h, rainfall_24h, rainfall_72h, risk_level,
            rainfall_forecast_24h, precipitation_probability_24h,
            soil_moisture_9_27cm, soil_moisture_27_81cm,
            temperature, humidity, wind_speed, weather_code, observed_at, source, rule_version, rainfall_window_end, provider_payload
         ) VALUES (?, 'reading', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['location_id'],
        $data['rainfall_1h'] ?? null,
        $data['rainfall_24h'] ?? null,
        $data['rainfall_72h'] ?? null,
        $data['risk_level'] ?? null,
        $data['rainfall_forecast_24h'] ?? null,
        $data['precipitation_probability_24h'] ?? null,
        $data['soil_moisture_9_27cm'] ?? null,
        $data['soil_moisture_27_81cm'] ?? null,
        $data['temperature'] ?? null,
        $data['humidity'] ?? null,
        $data['wind_speed'] ?? null,
        $data['weather_code'] ?? null,
        $data['observed_at'],
        $data['source'] ?? 'openmeteo',
        $data['rule_version'] ?? RiskAnalyzer::VERSION,
        $data['rainfall_window_end'] ?? null,
        $data['provider_payload'] ?? null
    ]);
    return (int)$pdo->lastInsertId();
}