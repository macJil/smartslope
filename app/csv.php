<?php

declare(strict_types=1);

function csv_cell($value): string
{
    $value = (string)($value ?? '');
    return preg_match("/^(?:'|[\s]*[=+\-@\t\r])/u", $value) ? "'" . $value : $value;
}

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
    if ($time === null) {
        return 'Not recorded';
    }
    $date = new DateTimeImmutable('@' . $time);
    $date = $date->setTimezone(new DateTimeZone('Asia/Manila'));
    return $date->format('Y-m-d H:i:s');
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
// Readings and reports are downloads; only Locations CSV supports import.
function export_readings_csv(array $readings): string
{
    $rows = [['Reading ID','Location','Stored category','Calculated rainfall category','Data status',
    'Rainfall - 1 hour (mm)','Rainfall - 24 hours (mm)','Rainfall - 72 hours (mm)',
    'Provider valid time (PHT)','Rainfall window end (PHT)','Saved at (PHT)',
    'Forecast rainfall - next 24 hours (mm)','Maximum hourly rain chance (%)',
    'Temperature (C)','Humidity (%)','Wind speed (km/h)',
    'Modeled soil moisture - 9 to 27 cm (m3/m3)','Modeled soil moisture - 27 to 81 cm (m3/m3)',
    'Data source','Rule version','Hourly inputs saved','Administrator adjusted','Adjustment history']];
    foreach ($readings as $reading) {
        $assessment = $reading['assessment'] ?? reading_assessment($reading);
        $rows[] = [(int)$reading['id'],csv_cell(ui_location_label($reading)),csv_label($reading['risk_level'] ?? null),csv_label($assessment['calculated_category']),csv_label($assessment['data_status']),
        $reading['rainfall_1h'] ?? '', $reading['rainfall_24h'] ?? '', $reading['rainfall_72h'] ?? '',
        csv_date($reading['observed_at'] ?? null),csv_date($assessment['rainfall_window_end'] ?? null),csv_date($reading['created_at'] ?? null),
        $reading['rainfall_forecast_24h'] ?? '', $reading['precipitation_probability_24h'] ?? '',
        $reading['temperature'] ?? '', $reading['humidity'] ?? '', $reading['wind_speed'] ?? '',
        $reading['soil_moisture_9_27cm'] ?? '', $reading['soil_moisture_27_81cm'] ?? '',
        csv_cell(ui_source($reading['source'] ?? null)),csv_cell($reading['rule_version'] ?? 'Legacy - not recorded'),
        empty($reading['provider_payload']) ? 'No' : 'Yes',$assessment['adjusted'] ? 'Yes' : 'No',csv_cell(csv_adjustments($reading['adjustment_log'] ?? null))];
    }
    return csv_rows($rows);
}

