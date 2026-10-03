<?php
require_once __DIR__ . '/bootstrap.php';
$key=str_repeat('a',64);
$locations=[['id'=>7,'name'=>'=Special, "name"','purok'=>"'Purok",'landmark'=>'+Landmark','lat'=>'16.42100','lng'=>'120.55950','active'=>1]];
$csv=export_locations_csv($locations,$key);
$parsed=parse_locations_csv($csv,$key);
test_check($parsed[0]['name']===$locations[0]['name'],'CSV preserves quoted/formula-looking location text.');
test_check(str_starts_with($csv,"\xEF\xBB\xBF"),'Location export includes UTF-8 BOM.');
test_check(str_contains(csv_cell('=HYPERLINK("bad")'),"'=HYPERLINK"),'CSV formula content is neutralized.');
test_check(csv_date('2026-01-01 00:00:00')==='2026-01-01 08:00:00','CSV date is rendered in Philippine time.');
try { parse_locations_csv(str_replace('Special','Changed',$csv),$key); test_check(false,'Tampered location CSV should fail.'); }
catch (InvalidArgumentException) { test_check(true,'Tampered location CSV rejected.'); }
test_check(!str_contains(export_readings_csv([]),'<br'),'Reading export contains CSV, not HTML.');
test_check(str_contains(export_reports_csv([]),'Contact phone'),'Report export documents contact columns.');
echo "$testChecks CSV checks passed.\n";
