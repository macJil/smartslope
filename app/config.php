<?php
declare(strict_types=1);

// Ultra-simplified configuration - combines config.php, paths.php, and helpers

function load_env_file(): void {
    $envFile = __DIR__ . '/../.env';
    if (!is_file($envFile)) {
        return;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
        if (!array_key_exists($name, $_SERVER)) {
            $_SERVER[$name] = $value;
        }
        putenv("{$name}={$value}");
    }
}

load_env_file();

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================
$config = [
    'db_host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'db_port' => (int)($_ENV['DB_PORT'] ?? 3306),
    'db_name' => $_ENV['DB_DATABASE'] ?? 'smartslope_mvp',
    'db_user' => $_ENV['DB_USERNAME'] ?? 'root',
    'db_pass' => $_ENV['DB_PASSWORD'] ?? '',
    'base_path' => $_ENV['APP_BASE_PATH'] ?? '',
];

// ============================================================================
// PATHS
// ============================================================================
define('APP_ROOT', dirname(__DIR__));
define('APP_BASE_PATH', $config['base_path']);

// ============================================================================
// DATABASE CONNECTION
// ============================================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        global $config;
        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

// Escape output for HTML
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Get POST value safely
function post(string $key, $default = '') {
    return $_POST[$key] ?? $default;
}

// Get GET value safely
function get(string $key, $default = '') {
    return $_GET[$key] ?? $default;
}

// Build URL
function url(string $path = ''): string {
    return APP_BASE_PATH . '/' . ltrim($path, '/');
}

// Redirect
function redirect(string $path): void {
    header("Location: " . url($path), true, 303);
    exit;
}

// Display local datetime
function local_date(?string $value): string {
    if (!$value) return '';
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    return $date ? $date->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A') : $value;
}

// Start session
function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params(['httponly' => true, 'secure' => $isHttps, 'samesite' => 'Lax']);
        session_start();
    }
}

// Check if logged in
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

// Check if admin
function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

// Require user login
function require_login(): void {
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

// Require admin
function require_admin(): void {
    if (!is_admin()) {
        redirect('index.php');
    }
}

// Set flash message
function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}

// ============================================================================
// AUTHENTICATION FUNCTIONS
// ============================================================================

// Hash password
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

// Authenticate user
function authenticate(string $username, string $password): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && verify_password($password, $user['password'])) {
        return $user;
    }
    return null;
}

// Create user
function create_user(string $fullName, string $username, string $email, string $phone, string $password): int {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, username, email, phone, password, role)
         VALUES (?, ?, ?, ?, ?, 'user')"
    );
    $stmt->execute([$fullName, $username, $email, $phone, hash_password($password)]);
    return (int)$pdo->lastInsertId();
}

// ============================================================================
// DATA ACCESS FUNCTIONS
// ============================================================================

// Get all active locations
function get_locations(): array {
    $pdo = db();
    return $pdo->query("SELECT * FROM locations WHERE active = 1 ORDER BY name")->fetchAll();
}

// Get location by ID
function get_location(int $id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// Create location
function create_location(array $data): int {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO locations (name, purok, landmark, lat, lng, susceptibility, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)"
    );
    $stmt->execute([
        $data['name'],
        $data['purok'] ?? null,
        $data['landmark'] ?? null,
        $data['lat'] ?? null,
        $data['lng'] ?? null,
        $data['susceptibility'] ?? 'unknown'
    ]);
    return (int)$pdo->lastInsertId();
}

// Update location
function update_location(int $id, array $data): bool {
    $pdo = db();
    $sets = [];
    $values = [];
    foreach ($data as $key => $value) {
        $sets[] = "$key = ?";
        $values[] = $value;
    }
    $values[] = $id;
    $stmt = $pdo->prepare("UPDATE locations SET " . implode(',', $sets) . " WHERE id = ?");
    return $stmt->execute($values);
}

// Deactivate location
function deactivate_location(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE locations SET active = 0 WHERE id = ?");
    return $stmt->execute([$id]);
}

// Get latest reading for location
function get_latest_reading(int $locationId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT * FROM events
         WHERE location_id = ? AND type = 'reading' AND archived = 0
         ORDER BY observed_at DESC, id DESC LIMIT 1"
    );
    $stmt->execute([$locationId]);
    return $stmt->fetch() ?: null;
}

// Get readings for location
function get_readings(int $locationId, int $limit = 50): array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT * FROM events
         WHERE location_id = ? AND type = 'reading' AND archived = 0
         ORDER BY observed_at DESC, id DESC LIMIT ?"
    );
    $stmt->execute([$locationId, $limit]);
    return $stmt->fetchAll();
}

