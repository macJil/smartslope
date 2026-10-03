<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
set_error_handler(static function(int $severity,string $message): never { throw new ErrorException($message,0,$severity); });
$checks=0;
function check_csv(bool $ok,string $message): void { global $checks; if(!$ok)throw new RuntimeException($message); $checks++; }
$key=str_repeat('a',64);
$rows=[['id'=>7,'name'=>"=Special, \"name\"\nñ",'purok'=>"'Purok",'landmark'=>'+Landmark','lat'=>'16.42100','lng'=>'120.55950','active'=>1]];
$csv=export_locations_csv($rows,$key);
$parsed=parse_locations_csv($csv,$key);
check_csv($parsed[0]['name']===$rows[0]['name'] && $parsed[0]['purok']===$rows[0]['purok'] && $parsed[0]['landmark']===$rows[0]['landmark'],'Unicode, quotes, newlines, formula protection round trip.');
check_csv(str_starts_with($csv,"\xEF\xBB\xBF") && str_contains($csv,"\r\n"),'Excel UTF-8 BOM and standard line endings.');
foreach ([$csv.'extra',str_replace('Special','Changed',$csv),"name,lat,lng\nTest,16.42,120.56\n",substr($csv,0,-40),export_locations_csv($rows,str_repeat('b',64)),export_reports_csv([]),"\0".$csv] as $invalid) {
    try { parse_locations_csv($invalid,$key); throw new RuntimeException('Invalid CSV accepted.'); }
    catch(InvalidArgumentException $e) { check_csv(str_contains($e->getMessage(),'Invalid/not applicable'),'Clear invalid-file message.'); }
}
check_csv(csv_date('2026-01-01 00:00:00')==='2026-01-01 08:00:00','PHT export time.');
check_csv(csv_cell('=HYPERLINK("bad")')[0]==="'",'Formula protection.');
check_csv(!str_contains(export_readings_csv([]),'<br'),'No HTML in export.');
echo "$checks CSV checks passed.\n";