function export_reports_csv(array $reports): string
{
    $rows = [['Report ID','Location','Landmark supplied by reporter','Observed condition','Full message','Status',
    'Occurred at (PHT)','Submitted at (PHT)','Reporter','Contact phone','Contact email','Last reviewer ID','Last reviewed at (PHT)']];
    foreach ($reports as $report) {
        $rows[] = [(int)$report['id'],csv_cell(ui_location_label($report)),csv_cell($report['house_landmark'] ?? ''),
        csv_cell(REPORT_TYPES[$report['report_type'] ?? ''] ?? 'Not recorded'),csv_cell($report['message']),csv_label($report['status']),
        csv_date($report['occurred_at'] ?? null),csv_date($report['created_at'] ?? null),csv_cell($report['reporter_name'] ?? 'Not recorded'),
        csv_cell($report['contact_phone'] ?? ''),csv_cell($report['contact_email'] ?? ''),$report['reviewed_by'] ?? '',csv_date($report['reviewed_at'] ?? null)];
    }
    return csv_rows($rows);
}
/** Stable secret in the site's existing protected .env; never included in CSV. */
function csv_signing_key(): string
{
    $key = env_value('CSV_SIGNING_KEY');
    if ($key !== '') {
        if (!preg_match('/^[a-f0-9]{64}$/i', $key)) {
            throw new RuntimeException('CSV_SIGNING_KEY must contain 64 hexadecimal characters.');
        }
        return $key;
    }
    $handle = @fopen(APP_ROOT . '/.env', 'c+');
    if (!$handle) {
        throw new RuntimeException('Cannot create the CSV verification key. Make .env writable or set CSV_SIGNING_KEY to a 64-character hexadecimal secret.');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Cannot lock CSV settings.');
        }
        $text = (string)stream_get_contents($handle);
        if (preg_match('/^CSV_SIGNING_KEY=([a-f0-9]{64})\s*$/mi', $text, $matches)) {
            return $matches[1];
        }
        $key = bin2hex(random_bytes(32));
        fseek($handle, 0, SEEK_END);
        if (fwrite($handle, "\nCSV_SIGNING_KEY=" . $key . "\n") === false || !fflush($handle)) {
            throw new RuntimeException('Cannot save the CSV verification key.');
        }
        @chmod(APP_ROOT . '/.env', 0600);
        return $key;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
const LOCATION_CSV_HEADERS = ['Location ID','Location name','Purok','Landmark','Latitude','Longitude','Status'];
function export_locations_csv(array $locations, ?string $key = null): string
{
    if (count($locations) > 5000) {
        throw new RuntimeException('Locations export exceeds the 5,000-location import limit.');
    }
    $rows = [['SmartSlope locations export','Version 1'],['Exported at (PHT)',csv_date(gmdate('Y-m-d H:i:s'))],LOCATION_CSV_HEADERS];
    foreach ($locations as $location) {
        $rows[] = [(int)$location['id'],csv_cell($location['name']),csv_cell($location['purok'] ?? ''),csv_cell($location['landmark'] ?? ''),
        $location['lat'] ?? '', $location['lng'] ?? '',(int)$location['active'] === 1 ? 'Active' : 'Inactive'];
    }
    $body = csv_rows($rows, false);
    return "\xEF\xBB\xBF" . $body . csv_rows([['Verification code (do not edit)',hash_hmac('sha256', $body, $key ?? csv_signing_key())]], false);
}

function csv_import_text(string $value): string
{
    if (str_starts_with($value, "'")) {
        return substr($value, 1);
    }
    return $value;
}

function parse_locations_csv(string $csv, ?string $key = null): array
{
    $invalid = 'Invalid/not applicable CSV. Import an unchanged Locations CSV downloaded from this website.';
    if (strlen($csv) > 5 * 1024 * 1024 || !preg_match('//u', $csv) || str_contains($csv, "\0")) {
        throw new InvalidArgumentException($invalid);
    }
    if (str_starts_with($csv, "\xEF\xBB\xBF")) {
        $csv = substr($csv, 3);
    }
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, $csv);
    rewind($stream);
    $rows = [];
    try {
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
            if (count($rows) > 5004) {
                throw new InvalidArgumentException('CSV exceeds the 5,000-location limit.');
            }
        }
    } finally {
        fclose($stream);
    }
    $signature = array_pop($rows);
    // Check the file layout before checking its verification code.
    $validHeading = ($rows[0] ?? []) === ['SmartSlope locations export', 'Version 1'];
    $validDate = ($rows[1][0] ?? '') === 'Exported at (PHT)' && count($rows[1] ?? []) === 2;
    $validColumns = ($rows[2] ?? []) === LOCATION_CSV_HEADERS;
    $validSignatureRow = count($signature ?? []) === 2 && ($signature[0] ?? '') === 'Verification code (do not edit)';
    if (!$validHeading || !$validDate || !$validColumns || !$validSignatureRow) {
        throw new InvalidArgumentException($invalid);
    }
    // An unchanged export from this installation must have the same signature.
    $expectedSignature = hash_hmac('sha256', csv_rows($rows, false), $key ?? csv_signing_key());
    if (!hash_equals($expectedSignature, $signature[1] ?? '')) {
        throw new InvalidArgumentException($invalid);
    }
    $result = [];
    $seenIds = [];
    $seenPoints = [];
    foreach (array_slice($rows, 3) as $row) {
        if (count($row) !== 7) {
            throw new InvalidArgumentException($invalid);
        }
        [$id,$name,$purok,$landmark,$lat,$lng,$status] = $row;
        // Undo the text prefix added during export, once per text column.
        $name = csv_import_text($name);
        $purok = csv_import_text($purok);
        $landmark = csv_import_text($landmark);
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $latitude = finite_number($lat, -90, 90);
        $longitude = finite_number($lng, -180, 180);
        $point = $lat . ',' . $lng;
        if (
            !$id || $id < 1 || isset($seenIds[$id]) || isset($seenPoints[$point]) || trim($name) === '' || strlen($name) > 600 || strlen($purok) > 400 || strlen($landmark) > 1020 ||
            $latitude === null || $longitude === null || !is_in_irisan($latitude, $longitude) || !in_array($status, ['Active','Inactive'], true)
        ) {
            throw new InvalidArgumentException($invalid . ' A location is invalid or outside Irisan.');
        }
        $seenIds[$id] = true;
        $seenPoints[$point] = true;
        $result[] = ['id' => $id,'name' => $name,'purok' => $purok,'landmark' => $landmark,'lat' => $latitude,'lng' => $longitude,'active' => $status === 'Active' ? 1 : 0];
    }
    return $result;
}

function import_locations_csv(array $rows): array
{
    $pdo = db();
    $result = ['added' => 0,'updated' => 0,'unchanged' => 0];
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $find = $pdo->prepare('SELECT * FROM locations WHERE lat=? AND lng=? FOR UPDATE');
            $find->execute([$row['lat'],$row['lng']]);
            $existing = $find->fetch();
            if ($existing) {
                $same = $existing['name'] === $row['name'] && ($existing['purok'] ?? '') === $row['purok'] && ($existing['landmark'] ?? '') === $row['landmark'] && (int)$existing['active'] === $row['active'];
                if ($same) {
                    $result['unchanged']++;
                    continue;
                }
                $stmt = $pdo->prepare('UPDATE locations SET name=?,purok=?,landmark=?,active=? WHERE id=?');
                $stmt->execute([$row['name'],$row['purok'],$row['landmark'],$row['active'],$existing['id']]);
                $result['updated']++;
            } else {
                // Match by coordinates, never repoint an existing ID or detach its history.
                $stmt = $pdo->prepare("INSERT INTO locations (name,purok,landmark,lat,lng,active,susceptibility) VALUES (?,?,?,?,?,?,'unknown')");
                $stmt->execute([$row['name'],$row['purok'],$row['landmark'],$row['lat'],$row['lng'],$row['active']]);
                $result['added']++;
            }
        }
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