// Get all recent readings
function get_all_readings(int $limit = 100): array {
    $pdo = db();
    return $pdo->query(
        "SELECT e.*, l.name as location_name, l.purok, l.lat, l.lng
         FROM events e
         JOIN locations l ON l.id = e.location_id
         WHERE e.type = 'reading' AND e.archived = 0 AND l.active = 1
         ORDER BY e.observed_at DESC, e.id DESC
         LIMIT $limit"
    )->fetchAll();
}

// Create reading
function create_reading(array $data): int {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO events (
            location_id, type, rainfall_1h, rainfall_24h, rainfall_72h, risk_level,
            rainfall_forecast_24h, precipitation_probability_24h,
            soil_moisture_9_27cm, soil_moisture_27_81cm,
            temperature, humidity, wind_speed, weather_code, observed_at, source
         ) VALUES (?, 'reading', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['location_id'],
        $data['rainfall_1h'] ?? null,
        $data['rainfall_24h'] ?? null,
        $data['rainfall_72h'] ?? null,
        $data['risk_level'] ?? 'low',
        $data['rainfall_forecast_24h'] ?? null,
        $data['precipitation_probability_24h'] ?? null,
        $data['soil_moisture_9_27cm'] ?? null,
        $data['soil_moisture_27_81cm'] ?? null,
        $data['temperature'] ?? null,
        $data['humidity'] ?? null,
        $data['wind_speed'] ?? null,
        $data['weather_code'] ?? null,
        $data['observed_at'],
        $data['source'] ?? 'openmeteo'
    ]);
    return (int)$pdo->lastInsertId();
}

// Calculate risk level
function calculate_risk(?float $r1, ?float $r24, ?float $r72): string {
    $r1 = $r1 ?? 0;
    $r24 = $r24 ?? 0;
    $r72 = $r72 ?? 0;

    if ($r1 >= 50 || $r24 >= 100 || $r72 >= 150) return 'high';
    if ($r1 >= 25 || $r24 >= 50 || $r72 >= 100) return 'medium';
    if ($r1 >= 10 || $r24 >= 25 || $r72 >= 50) return 'normal';
    return 'low';
}

// Get all reports
function get_reports(?string $status = null): array {
    $pdo = db();
    $sql = "SELECT e.*, l.name as location_name, l.purok,
                   u.full_name as reporter_name
            FROM events e
            JOIN locations l ON l.id = e.location_id
            LEFT JOIN users u ON u.id = e.user_id
            WHERE e.type = 'report' AND l.active = 1";
    $params = [];
    if ($status) {
        $sql .= " AND e.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY e.created_at DESC, e.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get pending report counts by location
function get_pending_counts(): array {
    $pdo = db();
    $stmt = $pdo->query(
        "SELECT location_id, COUNT(*) as count
         FROM events
         WHERE type = 'report' AND status = 'pending'
         GROUP BY location_id"
    );
    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(int)$row['location_id']] = (int)$row['count'];
    }
    return $counts;
}

