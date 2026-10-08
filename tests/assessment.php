<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$config['freshness_seconds'] = 10800;
$config['future_tolerance_seconds'] = 300;
$now = utc_timestamp('2026-10-02 06:00:00');
$row = ['observed_at'=>'2026-10-02 06:00:00', 'created_at'=>'2026-10-02 06:01:00',
    'rainfall_1h'=>0,'rainfall_24h'=>0,'rainfall_72h'=>0,'risk_level'=>'low','stale'=>1];
$check = static function ($actual, $expected) { if ($actual !== $expected) throw new RuntimeException(var_export([$actual,$expected], true)); };
$check(reading_assessment(null, [], $now)['data_status'], 'unavailable');
$check(reading_assessment($row, [], $now)['data_status'], 'current');
$check(reading_assessment($row, [], $now + 10800)['data_status'], 'current');
$check(reading_assessment($row, [], $now + 10801)['data_status'], 'outdated');
$check(reading_assessment($row, [], $now + 10801)['current_category'], null);
$check(reading_assessment($row, [], $now - 301)['data_status'], 'incomplete');
$check(utc_timestamp('2026-02-30 10:00:00'), null);
$row['risk_level'] = 'high';
$check(reading_assessment($row, [], $now)['adjusted'], true);
$check(reading_assessment($row, [], $now)['calculated_category'], 'low');
$row['rainfall_24h'] = null;
$check(reading_assessment($row, [], $now)['category'], null);
$row['risk_level'] = null;
$check(reading_assessment($row, [], $now)['data_status'], 'incomplete');
echo "11 assessment checks passed.\n";
