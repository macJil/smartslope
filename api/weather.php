<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../app/json.php';

define('SMARTSLOPE_JSON_REQUEST', true);
require_once __DIR__ . '/../app/bootstrap.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['user', 'admin'], true)) {
    respond_json(401, ['error' => 'authentication_required']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond_json(405, ['error' => 'method_not_allowed']);
}

if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    respond_json(403, ['error' => 'invalid_csrf_token']);
}

$locationId = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
if (!$locationId || $locationId < 1) {
    respond_json(400, ['error' => 'invalid_location_id']);
}

session_write_close(); // Do not lock the resident session during the outbound request.

try {
    $locationStatement = $pdo->prepare(
        "SELECT l.location_id, l.location_name, l.purok_zone, l.latitude, l.longitude
         FROM locations AS l
         INNER JOIN barangays AS b ON b.barangay_id = l.barangay_id
         WHERE l.location_id = :location_id
           AND l.is_active = 1 AND b.is_active = 1
           AND b.barangay_name = 'Barangay Irisan' AND b.city_name = 'Baguio City'
         LIMIT 1"
    );
    $locationStatement->execute(['location_id' => (int) $locationId]);
    $location = $locationStatement->fetch();

} catch (PDOException $exception) {
    error_log('SmartSlope location lookup failed: ' . $exception->getMessage());
    respond_json(503, ['error' => 'database_unavailable']);
}

if (!$location) {
    respond_json(404, ['error' => 'location_not_found']);
}
if (!is_numeric($location['latitude']) || !is_numeric($location['longitude'])
    || (float) $location['latitude'] < -90 || (float) $location['latitude'] > 90
    || (float) $location['longitude'] < -180 || (float) $location['longitude'] > 180) {
    respond_json(422, ['error' => 'location_coordinates_missing']);
}

