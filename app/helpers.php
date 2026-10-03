<?php
declare(strict_types=1);

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Get POST value safely

function post(string $key, $default = '') {
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

// Get GET value safely

function get(string $key, $default = '') {
    $value = $_GET[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

// Build URL

function url(string $path = ''): string {
    $base = '/' . trim(APP_BASE_PATH, '/');
    return rtrim($base, '/') . '/' . ltrim($path, '/');
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

function is_in_irisan(float $lat, float $lng): bool {
    $geo = json_decode((string)file_get_contents(APP_ROOT . '/assets/map/irisan.geojson'), true);
    if (!is_array($geo)) return false;
    $geometry = $geo['type'] === 'FeatureCollection'
        ? ($geo['features'][0]['geometry'] ?? null)
        : ($geo['geometry'] ?? $geo);
    $rings = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : ($geometry['coordinates'] ?? []);
    foreach ($rings as $polygon) {
        $inside = false;
        foreach ($polygon as $ringIndex => $ring) {
            $crosses = false;
            for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
                [$x1, $y1] = $ring[$i]; [$x2, $y2] = $ring[$j];
                if (($y1 > $lat) !== ($y2 > $lat) &&
                    $lng < ($x2 - $x1) * ($lat - $y1) / ($y2 - $y1) + $x1) $crosses = !$crosses;
            }
            if ($ringIndex === 0) $inside = $crosses;
            elseif ($crosses) $inside = false;
        }
        if ($inside) return true;
    }
    return false;
}

// ============================================================================
// CSV EXPORT
// ============================================================================


function export_readings_csv(array $readings): string {
    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, [
        'ID', 'Location', 'Risk', '1h Rainfall', '24h Rainfall', '72h Rainfall',
        '24h Forecast Rainfall', '24h Precipitation Probability',
        'Soil Moisture 9-27cm', 'Soil Moisture 27-81cm', 'Observed At UTC', 'Retrieved At UTC', 'Data Status', 'Calculated Category', 'Administrator Adjusted', 'Source', 'Rule version', 'Rainfall window end UTC', 'Saved hourly inputs', 'Adjustment log JSON'
    ]);

    foreach ($readings as $r) {
        fputcsv($stream, [
            $r['id'],
            csv_cell($r['location_name'] ?? ''),
            $r['risk_level'],
            $r['rainfall_1h'] ?? '',
            $r['rainfall_24h'] ?? '',
            $r['rainfall_72h'] ?? '',
            $r['rainfall_forecast_24h'] ?? '',
            $r['precipitation_probability_24h'] ?? '',
            $r['soil_moisture_9_27cm'] ?? '',
            $r['soil_moisture_27_81cm'] ?? '',
            $r['observed_at'],
            $r['created_at'] ?? '',
            $r['assessment']['data_status'] ?? 'unavailable',
            $r['assessment']['calculated_category'] ?? '',
            !empty($r['assessment']['adjusted']) ? 'yes' : 'no',
            csv_cell($r['source'] ?? ''), csv_cell($r['rule_version'] ?? 'legacy-unrecorded'),
            $r['assessment']['rainfall_window_end'] ?? '', empty($r['provider_payload']) ? 'no' : 'yes',
            csv_cell($r['adjustment_log'] ?? '')
        ]);
    }

    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    return $csv;
}


function csv_cell($value): string {
    $value = (string)$value;
    return preg_match('/^[\s]*[=+\-@\t\r]/u', $value) ? "'" . $value : $value;
}
