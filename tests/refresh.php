<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
// Run with PDO/MySQL enabled and cURL disabled; HTTP is replaced by a fixture.
if (PHP_SAPI !== 'cli') exit;
if (extension_loaded('curl')) {
    fwrite(STDERR, "Run this test with php -n and PDO/MySQL extensions, without cURL.\n");
    exit(1);
}
if (!str_ends_with($config['db_name'], '_test')) throw new RuntimeException('Use a disposable *_test database.');

foreach (['CURLOPT_RETURNTRANSFER', 'CURLOPT_TIMEOUT', 'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST', 'CURLINFO_HTTP_CODE'] as $index => $name) {
    define($name, $index + 1);
}
$requests = 0;
$providerStatus = 200;
if (!extension_loaded('curl')) {
    function curl_init($url) { global $requestedUrl; $requestedUrl = $url; return 1; }
    function curl_setopt($handle, $option, $value) { return true; }
    function curl_exec($handle) { global $fixture, $requests; $requests++; return json_encode($fixture); }
    function curl_getinfo($handle, $option) { global $providerStatus; return $providerStatus; }
    function curl_close($handle) {}
}

$end = intdiv(time(), 3600) * 3600;
$hourly = ['time'=>[], 'precipitation'=>[], 'precipitation_probability'=>[], 'soil_moisture_9_to_27cm'=>[], 'soil_moisture_27_to_81cm'=>[]];
for ($hour = -72; $hour <= 24; $hour++) {
    $hourly['time'][] = gmdate('Y-m-d\TH:i', $end + $hour * 3600);
    $hourly['precipitation'][] = 1;
    $hourly['precipitation_probability'][] = 60;
    $hourly['soil_moisture_9_to_27cm'][] = 0.3;
    $hourly['soil_moisture_27_to_81cm'][] = 0.4;
}
$fixture = [
    'current'=>['time'=>gmdate('Y-m-d\TH:i', $end), 'temperature_2m'=>20, 'relative_humidity_2m'=>80, 'wind_speed_10m'=>5, 'precipitation'=>1, 'weather_code'=>3],
    'hourly'=>$hourly, 'utc_offset_seconds'=>0,
    'current_units'=>['temperature_2m'=>'°C', 'relative_humidity_2m'=>'%', 'wind_speed_10m'=>'km/h', 'precipitation'=>'mm', 'weather_code'=>'wmo code'],
    'hourly_units'=>['precipitation'=>'mm', 'precipitation_probability'=>'%', 'soil_moisture_9_to_27cm'=>'m³/m³', 'soil_moisture_27_to_81cm'=>'m³/m³'],
];
$checks = 0;
function check_refresh($ok, $message) {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
$location = get_locations()[0];
$before = (int)db()->query("SELECT COUNT(*) FROM events WHERE type='reading'")->fetchColumn();
$first = refresh_location((int)$location['id']);
$second = refresh_location((int)$location['id']);
check_refresh($requests === 2 && $first['id'] !== $second['id'], 'Two refreshes request twice and save separate snapshots.');
check_refresh((float)$second['rainfall_24h'] === 24.0 && (float)$second['rainfall_72h'] === 72.0, 'Rainfall windows saved.');
check_refresh($second['assessment']['current_category'] === 'normal', 'Same risk threshold applied.');
check_refresh(json_decode($second['provider_payload'], true)['response']['hourly'] === $hourly, 'Provider inputs saved.');
check_refresh(str_contains($requestedUrl, 'past_hours=73') && str_contains($requestedUrl, 'forecast_hours=25'), 'Provider request windows unchanged.');
$providerStatus = 503;
try {
    refresh_location((int)$location['id']);
    throw new LogicException('Provider failure accepted.');
} catch (RuntimeException $error) {
    check_refresh($error->getMessage() === 'Weather provider is unavailable.', 'Provider failure handled.');
}
check_refresh((int)db()->query("SELECT COUNT(*) FROM events WHERE type='reading'")->fetchColumn() === $before + 2, 'Failed provider call saves nothing and preserves history.');
db()->prepare('DELETE FROM events WHERE id IN (?, ?)')->execute([$first['id'], $second['id']]);
echo "$checks refresh checks passed (fixture provider, real database).\n";
