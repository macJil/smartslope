<?php
require_once __DIR__ . '/bootstrap.php';
$end=intdiv(time(),3600)*3600;
$times=[]; $rain=[]; $probability=[]; $soil=[];
for ($stamp=$end-72*3600; $stamp<=$end+24*3600; $stamp+=3600) {
    $times[]=gmdate('Y-m-d\TH:i',$stamp);
    $rain[]=1.0; $probability[]=20; $soil[]=0.25;
}
$indicators=calculate_weather_indicators([
    'time'=>$times,'precipitation'=>$rain,'precipitation_probability'=>$probability,
    'soil_moisture_9_to_27cm'=>$soil,'soil_moisture_27_to_81cm'=>$soil
],gmdate('Y-m-d H:i:s',$end));
test_check($indicators['rainfall_1h']===1.0,'One-hour rainfall uses last completed interval.');
test_check($indicators['rainfall_24h']===24.0,'Daily rainfall sums 24 completed hourly values.');
test_check($indicators['rainfall_72h']===72.0,'Three-day rainfall sums 72 completed hourly values.');
test_check($indicators['rainfall_forecast_24h']===24.0,'Forecast totals use the following 24 hours.');
test_check($indicators['precipitation_probability_24h']===20,'Forecast probability is the maximum hourly value.');
test_check($indicators['soil_moisture_9_27cm']===0.25,'Optional soil moisture is retained in range.');
$missing=['time'=>$times,'precipitation'=>$rain];
$missing['precipitation'][array_search(gmdate('Y-m-d\TH:i',$end-48*3600),$times,true)]=null;
$partial=calculate_weather_indicators($missing,gmdate('Y-m-d H:i:s',$end));
test_check($partial['rainfall_72h']===null,'Missing hourly input makes its window unavailable.');
$duplicate=$times; $duplicate[1]=$duplicate[0];
try { calculate_weather_indicators(['time'=>$duplicate,'precipitation'=>$rain],gmdate('Y-m-d H:i:s',$end)); test_check(false,'Duplicate time should fail.'); }
catch (RuntimeException) { test_check(true,'Duplicate time rejected.'); }
$badRain=$rain; $badRain[array_search(gmdate('Y-m-d\TH:i',$end),$times,true)]='nan';
$bad=calculate_weather_indicators(['time'=>$times,'precipitation'=>$badRain],gmdate('Y-m-d H:i:s',$end));
test_check($bad['rainfall_1h']===null,'Invalid numeric input makes the affected window unavailable.');
echo "$testChecks weather checks passed.\n";
