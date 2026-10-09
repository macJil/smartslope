<?php
// Local test provider only. Never serve this through the website's Apache host.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/failure') {
    http_response_code(503);
    echo '{"error":"Test provider unavailable"}';
    exit;
}
if ($path !== '/weather') {
    http_response_code(404);
    exit;
}
$end = intdiv(time(), 3600) * 3600;
$hourly = ['time' => [], 'precipitation' => [], 'precipitation_probability' => [],
    'soil_moisture_9_to_27cm' => [], 'soil_moisture_27_to_81cm' => []];
for ($hour = -72; $hour <= 24; $hour++) {
    $hourly['time'][] = gmdate('Y-m-d\TH:i', $end + $hour * 3600);
    $hourly['precipitation'][] = 1;
    $hourly['precipitation_probability'][] = 60;
    $hourly['soil_moisture_9_to_27cm'][] = 0.3;
    $hourly['soil_moisture_27_to_81cm'][] = 0.4;
}
echo json_encode([
    'current' => ['time' => gmdate('Y-m-d\TH:i', $end), 'temperature_2m' => 20,
        'relative_humidity_2m' => 80, 'wind_speed_10m' => 5, 'precipitation' => 1, 'weather_code' => 3],
    'hourly' => $hourly, 'utc_offset_seconds' => 0,
    'current_units' => ['temperature_2m' => '°C', 'relative_humidity_2m' => '%',
        'wind_speed_10m' => 'km/h', 'precipitation' => 'mm', 'weather_code' => 'wmo code'],
    'hourly_units' => ['precipitation' => 'mm', 'precipitation_probability' => '%',
        'soil_moisture_9_to_27cm' => 'm³/m³', 'soil_moisture_27_to_81cm' => 'm³/m³'],
], JSON_UNESCAPED_UNICODE);
