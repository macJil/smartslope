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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond_json(405, ['error' => 'method_not_allowed']);
}
$locationId=filter_var($_GET['location_id'] ?? null, FILTER_VALIDATE_INT);
if (!$locationId || $locationId < 1) respond_json(400, ['error' => 'invalid_location_id']);

try {
    $locationQuery=$pdo->prepare(
        "SELECT l.location_id,l.location_name,l.purok_zone FROM locations AS l
         JOIN barangays AS b ON b.barangay_id=l.barangay_id
         WHERE l.location_id=:location_id AND l.is_active=1 AND b.is_active=1
           AND b.barangay_name='Barangay Irisan' AND b.city_name='Baguio City' LIMIT 1"
    );
    $locationQuery->execute(['location_id'=>(int)$locationId]);
    $location=$locationQuery->fetch();
    if (!$location) respond_json(404, ['error' => 'location_not_found']);

    $repository=new ReadingRepository($pdo);
    $reading=$repository->latestForActiveLocation((int)$locationId);
    $stale=true;
    $alert=null;
    if ($reading) {
        $age=time()-(new DateTimeImmutable($reading['observed_at'],new DateTimeZone('UTC')))->getTimestamp();
        $stale=$age>7200 || $age< -600;
        if (!$stale) $alert=(new AlertRepository($pdo))->activeForReading((int)$reading['reading_id']);
        $reading['observed_at']=str_replace(' ','T',$reading['observed_at']).'Z';
    }

    $current=null;
    $history=[];
    $query=$pdo->prepare(
        "SELECT w.observation_kind,w.observed_at,w.fetched_at,w.interval_seconds,
                w.temperature_2m,w.relative_humidity_2m,w.apparent_temperature,
                w.precipitation,w.rain,w.showers,w.weather_code,w.cloud_cover,
                w.pressure_msl,w.surface_pressure,w.wind_speed_10m,w.wind_direction_10m,
                w.wind_gusts_10m
         FROM weather_observations AS w JOIN sensors AS s ON s.sensor_id=w.sensor_id
         WHERE s.location_id=:location_id AND s.sensor_type='weather_api'
           AND s.provider_name='Open-Meteo' AND s.status='active'
           AND w.observed_at BETWEEN UTC_TIMESTAMP()-INTERVAL 72 HOUR
                                 AND UTC_TIMESTAMP()+INTERVAL 10 MINUTE
         ORDER BY w.observed_at DESC LIMIT 220"
    );
    $query->execute(['location_id'=>(int)$locationId]);
    foreach ($query->fetchAll() as $row) {
        $kind=$row['observation_kind'];
        $row['time']=str_replace(' ','T',$row['observed_at']).'Z';
        $row['fetched_at']=str_replace(' ','T',$row['fetched_at']).'Z';
        $row['interval']=$row['interval_seconds'];
        unset($row['observed_at'],$row['observation_kind'],$row['interval_seconds']);
        if ($kind==='current' && $current===null) $current=$row;
        if ($kind==='hourly' && count($history)<72) $history[]=$row;
    }
    $history=array_reverse($history);
    respond_json(200,['data'=>[
        'location'=>['location_id'=>(int)$locationId,'location_name'=>$location['location_name'],
                     'purok_zone'=>$location['purok_zone']],
        'rainfall'=>$reading ? $reading+['risk_explanation'=>RiskAnalyzer::description()] : null,
        'current'=>$current,
        'hourly'=>$history,
        'retrieved_at'=>$current['fetched_at'] ?? null,
        'stale'=>$stale,
        'alert'=>$alert ? ['risk_level'=>$alert['risk_level'],'status'=>$alert['status']] : null,
    ]]);
} catch (Throwable $exception) {
    error_log('SmartSlope stored dashboard failed: '.$exception->getMessage());
    respond_json(503, ['error'=>'database_unavailable']);
}
