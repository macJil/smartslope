<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$readings = (new ReadingRepository($pdo))->adminList();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="smartslope-api-readings-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, max-age=0');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit('Could not create CSV output.');
}

// The BOM helps spreadsheet applications detect UTF-8 correctly.
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, [
    'Reading ID',
    'Observed At (Asia/Manila)',
    'Location',
    'Purok / Zone',
    'Rainfall 1h (mm)',
    'Rainfall 24h (mm)',
    'Rainfall 72h (mm)',
    'Risk Level',
    'Source',
    'Source URL',
], ',', '"', '\\');

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
        (int) $reading['reading_id'],
        display_local_datetime($reading['observed_at']),
        $safeCsvText((string) $reading['location_name']),
        $safeCsvText($reading['purok_zone'] !== null ? (string) $reading['purok_zone'] : ''),
        $reading['rainfall_1h_mm'],
        $reading['rainfall_24h_mm'],
        $reading['rainfall_72h_mm'],
        $safeCsvText((string) $reading['risk_level']),
        $safeCsvText((string) $reading['source_name']),
        $safeCsvText($reading['source_url'] !== null ? (string) $reading['source_url'] : ''),
    ], ',', '"', '\\');
}

fclose($output);
exit;