// Create report
function create_report(array $data): int {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO events (
            location_id, user_id, type, message, contact_phone, contact_email, house_landmark, status
         ) VALUES (?, ?, 'report', ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([
        $data['location_id'],
        $data['user_id'] ?? null,
        $data['message'],
        $data['contact_phone'],
        $data['contact_email'] ?? null,
        $data['house_landmark'] ?? null
    ]);
    return (int)$pdo->lastInsertId();
}

// Update report status
function update_report(int $id, string $status, int $adminId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE events SET status = ?, user_id = ? WHERE id = ? AND type = 'report'");
    return $stmt->execute([$status, $adminId, $id]);
}

// Delete report
function delete_report(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND type = 'report'");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}

// Archive reading
function archive_reading(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE events SET archived = 1 WHERE id = ? AND type = 'reading'");
    return $stmt->execute([$id]);
}

// ============================================================================
// WEATHER API FUNCTIONS
// ============================================================================

// Fetch weather from Open-Meteo
function fetch_weather(float $lat, float $lng): array {
    $url = "https://api.open-meteo.com/v1/forecast?";
    $params = [
        'latitude' => $lat,
        'longitude' => $lng,
        'current' => 'temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m',
        'hourly' => 'precipitation,precipitation_probability,soil_moisture_9_to_27cm,soil_moisture_27_to_81cm',
        'past_days' => 3,
        'forecast_hours' => 25,
        'timezone' => 'UTC'
    ];

    $ch = curl_init($url . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true) ?: [];
}

// Calculate rainfall from hourly data
function calculate_weather_indicators(array $hourly, string $currentTime): array {
    $indicators = [
        'rainfall_1h' => null,
        'rainfall_24h' => null,
        'rainfall_72h' => null,
        'rainfall_forecast_24h' => null,
        'precipitation_probability_24h' => null,
        'soil_moisture_9_27cm' => null,
        'soil_moisture_27_81cm' => null,
    ];

    if (!isset($hourly['time'], $hourly['precipitation'])) {
        return $indicators;
    }

    $currentTimestamp = strtotime($currentTime . ' UTC');
    if ($currentTimestamp === false) {
        return $indicators;
    }

    $rainfallTotals = [1 => 0.0, 24 => 0.0, 72 => 0.0];
    $rainfallCounts = [1 => 0, 24 => 0, 72 => 0];
    $forecastRainfall = 0.0;
    $forecastCount = 0;
    $maxPrecipitationProbability = null;
    $nearestSoilReading = null;

    foreach ($hourly['time'] as $index => $time) {
        $timestamp = strtotime($time . ' UTC');
        $precipitation = $hourly['precipitation'][$index] ?? null;
        if ($timestamp === false || !is_numeric($precipitation)) {
            continue;
        }

        $hoursFromCurrent = ($timestamp - $currentTimestamp) / 3600;
        if ($hoursFromCurrent <= 0) {
            $hoursAgo = -$hoursFromCurrent;
            foreach ([1, 24, 72] as $windowHours) {
                if ($hoursAgo < $windowHours) {
                    $rainfallTotals[$windowHours] += (float)$precipitation;
                    $rainfallCounts[$windowHours]++;
                }
            }

            if ($hoursAgo <= 3 && ($nearestSoilReading === null || $hoursAgo < $nearestSoilReading)) {
                $nearestSoilReading = $hoursAgo;
                foreach ([
                    'soil_moisture_9_to_27cm' => 'soil_moisture_9_27cm',
                    'soil_moisture_27_to_81cm' => 'soil_moisture_27_81cm',
                ] as $apiField => $indicatorField) {
                    $value = $hourly[$apiField][$index] ?? null;
                    $indicators[$indicatorField] = is_numeric($value) ? round((float)$value, 4) : null;
                }
            }
        } elseif ($hoursFromCurrent <= 24) {
            $forecastRainfall += (float)$precipitation;
            $forecastCount++;
            $probability = $hourly['precipitation_probability'][$index] ?? null;
            if (is_numeric($probability)) {
                $maxPrecipitationProbability = max($maxPrecipitationProbability ?? 0, (int)$probability);
            }
        }
    }

    foreach ($rainfallTotals as $windowHours => $total) {
        if ($rainfallCounts[$windowHours] > 0) {
            $indicators['rainfall_' . $windowHours . 'h'] = round($total, 2);
        }
    }

    if ($forecastCount > 0) {
        $indicators['rainfall_forecast_24h'] = round($forecastRainfall, 2);
    }
    $indicators['precipitation_probability_24h'] = $maxPrecipitationProbability;

    return $indicators;
}

// Get or create location for coordinates
function get_or_create_location(float $lat, float $lng): array {
    $pdo = db();

    // Round to 5 decimal places
    $lat = round($lat, 5);
    $lng = round($lng, 5);

    // Check if exists
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE lat = ? AND lng = ?");
    $stmt->execute([$lat, $lng]);

    if ($loc = $stmt->fetch()) {
        if (!$loc['active']) {
            $pdo->prepare("UPDATE locations SET active = 1 WHERE id = ?")->execute([$loc['id']]);
        }
        return $loc;
    }

    // Create new
    $name = sprintf('Irisan %.5f, %.5f', $lat, $lng);
    $stmt = $pdo->prepare(
        "INSERT INTO locations (name, purok, lat, lng, susceptibility, active)
         VALUES (?, 'Map point', ?, ?, 'unknown', 1)"
    );
    $stmt->execute([$name, $lat, $lng]);
    return get_location((int)$pdo->lastInsertId());
}

// ============================================================================
// MAP BOUNDARY CHECK
// ============================================================================

// Simple bounds check for Barangay Irisan
function is_in_irisan(float $lat, float $lng): bool {
    return $lat >= 16.407 && $lat <= 16.435 && $lng >= 120.543 && $lng <= 120.576;
}

// ============================================================================
// CSV EXPORT
// ============================================================================

function export_readings_csv(array $readings): string {
    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, [
        'ID', 'Location', 'Risk', '1h Rainfall', '24h Rainfall', '72h Rainfall',
        '24h Forecast Rainfall', '24h Precipitation Probability',
        'Soil Moisture 9-27cm', 'Soil Moisture 27-81cm', 'Observed At'
    ]);

    foreach ($readings as $r) {
        fputcsv($stream, [
            $r['id'],
            $r['location_name'] ?? '',
            $r['risk_level'],
            $r['rainfall_1h'] ?? '',
            $r['rainfall_24h'] ?? '',
            $r['rainfall_72h'] ?? '',
            $r['rainfall_forecast_24h'] ?? '',
            $r['precipitation_probability_24h'] ?? '',
            $r['soil_moisture_9_27cm'] ?? '',
            $r['soil_moisture_27_81cm'] ?? '',
            $r['observed_at']
        ]);
    }

    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    return $csv;
}