try {
    $weather = (new WeatherApiClient())->fetch(
        (float) $location['latitude'],
        (float) $location['longitude']
    );

    $timezone = new DateTimeZone('Asia/Manila');
    $currentTime = new DateTimeImmutable((string) $weather['current']['time'], $timezone);
    $weather['current']['time'] = $currentTime->format(DateTimeInterface::ATOM);
    $earliestTime = $currentTime->modify('-72 hours');
    $hourlyData = $weather['hourly'];
    $hourlyFields = [
        'temperature_2m', 'relative_humidity_2m', 'apparent_temperature',
        'precipitation', 'rain', 'showers', 'weather_code', 'cloud_cover',
        'pressure_msl', 'surface_pressure', 'wind_speed_10m',
        'wind_direction_10m', 'wind_gusts_10m',
    ];

    $history = [];
    foreach ($hourlyData['time'] as $index => $timeValue) {
        if (!is_string($timeValue)) {
            continue;
        }
        $time = new DateTimeImmutable($timeValue, $timezone);
        if ($time > $currentTime || $time < $earliestTime) {
            continue;
        }

        $row = ['time' => $time->format(DateTimeInterface::ATOM)];
        foreach ($hourlyFields as $field) {
            $value = $hourlyData[$field][$index] ?? null;
            $row[$field] = is_numeric($value) ? (float) $value : null;
        }
        $history[$time->getTimestamp()] = $row;
    }

    ksort($history, SORT_NUMERIC);
    $history = array_values(array_slice($history, -72, null, true));
    if (!$history) {
        respond_json(502, ['error' => 'weather_history_unavailable']);
    }

    $lastHourlyReading=$history[count($history)-1];
    $observedAtUtc=(new DateTimeImmutable($lastHourlyReading['time']))
        ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $retrievedAt=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $age=$retrievedAt->getTimestamp()-$currentTime->getTimestamp();
    $stale=$age>7200 || $age< -600;
    $rainfall1h=$rainfall24h=$rainfall72h=null;
    $riskLevel=null;
    $alert=null;
    $savedObservations=0;
    $persistenceWarning=false;
    try {
        $pdo->beginTransaction();
        $sensorId=(new SensorRepository($pdo))->openMeteoForLocation((int)$location['location_id']);
        $repository=new ReadingRepository($pdo);
        $savedObservations=$repository->saveWeatherObservations(
            $sensorId,$weather['current'],$history,$retrievedAt->format('Y-m-d H:i:s')
        );
        [$rainfall1h,$rainfall24h,$rainfall72h]=$repository->rainfallFromStoredHours($sensorId,$observedAtUtc);
        if ($rainfall1h!==null && $rainfall24h!==null && $rainfall72h!==null) {
            $saved=$repository->createFromApi([
                'location_id'=>(int)$location['location_id'],
                'rainfall_1h_mm'=>$rainfall1h,
                'rainfall_24h_mm'=>$rainfall24h,
                'rainfall_72h_mm'=>$rainfall72h,
                'source_name'=>'Open-Meteo',
                'source_url'=>'https://open-meteo.com/',
                'observed_at'=>$observedAtUtc,
            ]);
            if ($saved) {
                // Use the actual saved summary so admin corrections remain authoritative.
                $rainfall1h=(float)$saved['rainfall_1h_mm'];
                $rainfall24h=(float)$saved['rainfall_24h_mm'];
                $rainfall72h=(float)$saved['rainfall_72h_mm'];
                $riskLevel=$saved['risk_level'];
                if (!$stale) {
                    $alerts=new AlertRepository($pdo);
                    $alerts->synchronize((int)$saved['reading_id'],(int)$location['location_id'],$riskLevel);
                    $alert=$alerts->activeForReading((int)$saved['reading_id']);
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('SmartSlope weather save failed: '.$exception->getMessage());
        $persistenceWarning=true;
        $savedObservations=0;
        $rainfall1h=$rainfall24h=$rainfall72h=$riskLevel=$alert=null;
    }
    $riskExplanation=$persistenceWarning
        ? 'Provider data could not be saved, so no database-backed risk status is available.'
        : ($riskLevel===null
            ? 'Complete, non-archived 1-hour, 24-hour and 72-hour rainfall records are required.'
            : strtoupper($riskLevel).' prototype rainfall indicator. '.RiskAnalyzer::description());
    if ($stale) $riskExplanation='Weather observation is over two hours old or has a future timestamp. '.$riskExplanation;

    $savedCurrentReadings=(new ReadingRepository($pdo))->currentForLocation((int)$location['location_id']);
    foreach ($savedCurrentReadings as &$savedCurrent) {
        $savedCurrent['time']=str_replace(' ','T',$savedCurrent['observed_at']).'Z';
        $savedCurrent['fetched_at']=str_replace(' ','T',$savedCurrent['fetched_at']).'Z';
        unset($savedCurrent['observed_at']);
    }
    unset($savedCurrent);
    $savedRiskReadings=($_SESSION['role'] ?? '')==='admin'
        ? (new ReadingRepository($pdo))->forActiveLocation((int)$location['location_id']) : [];
    foreach ($savedRiskReadings as &$savedRisk) {
        $savedRisk['observed_at']=str_replace(' ','T',$savedRisk['observed_at']).'Z';
    }
    unset($savedRisk);

    respond_json(200,['data'=>[
        'location'=>[
            'location_id'=>(int)$location['location_id'],
            'location_name'=>$location['location_name'],
            'purok_zone'=>$location['purok_zone'],
        ],
        'source_name'=>'Open-Meteo',
        'source_url'=>'https://open-meteo.com/',
        'retrieved_at'=>$retrievedAt->format(DateTimeInterface::ATOM),
        'saved_observations'=>$savedObservations,
        'persistence_warning'=>$persistenceWarning,
        'stale'=>$stale,
        'current'=>$weather['current'],
        'current_readings'=>$savedCurrentReadings,
        'risk_readings'=>$savedRiskReadings,
        'alert'=>$alert ? ['risk_level'=>$alert['risk_level'],'status'=>$alert['status']] : null,
        'rainfall'=>[
            'rainfall_1h_mm'=>$rainfall1h,
            'rainfall_24h_mm'=>$rainfall24h,
            'rainfall_72h_mm'=>$rainfall72h,
            'risk_level'=>$riskLevel,
            'risk_explanation'=>$riskExplanation,
            'observed_at'=>str_replace(' ','T',$observedAtUtc).'Z',
        ],
        'hourly'=>$history,
    ]]);
} catch (Throwable $exception) {
    error_log('SmartSlope weather API request failed: ' . $exception->getMessage());
    respond_json(503, ['error' => 'weather_provider_unavailable']);
}
