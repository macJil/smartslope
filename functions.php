<?php
/**
 * SmartSlope - Core Functions
 * All helper functions for authentication, database operations, and utilities
 */

// ============================================================================
// SESSION & AUTHENTICATION
// ============================================================================

/**
 * Start secure session
 */
function start_session() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $isLocalhost = isset($_SERVER['HTTP_HOST']) && 
                      (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                       strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
        
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $isHttps && !$isLocalhost,
            'samesite' => 'Lax',
            'path' => '/'
        ]);
        session_start();
    }
}

/**
 * Clear and destroy session
 */
function clear_session() {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function is_admin() {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Require login - redirect if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php");
        exit;
    }
}

/**
 * Require admin - redirect if not admin
 */
function require_admin() {
    if (!is_admin()) {
        header("Location: index.php");
        exit;
    }
}

// ============================================================================
// INPUT/OUTPUT HELPERS
// ============================================================================

/**
 * HTML escape output
 */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Get POST value safely
 */
function post($key, $default = '') {
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

/**
 * Get GET value safely
 */
function get($key, $default = '') {
    $value = $_GET[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

/**
 * Build URL with base path handling
 */
function url($path = '') {
    static $base = null;
    if ($base === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        $base = ($scriptDir === '.' || $scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/\\');
    }
    $path = ltrim($path, '/\\');
    return ($base !== '' ? $base . '/' : '') . $path;
}

/**
 * Redirect to URL
 */
function redirect($path) {
    header("Location: " . url($path), true, 303);
    exit;
}

/**
 * Flash message system
 */
function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}

// ============================================================================
// USER FUNCTIONS
// ============================================================================

/**
 * Authenticate user
 */
function authenticate_user($username, $password) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return null;
}

/**
 * Create new user
 */
function create_user($fullName, $username, $email, $phone, $password) {
    $pdo = db();
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users 
                          (full_name, username, email, phone, password, role, created_at)
                          VALUES (?, ?, ?, ?, ?, 'user', NOW())");
    return $stmt->execute([$fullName, $username, $email, $phone, $hashed]);
}

/**
 * Check if username, email, or phone exists
 */
function user_exists($username, $email, $phone) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users 
                          WHERE username = ? OR email = ? OR phone = ?");
    $stmt->execute([$username, $email, $phone]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Get user by ID
 */
function get_user_by_id($id) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Get all users
 */
function get_all_users() {
    $pdo = db();
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

// ============================================================================
// LOCATION FUNCTIONS
// ============================================================================

/**
 * Get all active locations
 */
function get_all_locations() {
    $pdo = db();
    $stmt = $pdo->query("SELECT * FROM locations WHERE active = 1 ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Get location by ID
 */
function get_location_by_id($id) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Create new location
 */
function create_location($name, $purok, $landmark, $lat, $lng, $susceptibility = 'unknown') {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO locations 
                          (name, purok, landmark, lat, lng, susceptibility, active, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$name, $purok, $landmark, $lat, $lng, $susceptibility]);
    return $pdo->lastInsertId();
}

/**
 * Update location
 */
function update_location($id, $name, $purok, $landmark, $lat, $lng, $susceptibility) {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE locations SET name = ?, purok = ?, landmark = ?, 
                          lat = ?, lng = ?, susceptibility = ? WHERE id = ?");
    $stmt->execute([$name, $purok, $landmark, $lat, $lng, $susceptibility, $id]);
    return $stmt->rowCount() > 0;
}

/**
 * Deactivate location
 */
function deactivate_location($id) {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE locations SET active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}

/**
 * Check if coordinates are in Irisan boundary
 */
function is_in_irisan($lat, $lng) {
    $geo = json_decode(file_get_contents(__DIR__ . '/assets/map/irisan.geojson'), true);
    if (!$geo) return false;
    
    $geometry = $geo['type'] === 'FeatureCollection'
        ? ($geo['features'][0]['geometry'] ?? null)
        : ($geo['geometry'] ?? $geo);
    
    if (!$geometry || !isset($geometry['coordinates'])) return false;
    
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
            if ($ringIndex === 0) {
                $inside = $crosses;
            } elseif ($crosses) {
                $inside = false;
            }
        }
        if ($inside) return true;
    }
    return false;
}

// ============================================================================
// READING FUNCTIONS
// ============================================================================

/**
 * Get latest reading for a location
 */
function get_latest_reading($locationId) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*
                          FROM events e JOIN readings r ON r.event_id = e.id
                          WHERE e.location_id = ? AND e.type = 'reading' AND r.archived = 0
                          ORDER BY r.observed_at DESC, e.id DESC LIMIT 1");
    $stmt->execute([$locationId]);
    return $stmt->fetch() ?: null;
}

/**
 * Get readings by location
 */
function get_readings_by_location($locationId, $limit = 50) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*
                          FROM events e JOIN readings r ON r.event_id = e.id
                          WHERE e.location_id = ? AND e.type = 'reading' AND r.archived = 0
                          ORDER BY r.observed_at DESC, e.id DESC LIMIT ?");
    $stmt->bindValue(1, $locationId, PDO::PARAM_INT);
    $stmt->bindValue(2, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get all recent readings
 */
function get_all_readings($limit = 100) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT e.id, e.location_id, e.user_id, e.type, e.created_at, r.*,
                          l.name as location_name, l.purok, l.lat, l.lng
                          FROM events e
                          JOIN readings r ON r.event_id = e.id
                          JOIN locations l ON l.id = e.location_id
                          WHERE e.type = 'reading' AND r.archived = 0 AND l.active = 1
                          ORDER BY r.observed_at DESC, e.id DESC
                          LIMIT ?");
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Create new reading
 */
function create_reading($locationId, $userId, $data) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Create event
        $stmt = $pdo->prepare("INSERT INTO events (location_id, user_id, type, created_at) 
                              VALUES (?, ?, 'reading', NOW())");
        $stmt->execute([$locationId, $userId]);
        $eventId = $pdo->lastInsertId();
        
        // Create reading
        $stmt = $pdo->prepare("INSERT INTO readings 
                              (event_id, rainfall_1h, rainfall_24h, rainfall_72h, risk_level,
                               rainfall_forecast_24h, precipitation_probability_24h,
                               soil_moisture_9_27cm, soil_moisture_27_81cm, temperature, humidity,
                               wind_speed, weather_code, observed_at, source, rule_version)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'openmeteo', '1.0')");
        $stmt->execute([
            $eventId,
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
            $data['observed_at'] ?? date('Y-m-d H:i:s')
        ]);
        
        $pdo->commit();
        return $eventId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Archive reading
 */
function archive_reading($eventId) {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE readings r JOIN events e ON e.id = r.event_id
                          SET r.archived = 1 WHERE e.id = ? AND e.type = 'reading' AND r.archived = 0");
    $stmt->execute([$eventId]);
    return $stmt->rowCount() === 1;
}

// ============================================================================
// REPORT FUNCTIONS
// ============================================================================

/**
 * Create new report
 */
function create_report($locationId, $userId, $data) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Create event
        $stmt = $pdo->prepare("INSERT INTO events (location_id, user_id, type, created_at) 
                              VALUES (?, ?, 'report', NOW())");
        $stmt->execute([$locationId, $userId]);
        $eventId = $pdo->lastInsertId();
        
        // Create report
        $stmt = $pdo->prepare("INSERT INTO reports 
                              (event_id, location_id, message, contact_phone, contact_email,
                               house_landmark, report_type, occurred_at, status, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
        $stmt->execute([
            $eventId,
            $locationId,
            $data['message'],
            $data['contact_phone'] ?? null,
            $data['contact_email'] ?? null,
            $data['house_landmark'] ?? null,
            $data['report_type'] ?? 'other',
            $data['occurred_at'] ?? date('Y-m-d H:i:s')
        ]);
        
        $pdo->commit();
        return $eventId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get all reports
 */
function get_all_reports() {
    $pdo = db();
    $stmt = $pdo->query("SELECT r.*, e.id as event_id, e.created_at as event_created_at, e.user_id as reporter_id,
                          u.full_name as reporter_name, u.username as reporter_username,
                          u.email as reporter_email, u.phone as reporter_phone,
                          l.name as location_name, l.purok as location_purok
                          FROM reports r
                          JOIN events e ON e.id = r.event_id
                          LEFT JOIN users u ON u.id = e.user_id
                          LEFT JOIN locations l ON l.id = r.location_id
                          WHERE e.type = 'report'
                          ORDER BY e.created_at DESC");
    return $stmt->fetchAll();
}

/**
 * Get report by event ID
 */
function get_report_by_event_id($eventId) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT r.*, e.id as event_id, e.created_at as event_created_at, e.user_id as reporter_id,
                          u.full_name as reporter_name, u.username as reporter_username
                          FROM reports r
                          JOIN events e ON e.id = r.event_id
                          LEFT JOIN users u ON u.id = e.user_id
                          WHERE e.id = ? AND e.type = 'report'");
    $stmt->execute([$eventId]);
    return $stmt->fetch() ?: null;
}

/**
 * Get reports by location
 */
function get_reports_by_location($locationId) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT r.*, e.id as event_id, e.created_at as event_created_at,
                          u.full_name as reporter_name
                          FROM reports r
                          JOIN events e ON e.id = r.event_id
                          LEFT JOIN users u ON u.id = e.user_id
                          WHERE r.location_id = ? AND e.type = 'report'
                          ORDER BY e.created_at DESC");
    $stmt->execute([$locationId]);
    return $stmt->fetchAll();
}

/**
 * Get pending report counts by location
 */
function get_pending_counts() {
    $pdo = db();
    $stmt = $pdo->query("SELECT location_id, COUNT(*) as count
                          FROM reports r
                          JOIN events e ON e.id = r.event_id
                          WHERE e.type = 'report' AND r.status = 'pending'
                          GROUP BY location_id");
    $results = $stmt->fetchAll();
    $counts = [];
    foreach ($results as $row) {
        $counts[$row['location_id']] = (int)$row['count'];
    }
    return $counts;
}

/**
 * Update report status
 */
function update_report_status($eventId, $status, $adminId) {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE reports SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                          WHERE event_id = ?");
    $stmt->execute([$status, $adminId, $eventId]);
    return $stmt->rowCount() === 1;
}

/**
 * Delete report
 */
function delete_report($eventId) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM reports WHERE event_id = ?");
        $stmt->execute([$eventId]);
        
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND type = 'report'");
        $stmt->execute([$eventId]);
        
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ============================================================================
// RISK ASSESSMENT
// ============================================================================

/**
 * Calculate risk level based on rainfall thresholds
 */
function calculate_risk_level($rainfall_1h, $rainfall_24h, $rainfall_72h) {
    if ($rainfall_1h >= RISK_HIGH_1H || $rainfall_24h >= RISK_HIGH_24H || $rainfall_72h >= RISK_HIGH_72H) {
        return 'high';
    } elseif ($rainfall_1h >= RISK_MEDIUM_1H || $rainfall_24h >= RISK_MEDIUM_24H || $rainfall_72h >= RISK_MEDIUM_72H) {
        return 'medium';
    } elseif ($rainfall_1h >= RISK_LOW_1H || $rainfall_24h >= RISK_LOW_24H || $rainfall_72h >= RISK_LOW_72H) {
        return 'normal';
    } else {
        // Only return 'low' if all three values are present and valid
        if ($rainfall_1h !== null && $rainfall_24h !== null && $rainfall_72h !== null) {
            return 'low';
        }
        return null;
    }
}

/**
 * Assess reading and return assessment array
 */
function assess_reading($reading) {
    if (!$reading) {
        return [
            'risk_level' => null,
            'category' => null,
            'current_category' => null,
            'data_status' => 'unavailable',
            'is_current' => false
        ];
    }
    
    $risk = calculate_risk_level(
        $reading['rainfall_1h'] ?? null,
        $reading['rainfall_24h'] ?? null,
        $reading['rainfall_72h'] ?? null
    );
    
    $observedAt = $reading['observed_at'] ?? '';
    $now = time();
    $maxAge = READING_MAX_AGE_SECONDS;
    
    if ($observedAt && $observedAt !== '0000-00-00 00:00:00') {
        $observedTime = strtotime($observedAt);
        $age = $now - $observedTime;
        $isCurrent = $age <= $maxAge;
    } else {
        $isCurrent = false;
    }
    
    return [
        'risk_level' => $risk,
        'category' => $risk,
        'current_category' => $isCurrent ? $risk : null,
        'data_status' => $isCurrent ? 'current' : ($risk ? 'outdated' : 'incomplete'),
        'is_current' => $isCurrent
    ];
}

// ============================================================================
// WEATHER FUNCTIONS
// ============================================================================

/**
 * Fetch weather data from Open-Meteo API
 */
function fetch_openmeteo_data($lat, $lng) {
    $url = "https://api.open-meteo.com/v1/forecast?latitude=$lat&longitude=$lng"
         . "&hourly=precipitation,soil_moisture_9_27cm,soil_moisture_27_81cm,"
         . "temperature_2m,relativehumidity_2m,windspeed_10m,weathercode"
         . "&forecast_days=1&timezone=auto";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Weather API error: " . $error);
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Weather API: Invalid JSON response");
    }
    
    if (!isset($data['hourly'])) {
        throw new Exception("Weather API: Missing hourly data");
    }
    
    return $data;
}

/**
 * Process weather data to reading format
 */
function process_weather_to_reading($weatherData) {
    $hourly = $weatherData['hourly'] ?? [];
    
    $precipitation = $hourly['precipitation'] ?? [];
    $time = $hourly['time'] ?? [];
    
    $rainfall_1h = calculate_precipitation_total($precipitation, 1);
    $rainfall_24h = calculate_precipitation_total($precipitation, 24);
    $rainfall_72h = calculate_precipitation_total($precipitation, min(72, count($precipitation)));
    
    $riskLevel = calculate_risk_level($rainfall_1h, $rainfall_24h, $rainfall_72h);
    
    // Get the most recent observation time
    $observedAt = date('Y-m-d H:i:s');
    if (!empty($time)) {
        $lastTime = end($time);
        if ($lastTime) {
            $observedAt = $lastTime;
        }
    }
    
    return [
        'rainfall_1h' => $rainfall_1h,
        'rainfall_24h' => $rainfall_24h,
        'rainfall_72h' => $rainfall_72h,
        'risk_level' => $riskLevel,
        'rainfall_forecast_24h' => !empty($precipitation) ? $precipitation[0] : null,
        'precipitation_probability_24h' => null,
        'soil_moisture_9_27cm' => !empty($hourly['soil_moisture_9_27cm']) ? $hourly['soil_moisture_9_27cm'][0] : null,
        'soil_moisture_27_81cm' => !empty($hourly['soil_moisture_27_81cm']) ? $hourly['soil_moisture_27_81cm'][0] : null,
        'temperature' => !empty($hourly['temperature_2m']) ? $hourly['temperature_2m'][0] : null,
        'humidity' => !empty($hourly['relativehumidity_2m']) ? $hourly['relativehumidity_2m'][0] : null,
        'wind_speed' => !empty($hourly['windspeed_10m']) ? $hourly['windspeed_10m'][0] : null,
        'weather_code' => !empty($hourly['weathercode']) ? $hourly['weathercode'][0] : null,
        'observed_at' => $observedAt,
        'source' => 'openmeteo',
        'rule_version' => '1.0'
    ];
}

/**
 * Calculate total precipitation from array
 */
function calculate_precipitation_total($precipitationArray, $hours) {
    $count = min($hours, count($precipitationArray));
    $total = 0;
    for ($i = 0; $i < $count; $i++) {
        $total += $precipitationArray[$i] ?? 0;
    }
    return $total;
}

/**
 * Fetch and save weather data for a location
 */
function fetch_and_save_weather($locationId, $userId) {
    $location = get_location_by_id($locationId);
    if (!$location) {
        throw new Exception("Location not found");
    }
    
    $weatherData = fetch_openmeteo_data($location['lat'], $location['lng']);
    $readingData = process_weather_to_reading($weatherData);
    
    return create_reading($locationId, $userId, $readingData);
}

// ============================================================================
// ADDRESS LOOKUP
// ============================================================================

/**
 * Lookup address from coordinates using Nominatim
 */
function lookup_address($lat, $lng) {
    // Check cache first
    $cached = get_cached_address($lat, $lng);
    if ($cached) {
        return $cached;
    }
    
    if (!NOMINATIM_ENABLED) {
        return '';
    }
    
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng&zoom=18&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, NOMINATIM_USER_AGENT);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return '';
    }
    
    $data = json_decode($response, true);
    if (!isset($data['display_name'])) {
        return '';
    }
    
    $address = $data['display_name'];
    
    // Cache the result
    if ($address) {
        cache_address($lat, $lng, $address);
    }
    
    return $address;
}

/**
 * Get cached address
 */
function get_cached_address($lat, $lng) {
    $pdo = db();
    try {
        $stmt = $pdo->prepare("SELECT address FROM location_address_cache 
                              WHERE lat = ? AND lng = ? AND expires_at > NOW()");
        $stmt->execute([$lat, $lng]);
        $result = $stmt->fetch();
        return $result ? $result['address'] : null;
    } catch (Exception $e) {
        // Cache table might not exist
        return null;
    }
}

/**
 * Cache address
 */
function cache_address($lat, $lng, $address) {
    $pdo = db();
    try {
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt = $pdo->prepare("INSERT INTO location_address_cache (lat, lng, address, expires_at)
                              VALUES (?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE address = ?, expires_at = ?");
        $stmt->execute([$lat, $lng, $address, $expires, $address, $expires]);
    } catch (Exception $e) {
        // Cache table might not exist - silently fail
    }
}

// ============================================================================
// CSV EXPORT
// ============================================================================

/**
 * Export readings to CSV
 */
function export_readings_csv($locationId = null) {
    $pdo = db();
    
    if ($locationId) {
        $sql = "SELECT e.id, e.location_id, e.created_at, r.observed_at,
                      r.rainfall_1h, r.rainfall_24h, r.rainfall_72h, r.risk_level,
                      l.name as location_name, l.purok
               FROM events e
               JOIN readings r ON r.event_id = e.id
               JOIN locations l ON l.id = e.location_id
               WHERE e.location_id = ? AND e.type = 'reading' AND r.archived = 0
               ORDER BY r.observed_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$locationId]);
    } else {
        $sql = "SELECT e.id, e.location_id, e.created_at, r.observed_at,
                      r.rainfall_1h, r.rainfall_24h, r.rainfall_72h, r.risk_level,
                      l.name as location_name, l.purok
               FROM events e
               JOIN readings r ON r.event_id = e.id
               JOIN locations l ON l.id = e.location_id
               WHERE e.type = 'reading' AND r.archived = 0
               ORDER BY r.observed_at DESC";
        $stmt = $pdo->query($sql);
    }
    
    $readings = $stmt->fetchAll();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="readings_' . date('Ymd') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Location', 'Purok', 'Created At', 'Observed At',
                       'Rainfall 1h (mm)', 'Rainfall 24h (mm)', 'Rainfall 72h (mm)', 'Risk Level']);
    
    foreach ($readings as $r) {
        fputcsv($output, [
            $r['id'],
            $r['location_name'],
            $r['purok'],
            $r['created_at'],
            $r['observed_at'],
            $r['rainfall_1h'],
            $r['rainfall_24h'],
            $r['rainfall_72h'],
            $r['risk_level']
        ]);
    }
    
    fclose($output);
    exit;
}

// ============================================================================
// DATE FORMATTING
// ============================================================================

/**
 * Format date for display (Manila time)
 */
function local_date($value) {
    if (!$value || $value === '0000-00-00 00:00:00') {
        return '';
    }
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A');
    } catch (Exception $e) {
        return $value;
    }
}

/**
 * Format date for input fields
 */
function date_for_input($value) {
    if (!$value || $value === '0000-00-00 00:00:00') {
        return '';
    }
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $date->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d\TH:i');
    } catch (Exception $e) {
        return $value;
    }
}

// ============================================================================
// VALIDATION HELPERS
// ============================================================================

/**
 * Sanitize string input
 */
function sanitize_str($value, $maxLength = 255) {
    $value = trim((string)$value);
    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

/**
 * Validate email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone
 */
function validate_phone($phone) {
    return preg_match('/^\+?[0-9]{10,15}$/', $phone);
}

/**
 * Validate username
 */
function validate_username($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
}

/**
 * Validate password length
 */
function validate_password($password) {
    return strlen($password) >= 8;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Get risk level badge HTML
 */
function get_risk_badge($assessment) {
    $category = $assessment['category'] ?? null;
    if (!$category || !in_array($category, ['low', 'normal', 'medium', 'high'], true)) {
        $category = 'unavailable';
    }
    
    $colors = [
        'low' => 'success',
        'normal' => 'primary',
        'medium' => 'warning text-dark',
        'high' => 'danger',
        'unavailable' => 'secondary'
    ];
    
    $color = $colors[$category] ?? 'secondary';
    $label = ucfirst($category);
    
    $html = '<span class="badge bg-' . e($color) . '">' . e($label) . '</span>';
    
    if ($category && ($assessment['data_status'] ?? '') !== 'current') {
        $html .= '<small class="d-block text-muted mt-1">Last saved category</small>';
    }
    
    return $html;
}

/**
 * Get risk level color class for map markers
 */
function get_risk_color_class($assessment) {
    $category = $assessment['category'] ?? null;
    if (!$category || !in_array($category, ['low', 'normal', 'medium', 'high'], true)) {
        return 'marker-color-unknown';
    }
    return 'marker-color-' . $category;
}

/**
 * Normalize risk level for JavaScript
 */
function normalize_risk($category) {
    if (!$category || !in_array($category, ['low', 'normal', 'medium', 'high'], true)) {
        return 'unavailable';
    }
    return $category;
}

/**
 * Get location label with address
 */
function get_location_label($location) {
    $parts = [];
    if (!empty($location['name'])) {
        $parts[] = $location['name'];
    }
    if (!empty($location['purok'])) {
        $parts[] = 'Purok ' . $location['purok'];
    }
    if (!empty($location['landmark'])) {
        $parts[] = $location['landmark'];
    }
    if (!empty($location['address'])) {
        $parts[] = $location['address'];
    }
    
    if (empty($parts)) {
        return 'Location ' . ($location['id'] ?? 'Unknown');
    }
    
    return implode(', ', $parts);
}

/**
 * Count readings by category
 */
function count_readings_by_category($readings, $category) {
    $count = 0;
    foreach ($readings as $r) {
        $assessment = assess_reading($r);
        if (($assessment['current_category'] ?? null) === $category) {
            $count++;
        }
    }
    return $count;
}

/**
 * Get reading counts for dashboard
 */
function get_reading_counts($readings) {
    return [
        'low' => count_readings_by_category($readings, 'low'),
        'normal' => count_readings_by_category($readings, 'normal'),
        'medium' => count_readings_by_category($readings, 'medium'),
        'high' => count_readings_by_category($readings, 'high')
    ];
}
