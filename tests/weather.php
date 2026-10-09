<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/risk.php';
require_once __DIR__ . '/../includes/http.php';
require_once __DIR__ . '/../includes/weather.php';

$end = utc_timestamp('2026-10-02T06:00');
$hourly = ['time'=>[], 'precipitation'=>[], 'precipitation_probability'=>[],
    'soil_moisture_9_to_27cm'=>[], 'soil_moisture_27_to_81cm'=>[]];
for ($i=-72; $i<=24; $i++) {
    $hourly['time'][] = gmdate('Y-m-d\TH:i', $end + $i * 3600);
    $hourly['precipitation'][] = 1;
    $hourly['precipitation_probability'][] = 60;
    $hourly['soil_moisture_9_to_27cm'][] = 0.3;
    $hourly['soil_moisture_27_to_81cm'][] = 0.4;
}
$check = static function ($actual, $expected) { if ($actual !== $expected) throw new RuntimeException(var_export([$actual,$expected], true)); };
$rejects = static function (callable $fn) { try { $fn(); } catch (RuntimeException $e) { return; } throw new RuntimeException('Invalid payload accepted.'); };
$run = static fn($h) => calculate_weather_indicators($h, '2026-10-02T06:15');
$result = $run($hourly);
$check($result['rainfall_1h'], 1.0);
$check($result['rainfall_24h'], 24.0);
$check($result['rainfall_72h'], 72.0);
$check($result['rainfall_forecast_24h'], 24.0);
$check($result['precipitation_probability_24h'], 60);
$bad = $hourly; unset($bad['time'][50]);
$check($run($bad)['rainfall_24h'], null);
$bad = $hourly; $bad['time'][50] = $bad['time'][51];
$rejects(fn() => $run($bad));
foreach ([null, -1, INF, 'invalid'] as $value) {
    $bad = $hourly; $bad['precipitation'][72] = $value;
    $check($run($bad)['rainfall_1h'], null);
}
$bad = $hourly; array_pop($bad['time']);
$check($run($bad)['rainfall_forecast_24h'], null);
$check($run($bad)['precipitation_probability_24h'], null);
$bad = $hourly; $bad['time'][72] = '2026-10-02T06:15';
$rejects(fn() => $run($bad));
$weather = ['current'=>['time'=>'2026-10-02T06:00','temperature_2m'=>20,'relative_humidity_2m'=>80,'wind_speed_10m'=>5,'precipitation'=>1,'weather_code'=>3],
    'hourly'=>$hourly, 'utc_offset_seconds'=>0,
    'current_units'=>['temperature_2m'=>'°C','relative_humidity_2m'=>'%','wind_speed_10m'=>'km/h','precipitation'=>'mm','weather_code'=>'wmo code'],
    'hourly_units'=>['precipitation'=>'mm','precipitation_probability'=>'%','soil_moisture_9_to_27cm'=>'m³/m³','soil_moisture_27_to_81cm'=>'m³/m³']];
validate_weather_payload($weather, $end);
$rejects(fn() => validate_weather_payload($weather, $end - 301));
$rejects(fn() => validate_weather_payload($weather, $end + 10801));
$bad = $weather; $bad['hourly_units']['precipitation'] = 'inch';
$rejects(fn() => validate_weather_payload($bad, $end));
$bad = $weather; $bad['current']['relative_humidity_2m'] = 101;
$rejects(fn() => validate_weather_payload($bad, $end));
echo "Weather windows, gaps, duplicate hours, invalid values, units and freshness passed.\n";
