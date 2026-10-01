<?php
declare(strict_types=1);
/** Run manually against a disposable or backed-up MySQL DB, never production. */
require_once __DIR__.'/../app/Database.php';
foreach (['SensorRepository','ReadingRepository','AlertRepository','RiskAnalyzer','LocationRepository','StudyArea'] as $class) {
    require_once __DIR__.'/../app/'.$class.'.php';
}
function verify(bool $condition,string $label): void {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS $label\n";
}
$pdo=Database::connect(require __DIR__.'/../configs/config.php');
$pdo->beginTransaction();
try {
    $barangay=$pdo->query("SELECT barangay_id FROM barangays WHERE barangay_name='Barangay Irisan'
        AND city_name='Baguio City' LIMIT 1")->fetchColumn();
    verify($barangay!==false,'study barangay seeded');
    $mapLocations = new LocationRepository($pdo);
    $point = $mapLocations->forMapPoint(16.421,120.5595);
    $same = $mapLocations->forMapPoint(16.421,120.5595);
    verify($point['location_id']===$same['location_id'],'same clicked point reuses its ID');
    verify((float)$point['latitude']===16.421 && (float)$point['longitude']===120.5595,'clicked coordinates retained');
    $outside=false;
    try { $mapLocations->forMapPoint(0,0); } catch (InvalidArgumentException $exception) { $outside=true; }
    verify($outside,'outside Irisan rejected');
    $location=$pdo->prepare('INSERT INTO locations(barangay_id,location_name,latitude,longitude)
        VALUES (:barangay_id,:name,16.4,120.5)');
    $location->execute(['barangay_id'=>$barangay,'name'=>'TEST ONLY '.bin2hex(random_bytes(5))]);
    $locationId=(int)$pdo->lastInsertId();
    $sensor=new SensorRepository($pdo);
    $sensorId=$sensor->openMeteoForLocation($locationId);
    verify($sensorId===$sensor->openMeteoForLocation($locationId),'source reuse');
    $repo=new ReadingRepository($pdo);
    $now=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->setTime((int)gmdate('H'),0);
    $base=$now->modify('-71 hours');
    $hourly=[];
    for ($i=0;$i<72;$i++) $hourly[]=['time'=>$base->modify("+$i hours")->format(DateTimeInterface::ATOM),
        'precipitation'=>3.0,'temperature_2m'=>20.0];
    $current=['time'=>$now->format(DateTimeInterface::ATOM),'interval'=>900,'temperature_2m'=>20.0];
    $fetched=gmdate('Y-m-d H:i:s');
    verify($repo->saveWeatherObservations($sensorId,$current,$hourly,$fetched)===73,'current and 72 hourly observations');
    [$one,$day,$three]=$repo->rainfallFromStoredHours($sensorId,$now->format('Y-m-d H:i:s'));
    verify($one===3.0 && $day===72.0 && $three===216.0,'contiguous stored rainfall window');
    $row=['location_id'=>$locationId,'rainfall_1h_mm'=>$one,'rainfall_24h_mm'=>$day,
        'rainfall_72h_mm'=>$three,'source_name'=>'Open-Meteo','source_url'=>'https://open-meteo.com/',
        'observed_at'=>$now->format('Y-m-d H:i:s')];
    $saved=$repo->createFromApi($row);
    verify($saved!==null && $saved['risk_level']==='high','saved risk from stored data');
    $alerts=new AlertRepository($pdo);
    $alerts->synchronize((int)$saved['reading_id'],$locationId,$saved['risk_level']);
    verify($alerts->activeForReading((int)$saved['reading_id'])!==null,'linked alert');
    $repo->saveWeatherObservations($sensorId,$current,$hourly,$fetched);
    $repo->createFromApi($row);
    $count=$pdo->query('SELECT COUNT(*) FROM weather_observations WHERE sensor_id='.$sensorId)->fetchColumn();
    verify((int)$count===73,'repeated fetch deduplicated');
    $repo->appendFetch($sensorId,$current,$fetched,[$one,$day,$three],$saved['risk_level'],false);
    $repo->appendFetch($sensorId,$current,$fetched,[$one,$day,$three],$saved['risk_level'],false);
    $logs=$repo->currentForLocation($locationId);
    verify(count($logs)===2,'two successful fetches with identical provider time create two log entries');
    verify($logs[0]['risk_level']==='high','risk snapshot saved in the list');
    verify((int)$logs[0]['observation_id']>(int)$logs[1]['observation_id'],'latest log displayed first');
    $logId=(int)$logs[0]['observation_id'];
    verify(!$repo->hasCurrent((int)$point['location_id'],$logId),'log edits scoped to selected location');
    verify($repo->updateCurrent($locationId,$logId,['temperature'=>21,'humidity'=>70,'precipitation'=>1,
        'rain'=>1,'showers'=>0,'wind'=>5,'gusts'=>8]),'admin log update');
    verify((float)$repo->currentForLocation($locationId)[0]['temperature_2m']===21.0,'updated log visible');
    verify($repo->deleteCurrent($locationId,$logId),'admin log removed');
    verify(count($repo->currentForLocation($locationId))===1,'removed log hidden from resident/admin list');
    $pdo->rollBack();
    echo "PASS transaction rolled back; synthetic records were not kept\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
