<?php
require_once __DIR__ . '/bootstrap.php';
test_check(report_occurrence('')===null,'Occurrence time is optional.');
$parsed=report_occurrence('2026-01-01T08:00',strtotime('2026-01-02T00:00:00Z'));
test_check($parsed==='2026-01-01 00:00:00','Philippine occurrence time is converted to UTC.');
foreach (['2026-02-30T08:00','not-a-date','2026-01-03T08:00'] as $input) {
    try { report_occurrence($input,strtotime('2026-01-02T00:00:00Z')); test_check(false,'Invalid/future occurrence time should fail.'); }
    catch (InvalidArgumentException) { test_check(true,'Invalid/future occurrence time rejected.'); }
}
$unavailable=['data_status'=>'unavailable','calculated_category'=>null,'reasons'=>['No data']];
$notices=awareness_notices($unavailable,['classification'=>'UNVERIFIED','category'=>'unknown']);
test_check($notices[0]['tone']==='secondary','Unavailable reading remains neutral.');
$medium=['data_status'=>'current','calculated_category'=>'medium','reasons'=>['Prototype threshold']];
$notices=awareness_notices($medium,['classification'=>'UNVERIFIED','category'=>'unknown']);
test_check($notices[0]['tone']==='warning','Medium category has warning tone.');
echo "$testChecks report checks passed.\n";
