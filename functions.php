<?php
// Application functions. Pages include config.php and this file directly.

// Turn database failures into useful messages without showing SQL or passwords.
function database_error_message(PDOException $error): string {
    $code = (int)($error->errorInfo[1] ?? 0);
    if ($code === 1049) return 'Database not found. Check DB_DATABASE in .env against your MySQL database name.';
    if ($code === 1045 || $code === 1044) return 'Database access denied. Check DB_USERNAME and DB_PASSWORD in .env.';
    if ($code === 2002 || $code === 2003) return 'Cannot connect to MySQL. Start the database server and check DB_HOST and DB_PORT in .env.';
    if ($error->getCode() === '42S02') return 'A required SmartSlope table is missing. Select your SmartSlope database in .env; use database/schema.sql only for a new database.';
    if ($error->getCode() === '42S22') return 'The database columns do not match this SmartSlope version. Check the selected database and run the documented migration.';
    if (str_contains($error->getMessage(), 'could not find driver')) return 'PHP needs the PDO MySQL extension enabled. Check the PHP version used by Herd or XAMPP.';
    return 'The database operation failed. Check the PHP error log for the recorded database error.';
}

function show_application_error(Throwable $error): void {
    error_log('SmartSlope: ' . $error->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    $message = 'This page could not load. Check the PHP error log for details.';
    if ($error instanceof PDOException) $message = database_error_message($error);
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
}
set_exception_handler('show_application_error');

// Basic input, output and map helpers

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Get POST value safely

function post(string $key, $default = '')
{
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

// Get GET value safely

function get(string $key, $default = '')
{
    $value = $_GET[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

// Build URL

// Redirect

function redirect(string $path): void
{
    header("Location: " . $path, true, 303);
    exit;
}

// Display local datetime

function local_date(?string $value): string
{
    if (!$value) {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    return $date ? $date->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A') : $value;
}

// Store or retrieve a flash message

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}

// Test whether a coordinate is inside the Irisan polygon.

function is_in_irisan(float $lat, float $lng): bool
{
    $geo = json_decode((string)file_get_contents(__DIR__ . '/assets/map/irisan.geojson'), true);
    if (!is_array($geo)) {
        return false;
    }
    $geometry = $geo['type'] === 'FeatureCollection'
        ? ($geo['features'][0]['geometry'] ?? null)
        : ($geo['geometry'] ?? $geo);
    $rings = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : ($geometry['coordinates'] ?? []);
    foreach ($rings as $polygon) {
        $inside = false;
        foreach ($polygon as $ringIndex => $ring) {
            $crosses = false;
            for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
                [$x1, $y1] = $ring[$i];
                [$x2, $y2] = $ring[$j];
                if (
                    ($y1 > $lat) !== ($y2 > $lat) &&
                    $lng < ($x2 - $x1) * ($lat - $y1) / ($y2 - $y1) + $x1
                ) {
                    $crosses = !$crosses;
                }
            }
            if ($ringIndex === 0) {
                $inside = $crosses;
            } elseif ($crosses) {
                $inside = false;
            }
        }
        if ($inside) {
            return true;
        }
    }
    return false;
}

// Login sessions and roles

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params(['httponly' => true, 'secure' => $isHttps, 'samesite' => 'Lax']);
        session_start();
    }
}


// Check if logged in

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

// Check if admin

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

// Require user login

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

// Require admin

function require_admin(): void
{
    if (!is_admin()) {
        redirect('index.php');
    }
}

// RiskAnalyzer: the small OOP example

/** Prototype rainfall screening. Thresholds are not calibrated for Irisan. */
final class RiskAnalyzer
{
    public const VERSION = 'prototype-1';
    public const THRESHOLDS = [
        'high' => [50, 100, 150],
        'medium' => [25, 50, 100],
        'normal' => [10, 25, 50],
    ];

    public function analyze(?float $oneHour, ?float $day, ?float $threeDays): ?string
    {
        return $this->explain($oneHour, $day, $threeDays)['category'];
    }

    public function explain(?float $oneHour, ?float $day, ?float $threeDays): array
    {
        $values = [$oneHour, $day, $threeDays];
        foreach ($values as $value) {
            if ($value === null || !is_finite($value) || $value < 0) {
                return ['category' => null, 'reasons' => ['Rainfall history is incomplete or invalid.'], 'rule_version' => self::VERSION];
            }
        }
        $hours = [1, 24, 72];
        foreach (self::THRESHOLDS as $level => $limits) {
            $reasons = [];
            foreach ($limits as $i => $limit) {
                if ($values[$i] >= $limit) {
                    $reasons[] = "{$hours[$i]}-hour rainfall ({$values[$i]} mm) reached the prototype {$level} threshold ({$limit} mm).";
                }
            }
            if ($reasons) return ['category' => $level, 'reasons' => $reasons, 'rule_version' => self::VERSION];
        }
        return ['category' => 'low', 'reasons' => ['Rainfall is below all prototype thresholds. Low does not mean the slope is safe.'], 'rule_version' => self::VERSION];
    }
}

// Reading assessment

function utc_timestamp(?string $value): ?int
{
    if (!$value) {
        return null;
    }
    foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
        if ($date && $date->format($format) === $value) {
            return $date->getTimestamp();
        }
    }
    return null;
}

function finite_number($value, ?float $minimum = null, ?float $maximum = null): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float)$value;
    if (
        !is_finite($number) || ($minimum !== null && $number < $minimum) ||
        ($maximum !== null && $number > $maximum)
    ) {
        return null;
    }
    return $number;
}

function calculate_risk(?float $r1, ?float $r24, ?float $r72): string
{
    $level = (new RiskAnalyzer())->analyze($r1, $r24, $r72);
    if ($level === null) {
        throw new RuntimeException('Rainfall history is incomplete or invalid.');
    }
    return $level;
}

