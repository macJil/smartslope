<?php
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