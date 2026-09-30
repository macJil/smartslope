<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$locationId = filter_var($_GET['location_id'] ?? null, FILTER_VALIDATE_INT);
if (!$locationId || $locationId < 1) {
    http_response_code(400);
    exit('Select a map location first.');
}
$location = (new LocationRepository($pdo))->find((int)$locationId);
if (!$location || !(int)$location['is_active']) {
    http_response_code(404);
    exit('Location unavailable.');
}
$readings = (new ReadingRepository($pdo))->currentForLocation((int)$locationId, null);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="smartslope-location-' . (int)$locationId . '-current-readings-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, max-age=0');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit('Could not create CSV output.');
}

// The BOM helps spreadsheet applications detect UTF-8 correctly.
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, [
    'Observation ID',
    'Observed At (Asia/Manila)',
    'Location',
    'Fetched At (Asia/Manila)',
    'Temperature (C)',
    'Humidity (%)',
    'Precipitation (mm)',
    'Rain (mm)',
    'Showers (mm)',
    'Wind speed (km/h)',
    'Wind gusts (km/h)',
], ',', '"', '');

$safeCsvText = static function (?string $value): string {
    $value = $value ?? '';
    // Prevent spreadsheet formula execution for text originating in API data.
    if ($value !== '' && preg_match('/\A[\s]*[=+@-]/u', $value) === 1) {
        return "'" . $value;
    }
    return $value;
};

foreach ($readings as $reading) {
    fputcsv($output, [
        (int) $reading['observation_id'],
        display_local_datetime($reading['observed_at']),
        $safeCsvText((string) $reading['location_name']),
        display_local_datetime($reading['fetched_at']),
        $reading['temperature_2m'],
        $reading['relative_humidity_2m'],
        $reading['precipitation'],
        $reading['rain'],
        $reading['showers'],
        $reading['wind_speed_10m'],
        $reading['wind_gusts_10m'],
    ], ',', '"', '');
}

fclose($output);
exit;