/** Request-time freshness; the legacy stale column is not used as a clock. */
function reading_assessment(?array $reading, array $location = [], ?int $now = null): array
{
    global $config;
    $now = $now ?? time();
    $result = [
        'category' => null, 'calculated_category' => null, 'current_category' => null,
        'basis' => 'rainfall', 'data_status' => 'unavailable', 'adjusted' => false,
        'reasons' => ['No saved reading is available.'], 'rule_version' => RiskAnalyzer::VERSION,
        'susceptibility' => $location['susceptibility'] ?? 'unknown',
        'observed_at' => $reading['observed_at'] ?? null,
        'retrieved_at' => $reading['created_at'] ?? null,
        'rainfall_window_end' => null, 'provenance_status' => 'unavailable', 'classification' => 'CALCULATED',
    ];
    if (!$reading) {
        return $result;
    }
    $analysis = (new RiskAnalyzer())->explain(
        finite_number($reading['rainfall_1h'] ?? null, 0),
        finite_number($reading['rainfall_24h'] ?? null, 0),
        finite_number($reading['rainfall_72h'] ?? null, 0)
    );
    $values = [];
    foreach (['rainfall_1h', 'rainfall_24h', 'rainfall_72h'] as $key) {
        $values[] = finite_number($reading[$key] ?? null, 0);
    }
    if (!in_array(null, $values, true) && ($values[0] > $values[1] || $values[1] > $values[2])) {
        $analysis = ['category' => null, 'reasons' => ['Rainfall totals are inconsistent: 1h must not exceed 24h, and 24h must not exceed 72h.']];
    }
    if (!empty($reading['rule_version']) && $reading['rule_version'] !== RiskAnalyzer::VERSION) {
        $analysis = ['category' => null, 'reasons' => ['This record uses a different rule version; a current assessment is unavailable.']];
    }
    $observedTimestamp = utc_timestamp($reading['observed_at'] ?? null);
    $result['rainfall_window_end'] = $reading['rainfall_window_end'] ?? ($observedTimestamp === null ? null : gmdate('Y-m-d H:i:s', intdiv($observedTimestamp, 3600) * 3600));
    $result['provenance_status'] = empty($reading['provider_payload']) ? 'legacy_without_hourly_inputs' : 'saved_provider_inputs';
    $result['classification'] = 'CALCULATED';
    $result['calculated_category'] = $analysis['category'];
    $result['reasons'] = $analysis['reasons'];
    $stored = $reading['risk_level'] ?? null;
    $validStored = in_array($stored, ['low', 'normal', 'medium', 'high'], true);
    $observed = utc_timestamp($reading['observed_at'] ?? null);
    if (
        $analysis['category'] === null || !$validStored || $observed === null ||
        $observed > $now + ($config['future_tolerance_seconds'] ?? 300)
    ) {
        $result['data_status'] = 'incomplete';
        $result['reasons'][] = 'A current assessment is unavailable because saved values or the observation time are invalid.';
        return $result;
    }
    $result['category'] = $stored;
    $result['adjusted'] = $stored !== $analysis['category'] || !empty($reading['adjustment_log']);
    if ($result['adjusted']) {
        $result['reasons'][] = 'Administrator-adjusted category; calculated rainfall category: ' . $analysis['category'] . '.';
    }
    $result['data_status'] = $now - $observed > ($config['freshness_seconds'] ?? 10800) ? 'outdated' : 'current';
    if ($result['data_status'] === 'current') {
        $result['current_category'] = $stored;
    } else {
        $result['reasons'][] = 'This is the last known category; refresh to obtain current data.';
    }
    return $result;
}

function assess_reading(array $reading): array
{
    $reading['assessment'] = reading_assessment($reading);
    // Compatibility for existing templates. Never write this derived value back.
    $reading['stale'] = $reading['assessment']['data_status'] === 'current' ? 0 : 1;
    return $reading;
}

// PDO database functions

