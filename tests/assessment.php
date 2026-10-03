<?php
require_once __DIR__ . '/bootstrap.php';
$now = time();
$reading = ['rainfall_1h'=>1,'rainfall_24h'=>3,'rainfall_72h'=>8,'risk_level'=>'low',
    'observed_at'=>gmdate('Y-m-d H:i:s',$now),'created_at'=>gmdate('Y-m-d H:i:s',$now),
    'rule_version'=>RiskAnalyzer::VERSION,'provider_payload'=>'{}'];
$current = reading_assessment($reading, [], $now);
test_check($current['data_status']==='current' && $current['current_category']==='low', 'Fresh valid reading is current.');
test_check($current['calculated_category']==='low', 'Calculated category is present.');
test_check(reading_assessment(null, [], $now)['data_status']==='unavailable', 'No reading is unavailable.');
$bad=$reading; $bad['rainfall_1h']=null;
test_check(reading_assessment($bad, [], $now)['data_status']==='incomplete', 'Missing rainfall is incomplete.');
$bad=$reading; $bad['rainfall_1h']=4; $bad['rainfall_24h']=2;
test_check(reading_assessment($bad, [], $now)['data_status']==='incomplete', 'Inconsistent totals are incomplete.');
$bad=$reading; $bad['observed_at']=gmdate('Y-m-d H:i:s',$now+3600);
test_check(reading_assessment($bad, [], $now)['data_status']==='incomplete', 'Future reading is incomplete.');
$bad=$reading; $bad['observed_at']=gmdate('Y-m-d H:i:s',$now-20000);
test_check(reading_assessment($bad, [], $now)['data_status']==='outdated', 'Old reading is historical, not current.');
$bad=$reading; $bad['rule_version']='old-rule';
test_check(reading_assessment($bad, [], $now)['data_status']==='incomplete', 'Different rule version cannot be current.');
$bad=$reading; $bad['risk_level']='high';
test_check(reading_assessment($bad, [], $now)['adjusted']===true, 'Stored override is distinguished from calculation.');
echo "$testChecks assessment checks passed.\n";