function create_user(string $fullName, string $username, string $email, string $phone, string $password): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, username, email, phone, password, role)
         VALUES (?, ?, ?, ?, ?, 'user')"
    );
    $stmt->execute([$fullName, $username, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
    return (int)$pdo->lastInsertId();
}

// ============================================================================
// DATA ACCESS FUNCTIONS
// ============================================================================

// Get all active locations

function get_locations(): array
{
    $pdo = db();
    return $pdo->query("SELECT * FROM locations WHERE active = 1 ORDER BY name")->fetchAll();
}

// Get location by ID

function get_location(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// Deactivate location

function deactivate_location(int $id): bool
{
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE locations SET active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}

// Get latest reading for location

function get_latest_reading(int $locationId): ?array
{
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

// Get readings for location

function get_readings(int $locationId, int $limit = 50): array
{
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

// Get all recent readings

function get_all_readings(int $limit = 100): array
{
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

// Create reading

function create_reading(array $data): int
{
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


function get_reports(?string $status = null): array
{
    $pdo = db();
    $sql = "SELECT e.*, l.name as location_name, l.purok, l.landmark, l.lat, l.lng,
                   l.susceptibility, u.full_name as reporter_name
            FROM events e
            JOIN locations l ON l.id = e.location_id
            LEFT JOIN users u ON u.id = e.user_id
            WHERE e.type = 'report'";
    $params = [];
    if ($status) {
        $sql .= " AND e.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY e.created_at DESC, e.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();
    $assessments = [];
    foreach ($reports as &$report) {
        $id = (int)$report['location_id'];
        if (!isset($assessments[$id])) {
            $assessments[$id] = reading_assessment(get_latest_reading($id), $report);
        }
        $report['location_assessment'] = $assessments[$id];
        $report['location_risk_level'] = $assessments[$id]['current_category'] ?? 'unavailable';
    }
    unset($report);
    return $reports;
}

// Get pending report counts by location

function get_pending_counts(): array
{
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

function create_report(array $data): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO events (
            location_id, user_id, type, message, contact_phone, contact_email, house_landmark, status, report_type, occurred_at
         ) VALUES (?, ?, 'report', ?, ?, ?, ?, 'pending', ?, ?)"
    );
    $stmt->execute([
        $data['location_id'],
        $data['user_id'] ?? null,
        $data['message'],
        $data['contact_phone'],
        $data['contact_email'] ?? null,
        $data['house_landmark'] ?? null,
        $data['report_type'] ?? 'other',
        $data['occurred_at'] ?? null
    ]);
    return (int)$pdo->lastInsertId();
}

// Update report status

function update_report(int $id, string $status, int $adminId): bool
{
    require_awareness_schema();
    $pdo = db();
    if (!in_array($status, ['reviewed','resolved'], true)) {
        throw new InvalidArgumentException('Invalid review status.');
    }
    $stmt = $pdo->prepare("UPDATE events SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ? AND type = 'report' AND status = ?");
    $stmt->execute([$status, $adminId, $id, $status === 'reviewed' ? 'pending' : 'reviewed']);
    return $stmt->rowCount() === 1;
}

// Delete report

function delete_report(int $id): bool
{
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND type = 'report'");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}

// Archive reading

function archive_reading(int $id): bool
{
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE events SET archived = 1 WHERE id = ? AND type = 'reading' AND archived = 0");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}


function update_reading_risk(int $readingId, string $risk, int $adminId, string $reason): void
{
    require_awareness_schema();
    if (!in_array($risk, ['low','normal','medium','high'], true) || trim($reason) === '' || strlen($reason) > 500) {
        throw new InvalidArgumentException('Valid category and adjustment reason are required.');
    }
    $pdo = db();
    $stmt = $pdo->prepare("SELECT risk_level, adjustment_log FROM events WHERE id=? AND type='reading' AND archived=0");
    $stmt->execute([$readingId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new InvalidArgumentException('Reading not found.');
    }
    $log = json_decode($row['adjustment_log'] ?? '[]', true);
    if (!is_array($log)) {
        throw new RuntimeException('Invalid existing adjustment log.');
    }
    $log[] = ['by' => $adminId,'at' => gmdate('Y-m-d H:i:s'),'from' => $row['risk_level'],'to' => $risk,'reason' => trim($reason)];
    $stmt = $pdo->prepare('UPDATE events SET risk_level=?, adjustment_log=? WHERE id=?');
    $stmt->execute([$risk,json_encode($log, JSON_THROW_ON_ERROR),$readingId]);
}


function get_or_create_location(float $lat, float $lng, ?string $landmark = null): array
{
    if (!is_finite($lat) || !is_finite($lng) || !is_in_irisan($lat, $lng) || strlen((string)$landmark) > 255) {
        throw new InvalidArgumentException('Invalid Irisan location or address.');
    }
    $pdo = db();

    // Round to 5 decimal places
    $lat = round($lat, 5);
    $lng = round($lng, 5);

    // Check if exists
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE lat = ? AND lng = ?");
    $stmt->execute([$lat, $lng]);

    if ($loc = $stmt->fetch()) {
        if (!$loc['active']) {
            throw new InvalidArgumentException('This monitoring point was removed by an administrator.');
        }
        if (empty($loc['landmark']) && trim((string)$landmark) !== '') {
            $stmt = $pdo->prepare("UPDATE locations SET landmark = ? WHERE id = ? AND (landmark IS NULL OR landmark = '')");
            $stmt->execute([trim((string)$landmark), $loc['id']]);
            return get_location((int)$loc['id']) ?? $loc;
        }
        return $loc;
    }

    // Create new
    $name = sprintf('Irisan %.5f, %.5f', $lat, $lng);
    $stmt = $pdo->prepare(
        "INSERT INTO locations (name, purok, landmark, lat, lng, susceptibility, active)
         VALUES (?, 'Map point', ?, ?, ?, 'unknown', 1)"
    );
    $landmark = trim((string)$landmark);
    $stmt->execute([$name, $landmark !== '' ? $landmark : null, $lat, $lng]);
    return get_location((int)$pdo->lastInsertId());
}

// Weather requests and rainfall windows

function fetch_weather(float $lat, float $lng): array
{
    $url = "https://api.open-meteo.com/v1/forecast?";
    $params = [
        'latitude' => $lat,
        'longitude' => $lng,
        'current' => 'temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m',
        'hourly' => 'precipitation,precipitation_probability,soil_moisture_9_to_27cm,soil_moisture_27_to_81cm',
        'past_hours' => 73,
        'forecast_hours' => 25,
        'timezone' => 'UTC',
        'temperature_unit' => 'celsius',
        'wind_speed_unit' => 'kmh',
        'precipitation_unit' => 'mm'
    ];

    $ch = curl_init($url . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $status !== 200) {
        throw new RuntimeException('Weather provider is unavailable.');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['current']['time'])) {
        throw new RuntimeException('Weather provider returned incomplete data.');
    }
    $decoded['provider_retrieved_at_utc'] = gmdate('Y-m-d H:i:s');
    $decoded['request_parameters'] = $params;
    foreach (['precipitation_probability' => '%', 'soil_moisture_9_to_27cm' => 'm³/m³', 'soil_moisture_27_to_81cm' => 'm³/m³'] as $field => $unit) {
        if (($decoded['hourly_units'][$field] ?? null) !== $unit) {
            unset($decoded['hourly'][$field]);
        }
    }
    validate_weather_payload($decoded);
    return $decoded;
}

// Calculate rainfall from hourly data

function rainfall_total(array $samples, array $times): ?float
{
    $sum = 0.0;
    foreach ($times as $timestamp) {
        $value = $samples[$timestamp]['rain'] ?? null;
        if ($value === null) {
            return null;
        }
        $sum += $value;
    }
    if ($sum > 99999.99) {
        return null;
    }
    return round($sum, 2);
}

/** Hourly precipitation is the sum of the preceding hour, labelled by its end. */
function calculate_weather_indicators(array $hourly, string $currentTime): array
{
    $end = utc_timestamp($currentTime);
    if (
        $end === null || !isset($hourly['time'], $hourly['precipitation']) ||
        !is_array($hourly['time']) || !is_array($hourly['precipitation'])
    ) {
        throw new RuntimeException('Missing hourly rainfall data.');
    }
    $end = intdiv($end, 3600) * 3600; // Last completed hourly interval.
    $samples = [];
    foreach ($hourly['time'] as $i => $time) {
        $timestamp = is_string($time) ? utc_timestamp($time) : null;
        if ($timestamp === null || $timestamp % 3600 !== 0 || array_key_exists($timestamp, $samples)) {
            throw new RuntimeException('Hourly timestamps are invalid or duplicated.');
        }
        $samples[$timestamp] = ['index' => $i, 'rain' => finite_number($hourly['precipitation'][$i] ?? null, 0, 99999.99)];
    }
    $result = [];
    foreach ([1, 24, 72] as $hours) {
        $times = [];
        for ($i = 0; $i < $hours; $i++) {
            $times[] = $end - $i * 3600;
        }
        $result['rainfall_' . $hours . 'h'] = rainfall_total($samples, $times);
    }
    $future = [];
    for ($i = 1; $i <= 24; $i++) {
        $future[] = $end + $i * 3600;
    }
    $result['rainfall_forecast_24h'] = rainfall_total($samples, $future);
    $probabilities = [];
    foreach ($future as $timestamp) {
        $index = $samples[$timestamp]['index'] ?? null;
        $probability = $index === null ? null : finite_number($hourly['precipitation_probability'][$index] ?? null, 0, 100);
        if ($probability === null) {
            $probabilities = [];
            break;
        }
        $probabilities[] = $probability;
    }
    $result['precipitation_probability_24h'] = count($probabilities) === 24 ? (int)round(max($probabilities)) : null;
    foreach (['9_to_27cm' => '9_27cm', '27_to_81cm' => '27_81cm'] as $apiDepth => $dbDepth) {
        $index = $samples[$end]['index'] ?? null;
        $value = $index === null ? null : finite_number($hourly['soil_moisture_' . $apiDepth][$index] ?? null, 0, 1);
        $result['soil_moisture_' . $dbDepth] = $value === null ? null : round($value, 4);
    }
    return $result;
}

function validate_weather_payload(array $weather, ?int $now = null): void
{
    global $config;
    $current = $weather['current'] ?? null;
    if (
        !is_array($current) || !is_array($weather['hourly'] ?? null) ||
        !is_string($current['time'] ?? null)
    ) {
        throw new RuntimeException('Incomplete weather response.');
    }
    $observed = utc_timestamp($current['time']);
    $now = $now ?? time();
    if ($observed === null) {
        throw new RuntimeException('Invalid provider observation time.');
    }
    if ($observed > $now + ($config['future_tolerance_seconds'] ?? 300)) {
        throw new RuntimeException('Provider observation is in the future.');
    }
    if ($now - $observed > ($config['freshness_seconds'] ?? 10800)) {
        throw new RuntimeException('Provider observation is outdated.');
    }
    if (($weather['utc_offset_seconds'] ?? null) !== 0) {
        throw new RuntimeException('Expected UTC provider timestamps.');
    }
    foreach (['precipitation' => 'mm'] as $field => $unit) {
        if (($weather['hourly_units'][$field] ?? null) !== $unit) {
            throw new RuntimeException('Unexpected hourly units.');
        }
    }
    foreach (['temperature_2m' => '°C', 'relative_humidity_2m' => '%', 'wind_speed_10m' => 'km/h', 'precipitation' => 'mm', 'weather_code' => 'wmo code'] as $field => $unit) {
        if (($weather['current_units'][$field] ?? null) !== $unit) {
            throw new RuntimeException('Unexpected current units.');
        }
    }
    foreach (
        ['temperature_2m' => [-100, 100], 'relative_humidity_2m' => [0, 100],
              'wind_speed_10m' => [0, 9999.99], 'weather_code' => [0, 99], 'precipitation' => [0, 99999.99]] as $field => $range
    ) {
        if (finite_number($current[$field] ?? null, $range[0], $range[1]) === null) {
            throw new RuntimeException('Invalid current weather value.');
        }
    }
}

function refresh_location(int $locationId): array
{
    $location = get_location($locationId);
    if (
        !$location || !$location['active'] || !$location['lat'] || !$location['lng'] ||
        !is_in_irisan((float)$location['lat'], (float)$location['lng'])
    ) {
        throw new InvalidArgumentException('Select an active Irisan location.');
    }
    $weather = fetch_weather((float)$location['lat'], (float)$location['lng']);
    $current = $weather['current'];
    $indicators = calculate_weather_indicators($weather['hourly'] ?? [], $current['time']);
    $risk = calculate_risk($indicators['rainfall_1h'], $indicators['rainfall_24h'], $indicators['rainfall_72h']);
    $observed = utc_timestamp($current['time']);
    create_reading(array_merge($indicators, [
        'location_id' => $locationId, 'risk_level' => $risk,
        'temperature' => $current['temperature_2m'] ?? null,
        'humidity' => $current['relative_humidity_2m'] ?? null,
        'wind_speed' => $current['wind_speed_10m'] ?? null,
        'weather_code' => $current['weather_code'] ?? null,
         'observed_at' => gmdate('Y-m-d H:i:s', $observed),
        'rule_version' => RiskAnalyzer::VERSION,
        'rainfall_window_end' => gmdate('Y-m-d H:i:s', intdiv($observed, 3600) * 3600),
        'provider_payload' => json_encode(['classification' => 'API','request_coordinates' => [$location['lat'],$location['lng']],
            'request_policy' => '73 past hours, 25 forecast hours, UTC; optional arrays discarded if units differ',
            'response' => $weather], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]));
    return get_latest_reading($locationId);
}

// CSV downloads and imports

function csv_rows(array $rows, bool $bom = true): string
{
    $stream = fopen('php://temp', 'w+');
    try {
        if ($bom) {
            fwrite($stream, "\xEF\xBB\xBF");
        }
        foreach ($rows as $row) {
            if (fputcsv($stream, $row, ',', '"', '', "\r\n") === false) {
                throw new RuntimeException('CSV could not be written.');
            }
        }
        rewind($stream);
        return (string)stream_get_contents($stream);
    } finally {
        fclose($stream);
    }
}
function csv_date(?string $date): string
{
    $time = utc_timestamp($date);
    return $time === null ? 'Not recorded' : (new DateTimeImmutable('@' . $time))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d H:i:s');
}
function csv_label(?string $value): string
{
    return ucwords(str_replace('_', ' ', $value ?? 'Not recorded'));
}
function download_csv(string $filename, string $csv): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $csv;
    exit;
}
function csv_adjustments(?string $json): string
{
    $log = json_decode($json ?? '[]', true);
    if (!is_array($log) || !$log) {
        return 'None recorded';
    }
    $lines = [];
    foreach ($log as $edit) {
        $lines[] = csv_date($edit['at'] ?? null) . ' PHT; administrator #' . ($edit['by'] ?? '?') . '; ' . csv_label($edit['from'] ?? null) . ' to ' . csv_label($edit['to'] ?? null) . '; ' . ($edit['reason'] ?? '');
    }
    return implode("\n", $lines);
}
function export_readings_csv(array $readings): string
{
    $rows = [['Reading ID','Location','Stored category','Calculated rainfall category','Data status',
        'Rainfall - 1 hour (mm)','Rainfall - 24 hours (mm)','Rainfall - 72 hours (mm)',
        'Provider valid time (PHT)','Rainfall window end (PHT)','Saved at (PHT)',
        'Forecast rainfall - next 24 hours (mm)','Maximum hourly rain chance (%)',
        'Temperature (C)','Humidity (%)','Wind speed (km/h)',
        'Modeled soil moisture - 9 to 27 cm (m3/m3)','Modeled soil moisture - 27 to 81 cm (m3/m3)',
        'Data source','Rule version','Hourly inputs saved','Administrator adjusted','Adjustment history']];
    foreach ($readings as $r) {
        $a = $r['assessment'] ?? reading_assessment($r);
        $rows[] = [(int)$r['id'],(string)(ui_location_label($r)),csv_label($r['risk_level'] ?? null),csv_label($a['calculated_category']),csv_label($a['data_status']),
            $r['rainfall_1h'] ?? '', $r['rainfall_24h'] ?? '', $r['rainfall_72h'] ?? '',
            csv_date($r['observed_at'] ?? null),csv_date($a['rainfall_window_end'] ?? null),csv_date($r['created_at'] ?? null),
            $r['rainfall_forecast_24h'] ?? '', $r['precipitation_probability_24h'] ?? '',
            $r['temperature'] ?? '', $r['humidity'] ?? '', $r['wind_speed'] ?? '',
            $r['soil_moisture_9_27cm'] ?? '', $r['soil_moisture_27_81cm'] ?? '',
            (string)(ui_source($r['source'] ?? null)),(string)($r['rule_version'] ?? 'Legacy - not recorded'),
            empty($r['provider_payload']) ? 'No' : 'Yes',$a['adjusted'] ? 'Yes' : 'No',(string)(csv_adjustments($r['adjustment_log'] ?? null))];
    }
    return csv_rows($rows);
}
function export_reports_csv(array $reports): string
{
    $rows = [['Report ID','Location','Landmark supplied by reporter','Observed condition','Full message','Status',
        'Occurred at (PHT)','Submitted at (PHT)','Reporter','Contact phone','Contact email','Last reviewer ID','Last reviewed at (PHT)']];
    foreach ($reports as $r) {
        $rows[] = [(int)$r['id'],(string)(ui_location_label($r)),(string)($r['house_landmark'] ?? ''),
        (string)(REPORT_TYPES[$r['report_type'] ?? ''] ?? 'Not recorded'),(string)($r['message']),csv_label($r['status']),
        csv_date($r['occurred_at'] ?? null),csv_date($r['created_at'] ?? null),(string)($r['reporter_name'] ?? 'Not recorded'),
        (string)($r['contact_phone'] ?? ''),(string)($r['contact_email'] ?? ''),$r['reviewed_by'] ?? '',csv_date($r['reviewed_at'] ?? null)];
    }
    return csv_rows($rows);
}

// Location CSVs use one header followed by ordinary, editable rows.
const LOCATION_CSV_HEADERS = ['Location ID', 'Location name', 'Purok', 'Landmark', 'Latitude', 'Longitude', 'Status'];

function export_locations_csv(array $locations): string
{
    $rows = [LOCATION_CSV_HEADERS];
    foreach ($locations as $location) {
        $rows[] = [
            $location['id'], $location['name'], $location['purok'] ?? '',
            $location['landmark'] ?? '', $location['lat'], $location['lng'],
            $location['active'] ? 'Active' : 'Inactive'
        ];
    }
    return csv_rows($rows);
}

function parse_locations_csv(string $csv): array
{
    if (str_starts_with($csv, "\xEF\xBB\xBF")) {
        $csv = substr($csv, 3);
    }
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, $csv);
    rewind($stream);
    $headers = fgetcsv($stream, 0, ',', '"', '');
    if ($headers !== LOCATION_CSV_HEADERS) {
        fclose($stream);
        throw new InvalidArgumentException('Choose a Locations CSV with the seven exported column headings.');
    }
    $locations = [];
    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        if ($row === [null]) {
            continue;
        }
        if (count($row) !== 7) {
            fclose($stream);
            throw new InvalidArgumentException('Each location needs seven CSV columns.');
        }
        // The exported ID is informational; coordinates identify stored locations.
        $locations[] = [
            'name' => trim($row[1]), 'purok' => trim($row[2]), 'landmark' => trim($row[3]),
            'lat' => $row[4], 'lng' => $row[5], 'active' => $row[6]
        ];
    }
    fclose($stream);
    return $locations;
}

function import_locations_csv(array $rows): array
{
    $pdo = db();
    $result = ['added' => 0, 'updated' => 0, 'unchanged' => 0];
    // The existing UNIQUE(lat, lng) key keeps readings/reports linked to the same ID.
    $save = $pdo->prepare("INSERT INTO locations (name, purok, landmark, lat, lng, active, susceptibility)
        VALUES (?, ?, NULLIF(?, ''), ?, ?, ?, 'unknown')
        ON DUPLICATE KEY UPDATE name = VALUES(name), purok = VALUES(purok),
            landmark = VALUES(landmark), active = VALUES(active)");
    foreach ($rows as $row) {
        // Only checks needed for the existing table and Irisan map are retained.
        $lat = finite_number($row['lat'], -90, 90);
        $lng = finite_number($row['lng'], -180, 180);
        if (
            $row['name'] === '' || strlen($row['name']) > 150 || strlen($row['purok']) > 100 ||
            strlen($row['landmark']) > 255 || $lat === null || $lng === null ||
            !is_in_irisan($lat, $lng) || !in_array($row['active'], ['Active', 'Inactive'], true)
        ) {
            throw new InvalidArgumentException('Invalid location details. Earlier rows may already be saved.');
        }
        $save->execute([$row['name'], $row['purok'], $row['landmark'], $lat, $lng, $row['active'] === 'Active' ? 1 : 0]);
        if ($save->rowCount() === 1) {
            $result['added']++;
        } elseif ($save->rowCount() === 2) {
            $result['updated']++;
        } else {
            $result['unchanged']++;
        }
    }
    return $result;
}

// Database setup

const AWARENESS_COLUMNS = ['rule_version'=>'VARCHAR(40) NULL','rainfall_window_end'=>'DATETIME NULL',
    'provider_payload'=>'LONGTEXT NULL','adjustment_log'=>'LONGTEXT NULL','report_type'=>'VARCHAR(30) NULL',
    'occurred_at'=>'DATETIME NULL','reviewed_by'=>'INT UNSIGNED NULL','reviewed_at'=>'DATETIME NULL'];
function missing_awareness_columns(): array {
    $existing=array_column(db()->query('SHOW COLUMNS FROM events')->fetchAll(),'Field');
    return array_diff(array_keys(AWARENESS_COLUMNS),$existing);
}
function require_awareness_schema(): void {
    if (missing_awareness_columns()) throw new RuntimeException('Database setup is incomplete. Open Admin Panel and click Complete database setup.');
}
function migrate_awareness_schema(): void {
    $pdo=db();
    foreach (missing_awareness_columns() as $name) $pdo->exec('ALTER TABLE events ADD COLUMN `'.$name.'` '.AWARENESS_COLUMNS[$name]);
    $indexes=array_column($pdo->query('SHOW INDEX FROM events')->fetchAll(),'Key_name');
    if (!in_array('reading_history',$indexes,true)) $pdo->exec('ALTER TABLE events ADD INDEX reading_history (location_id, type, archived, observed_at)');
}

// Optional reviewed susceptibility dataset

/** A source review is a documented human check, not scientific validation. */
function validate_susceptibility_dataset(array $data): void {
    $m = $data['metadata'] ?? [];
    $host = parse_url($m['source_url'] ?? '', PHP_URL_HOST);
    if (($data['type'] ?? '') !== 'FeatureCollection' || ($m['reviewed'] ?? false) !== true ||
        parse_url($m['source_url'] ?? '', PHP_URL_SCHEME) !== 'https' || !is_string($host) || !($host === 'mgb.gov.ph' || str_ends_with($host, '.mgb.gov.ph')) ||
        ($m['crs'] ?? '') !== 'EPSG:4326' || empty($data['features'])) {
        throw new InvalidArgumentException('A reviewed MGB WGS84 polygon dataset is required.');
    }
    foreach (['edition','scale','reuse_terms','coverage_review','reviewed_by','reviewed_at'] as $field) {
        if (!is_string($m[$field] ?? null) || trim($m[$field]) === '') throw new InvalidArgumentException('Missing source review: ' . $field);
    }
    foreach ($data['features'] as $feature) {
        if (!in_array($feature['properties']['LndslideSusc'] ?? '', ['VHL','HL','ML','LL','DF'], true) ||
            !isset($feature['properties']['OBJECTID'])) throw new InvalidArgumentException('Invalid original MGB class or feature ID.');
        $g = $feature['geometry'] ?? [];
        if (!in_array($g['type'] ?? '', ['Polygon','MultiPolygon'], true)) throw new InvalidArgumentException('Polygon geometry required.');
        $polygons = $g['type'] === 'Polygon' ? [$g['coordinates'] ?? []] : ($g['coordinates'] ?? []);
        if (!$polygons) throw new InvalidArgumentException('Empty geometry.');
        foreach ($polygons as $polygon) {
            if (!$polygon) throw new InvalidArgumentException('Empty polygon.');
            foreach ($polygon as $ring) {
                if (count($ring) < 4 || $ring[0] !== $ring[count($ring)-1]) throw new InvalidArgumentException('Closed ring required.');
                foreach ($ring as $point) {
                    if (count($point) < 2 || finite_number($point[0], -180, 180) === null || finite_number($point[1], -90, 90) === null) throw new InvalidArgumentException('Invalid WGS84 coordinate.');
                }
            }
        }
    }
}

// 0 outside, 1 inside, 2 on boundary. Boundary/overlap ambiguity stays unknown.
function susceptibility_ring(float $lat, float $lng, array $ring): int {
    $inside = false;
    for ($i=0, $j=count($ring)-1; $i<count($ring); $j=$i++) {
        [$x1,$y1]=$ring[$j]; [$x2,$y2]=$ring[$i];
        $cross=($lng-$x1)*($y2-$y1)-($lat-$y1)*($x2-$x1);
        if (abs($cross)<1e-12 && $lng>=min($x1,$x2)-1e-10 && $lng<=max($x1,$x2)+1e-10 && $lat>=min($y1,$y2)-1e-10 && $lat<=max($y1,$y2)+1e-10) return 2;
        if (($y1>$lat)!==($y2>$lat) && $lng<($x2-$x1)*($lat-$y1)/($y2-$y1)+$x1) $inside=!$inside;
    }
    return $inside ? 1 : 0;
}

function susceptibility_lookup(array $location, ?array $dataset = null): array {
    $unknown=['category'=>'unknown','classification'=>null,'source'=>null,'reason'=>'No reviewed susceptibility dataset is installed.'];
    if (finite_number($location['lat'] ?? null) === null || finite_number($location['lng'] ?? null) === null) return $unknown;
    if ($dataset === null) {
        static $loaded = false, $local = null;
        if (!$loaded) {
            $loaded=true;
            $path=__DIR__ . '/data/irisan-susceptibility.geojson';
            if (is_file($path)) $local=json_decode((string)file_get_contents($path), true);
        }
        $dataset=$local;
    }
    if (!is_array($dataset)) return $unknown;
    try { validate_susceptibility_dataset($dataset); } catch (Throwable $e) { return $unknown; }
    $matches=[];
    foreach ($dataset['features'] as $feature) {
        $g=$feature['geometry'];
        foreach ($g['type']==='Polygon' ? [$g['coordinates']] : $g['coordinates'] as $polygon) {
            $inside=false;
            foreach ($polygon as $i=>$ring) {
                $state=susceptibility_ring((float)$location['lat'], (float)$location['lng'], $ring);
                if ($state===2) return array_merge($unknown,['reason'=>'Point is on an approximate polygon boundary.']);
                if ($i===0) $inside=$state===1;
                elseif ($state===1) $inside=false;
            }
            if ($inside) $matches[]=$feature['properties'];
        }
    }
    $classes=array_unique(array_column($matches,'LndslideSusc'));
    if (count($classes)!==1) return array_merge($unknown,['reason'=>$matches ? 'Conflicting polygon classes.' : 'No reviewed polygon covers this point.']);
    $map=['VHL'=>'very_high','HL'=>'high','ML'=>'moderate','LL'=>'low','DF'=>'debris_flow'];
    return ['category'=>$map[reset($classes)],'classification'=>'VERIFIED','source'=>$dataset['metadata'],
        'feature_ids'=>array_column($matches,'OBJECTID'),'reason'=>'Reviewed MGB dataset; boundaries are approximate.'];
}

// Reports and notices

const REPORT_TYPES = ['ground_cracks'=>'Ground cracks','fallen_material'=>'Fallen soil or rock',
    'drainage_problem'=>'Drainage problem','other'=>'Other observed condition'];

function report_occurrence(string $input, ?int $now = null): ?string {
    if ($input === '') return null;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $input, new DateTimeZone('Asia/Manila'));
    if (!$date || $date->format('Y-m-d\TH:i')!==$input || $date->getTimestamp()>($now ?? time())+300) throw new InvalidArgumentException('Enter a valid occurrence time that is not in the future.');
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function awareness_notices(array $assessment, array $baseline): array {
    $notices=[];
    // Administrator edits are presented separately; automated notices use the calculated category.
    $level=$assessment['data_status']==='current' ? $assessment['calculated_category'] : null;
    if ($level===null) $notices[]=['tone'=>'secondary','title'=>'Current assessment unavailable',
        'text'=>'Select a location and refresh weather. Last saved readings remain historical context.'];
    elseif (in_array($level,['medium','high'],true)) $notices[]=['tone'=>$level==='high'?'danger':'warning',
        'title'=>'Prototype rainfall notice: ' . ucfirst($level),
        'text'=>implode(' ', $assessment['reasons'])];
    else $notices[]=['tone'=>'info','title'=>'Prototype rainfall screening: ' . ucfirst($level),
        'text'=>'Rainfall is below the medium and high prototype thresholds. This does not establish slope safety.'];
    if ($baseline['classification']==='VERIFIED' && in_array($baseline['category'],['high','very_high','debris_flow'],true)) {
        $notices[]=['tone'=>'warning','title'=>'Baseline susceptibility notice',
            'text'=>'Reviewed MGB class: ' . str_replace('_',' ', $baseline['category']) . '. This baseline is separate from rainfall screening. Boundaries are approximate.'];
    }
    return $notices;
}

function location_report_summary(int $id): array {
    $stmt=db()->prepare("SELECT status, COUNT(*) AS total FROM events WHERE type='report' AND location_id=? GROUP BY status");
    $stmt->execute([$id]);
    return array_map('intval', array_column($stmt->fetchAll(),'total','status'));
}

function ui_report_summary(array $counts, bool $admin): string {
    $html='<h4 class="h6">Community reports <span class="badge bg-secondary">USER-SUBMITTED</span></h4><p>';
    foreach (['pending','reviewed','resolved'] as $state) $html.=e(ucfirst($state)).': '.(int)($counts[$state]??0).' &nbsp; ';
    $html.='</p><p class="small text-muted">Review status is a workflow label, not scientific confirmation. Reports do not change the rainfall category.</p>';
    if ($admin && ($counts['pending']??0)>0) $html.='<a class="btn btn-sm btn-outline-primary" href="'.e('admin.php?status=pending').'">Review pending reports</a>';
    return $html;
}

// Shared HTML rendering

// Shared, escaped presentation for page loads and the weather AJAX response.
function ui_navigation(string $active): string {
    ob_start();
    require __DIR__ . '/partials/navbar.php';
    return (string)ob_get_clean();
}

function ui_current_category(array $assessment): ?string {
    $category = $assessment['current_category'] ?? null;
    return ($assessment['data_status'] ?? '') === 'current' &&
        in_array($category, ['low', 'normal', 'medium', 'high'], true) ? $category : null;
}

function ui_current_count(array $readings, string $category): int {
    $count = 0;
    foreach ($readings as $reading) {
        if (ui_current_category($reading['assessment'] ?? []) === $category) $count++;
    }
    return $count;
}

function ui_number($value, string $unit = ''): string {
    return $value === null || $value === '' ? 'Unavailable' : (string)$value . $unit;
}

function ui_time(?string $value): string {
    return $value ? local_date($value) . ' PHT' : 'Unavailable';
}

function ui_source(?string $source): string {
    if ($source === 'openmeteo') return 'Open-Meteo weather provider (modeled data)';
    if ($source === 'manual') return 'Manual entry';
    return 'Source unavailable';
}

function ui_location_label(array $location): string {
    $name = trim((string)($location['name'] ?? $location['location_name'] ?? ''));
    $landmark = trim((string)($location['landmark'] ?? ''));
    $purok = trim((string)($location['purok'] ?? ''));
    // A generated coordinate label is not a street address.
    $generated = preg_match('/^Irisan\s+-?\d+(?:\.\d+)?,\s*-?\d+(?:\.\d+)?$/i', $name);
    $parts = [];
    if ($landmark !== '') {
        $parts[] = $landmark;
    } elseif ($name !== '' && !$generated && strcasecmp($name, 'Irisan') !== 0) {
        $parts[] = $name;
    }
    if ($landmark !== '' && stripos($landmark, 'Baguio') !== false) return $landmark;
    if ($purok !== '' && stripos(implode(', ', $parts), $purok) === false) $parts[] = $purok;
    $parts[] = 'Barangay Irisan, Baguio City, Benguet, Philippines';
    $address = implode(', ', $parts);
    if ($generated && $landmark === '' && isset($location['lat'], $location['lng'])) {
        $address .= sprintf(' (%.5f, %.5f)', $location['lat'], $location['lng']);
    }
    return $address;
}

function ui_category_badge(array $assessment): string {
    $category = ui_current_category($assessment);
    $color = ['low'=>'success', 'normal'=>'primary', 'medium'=>'warning text-dark', 'high'=>'danger'][$category ?? ''] ?? 'secondary';
    $html = '<span class="badge bg-' . $color . '">' . e(ucfirst($category ?? 'unavailable')) . '</span>';
    if (!$category && ($assessment['data_status'] ?? '') === 'outdated' && !empty($assessment['category'])) {
        $html .= '<small class="d-block text-muted mt-1">Last saved: ' . e(ucfirst($assessment['category'])) . '</small>';
    }
    if (!empty($assessment['adjusted'])) {
        $html .= '<small class="d-block mt-1">Administrator-adjusted</small>';
    }
    return $html;
}

function ui_weather_details(array $reading): string {
    ob_start(); ?>
    <dl class="weather-details mb-0">
        <?php foreach ([
            'Forecast rainfall (24h from last whole hour)' => ui_number($reading['rainfall_forecast_24h'] ?? null, ' mm'),
            'Maximum hourly rain chance in that period' => ui_number($reading['precipitation_probability_24h'] ?? null, '%'),
            'Modeled soil moisture, 9–27 cm' => ui_number($reading['soil_moisture_9_27cm'] ?? null, ' m³/m³'),
            'Modeled soil moisture, 27–81 cm' => ui_number($reading['soil_moisture_27_81cm'] ?? null, ' m³/m³'),
            'Temperature' => ui_number($reading['temperature'] ?? null, ' °C'),
            'Humidity' => ui_number($reading['humidity'] ?? null, '%'),
            'Wind speed' => ui_number($reading['wind_speed'] ?? null, ' km/h'),
            'Retrieved' => ui_time($reading['created_at'] ?? null),
            'Source' => ui_source($reading['source'] ?? null),
        ] as $label => $value): ?>
            <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
        <?php endforeach; ?>
    </dl>
    <?php return (string)ob_get_clean();
}

function ui_assessment_panel(?array $reading, array $location, array $assessment): string {
    $status = $assessment['data_status'] ?? 'unavailable';
    $baseline = susceptibility_lookup($location);
    ob_start(); ?>
    <?php foreach (awareness_notices($assessment,$baseline) as $notice): ?>
        <div class="alert alert-<?= e($notice['tone']) ?>" role="status"><strong><?= e($notice['title']) ?></strong><p class="mb-0"><?= e($notice['text']) ?></p></div>
    <?php endforeach; ?>
    <p class="small text-muted">Academic prototype, not an official warning. Updates follow manual weather refresh. No automatic evacuation instructions.</p>
    <h3 class="h6 text-muted">Prototype rainfall-screening category</h3>
    <div class="assessment-category mb-2"><?= ui_category_badge($assessment) ?></div>
    <p class="mb-2"><strong>Data status:</strong> <?= e(ucfirst($status)) ?></p>
    <?php if ($status !== 'current'): ?>
        <p class="alert alert-secondary py-2">A current assessment is unavailable. <?= $location ? 'Refresh weather to request an updated reading.' : 'Select an Irisan location to view its assessment.' ?></p>
    <?php endif; ?>
    <p><strong>Location:</strong> <?= e($location ? ui_location_label($location) : 'No location selected') ?></p>
    <div class="row g-2 mb-3">
        <?php foreach (['1h'=>'rainfall_1h', '24h'=>'rainfall_24h', '72h'=>'rainfall_72h'] as $period=>$field): ?>
        <div class="col-4"><div class="border rounded p-2 h-100"><small class="d-block text-muted"><?= e($period) ?> rainfall</small><strong><?= e(ui_number($reading[$field] ?? null, ' mm')) ?></strong></div></div>
        <?php endforeach; ?>
    </div>
    <p class="small mb-2"><strong>Provider valid time:</strong> <?= e(ui_time($reading['observed_at'] ?? null)) ?><br>
        <strong>Rainfall window end:</strong> <?= e(ui_time($assessment['rainfall_window_end'] ?? null)) ?><br>
        <strong>Retrieved:</strong> <?= e(ui_time($reading['created_at'] ?? null)) ?><br>
        <strong>Source:</strong> <?= e(ui_source($reading['source'] ?? null)) ?></p>
    <div class="border rounded p-3 mb-3">
        <h4 class="h6">Why this category? <span class="badge bg-secondary">CALCULATED</span></h4>
        <ul><?php foreach ($assessment['reasons'] as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul>
        <p class="small mb-0">Rule: <?= e($reading['rule_version'] ?? 'prototype-1 (legacy version not recorded)') ?>. Historical inputs: <?= ($assessment['provenance_status'] ?? '') !== 'saved_provider_inputs' ? 'not retained for this legacy record' : 'retained with provider grid metadata' ?>.</p>
    </div>
    <div class="border rounded p-3 mb-3"><h4 class="h6">Baseline susceptibility: <?= e(ucwords(str_replace('_',' ',$baseline['category']))) ?></h4>
        <p class="small mb-1"><?= e($baseline['reason']) ?></p>
        <?php if ($baseline['source']): ?><p class="small mb-0"><span class="badge bg-secondary">VERIFIED</span> Source: <a href="<?= e($baseline['source']['source_url']) ?>" target="_blank" rel="noopener noreferrer">MGB</a>; edition <?= e($baseline['source']['edition']) ?>; scale <?= e($baseline['source']['scale']) ?>.</p><?php endif; ?>
    </div>
    <?php if ($reading): ?>
        <button type="button" class="btn btn-outline-primary btn-sm" data-weather-dialog
                data-template-id="assessment-weather-details" data-details-title="Weather details for <?= e($location['name'] ?? 'selected location') ?>">More weather details</button>
        <template id="assessment-weather-details">
            <p><strong>Location:</strong> <?= e(ui_location_label($location)) ?></p>
            <?= ui_weather_details($reading) ?>
        </template>
    <?php endif; ?>
    <?php return (string)ob_get_clean();
}

function ui_readings_head(bool $admin, string $group): string {
    ob_start(); ?>
    <thead class="table-light"><tr>
        <?php if ($admin): ?><th scope="col"><input type="checkbox" data-select-all="<?= e($group) ?>" aria-label="Select all displayed readings"></th><?php endif; ?>
        <th scope="col">Location</th><th scope="col">Current assessment</th>
        <th scope="col">Rainfall mm<br><small>1h / 24h / 72h</small></th>
        <th scope="col">Provider time (PHT)</th><th scope="col">Data status</th><th scope="col">Details</th>
        <?php if ($admin): ?><th scope="col">Actions</th><?php endif; ?>
    </tr></thead>
    <?php return (string)ob_get_clean();
}

function ui_readings_rows(array $readings, bool $admin, string $group, string $formId, array $locations = []): string {
    ob_start();
    foreach ($readings as $reading):
        $assessment = $reading['assessment'] ?? reading_assessment($reading);
        $location = $locations[(int)$reading['location_id']] ?? [];
        if (!$location) $location = $reading;
        $name = ui_location_label($location);
        $templateId = 'weather-details-' . $group . '-' . (int)$reading['id']; ?>
        <tr>
            <?php if ($admin): ?><td><input type="checkbox" form="<?= e($formId) ?>" name="reading_ids[]" value="<?= (int)$reading['id'] ?>" data-bulk-item="<?= e($group) ?>" aria-label="Select reading <?= (int)$reading['id'] ?>"></td><?php endif; ?>
            <td class="reading-location"><?= e($name) ?></td>
            <td><?= ui_category_badge($assessment) ?></td>
            <td><?= e(ui_number($reading['rainfall_1h'] ?? null)) ?> / <?= e(ui_number($reading['rainfall_24h'] ?? null)) ?> / <?= e(ui_number($reading['rainfall_72h'] ?? null)) ?></td>
            <td><?= e($reading['observed_at'] ? local_date($reading['observed_at']) : 'Unavailable') ?></td>
            <td><?= e(ucfirst($assessment['data_status'] ?? 'unavailable')) ?></td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary" data-weather-dialog
                        data-template-id="<?= e($templateId) ?>" data-details-title="Weather details for reading <?= (int)$reading['id'] ?>">View details</button>
                <template id="<?= e($templateId) ?>">
                    <p><strong>Location:</strong> <?= e($name) ?></p>
                    <p><strong>Provider valid time:</strong> <?= e(ui_time($reading['observed_at'] ?? null)) ?></p>
                    <?= ui_weather_details($reading) ?>
                </template>
            </td>
            <?php if ($admin): ?><td><a class="btn btn-sm btn-outline-primary" href="<?= e(('admin.php?action=edit_reading&reading_id=' . (int)$reading['id'])) ?>">Edit</a></td><?php endif; ?>
        </tr>
    <?php endforeach;
    if (!$readings): ?>
        <tr><td colspan="<?= $admin ? 8 : 6 ?>" class="text-center text-muted">No saved readings. Select a location and refresh weather from the dashboard.</td></tr>
    <?php endif;
    return (string)ob_get_clean();
}

function ui_weather_modal(): string {
    return '<div class="modal fade" id="readingWeatherModal" tabindex="-1" aria-labelledby="readingWeatherTitle" aria-hidden="true">'
        . '<div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">'
        . '<div class="modal-header"><h2 class="modal-title h5" id="readingWeatherTitle">Weather details</h2>'
        . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close weather details"></button></div>'
        . '<div class="modal-body" id="readingWeatherBody"></div>'
        . '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>'
        . '</div></div></div>';
}

/** Snapshot history: repeated timestamps are collapsed only for this display. */
function ui_history(array $readings): string {
    $seen=[]; $rows=[];
    foreach ($readings as $r) {
        $key=$r['observed_at'] ?? '';
        if ($key==='' || isset($seen[$key])) continue;
        $seen[$key]=true; $rows[]=$r;
    }
    ob_start(); ?>
    <p class="small text-muted">Recent saved snapshots, newest first; repeated provider times are shown once. These are accumulated totals, not individual hourly rainfall. Never add these rows together.</p>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable rainfall history"><table class="table table-sm"><thead><tr><th>Provider time (PHT)</th><th>24h total (mm)</th><th>Change from previous saved snapshot (mm)</th><th>Calculated category</th></tr></thead><tbody>
    <?php foreach ($rows as $i=>$r):
        $previous=$rows[$i+1]??null;
        $now=finite_number($r['rainfall_24h']??null,0); $then=finite_number($previous['rainfall_24h']??null,0);
        $change=$now!==null && $then!==null ? round($now-$then,2) : null;
        $a=$r['assessment']??reading_assessment($r); ?>
        <tr><td><?= e(ui_time($r['observed_at'])) ?></td><td><?= e(ui_number($now)) ?></td><td><?= e(ui_number($change)) ?></td><td><?= e($a['calculated_category'] ?? 'Unavailable') ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="4">No saved history for this location.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php return (string)ob_get_clean();
}
