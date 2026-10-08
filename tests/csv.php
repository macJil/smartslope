<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

set_error_handler(static function(int $severity,string $message): never { throw new ErrorException($message,0,$severity); });
$checks=0;
function check_csv(bool $ok,string $message): void { global $checks; if(!$ok)throw new RuntimeException($message); $checks++; }
$rows = [['id'=>7, 'name'=>"=Special, \"name\"\nñ", 'purok'=>"'Purok", 'landmark'=>'+Landmark', 'lat'=>'16.42100', 'lng'=>'120.55950', 'active'=>1]];
$csv = export_locations_csv($rows);
$parsed = parse_locations_csv($csv);
check_csv($parsed[0]['name'] === $rows[0]['name'] && $parsed[0]['purok'] === $rows[0]['purok'] && $parsed[0]['landmark'] === $rows[0]['landmark'], 'CSV text round trip.');
check_csv(str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, "\r\n"), 'UTF-8 BOM and CRLF.');
check_csv(parse_locations_csv(str_replace('Special', 'Changed', $csv))[0]['name'] !== $rows[0]['name'], 'Editable CSV accepted.');
check_csv(count(parse_locations_csv($csv . "\r\n")) === 1, 'Blank trailing row ignored.');
foreach (["name,lat,lng\nTest,16.42,120.56\n", export_reports_csv([]), $csv . "one,two\r\n"] as $invalid) {
    try { parse_locations_csv($invalid); throw new RuntimeException('Wrong format accepted.'); }
    catch (InvalidArgumentException $error) { check_csv(true, 'Wrong columns rejected.'); }
}
check_csv(csv_date('2026-01-01 00:00:00') === '2026-01-01 08:00:00', 'PHT export time.');
check_csv(str_contains($csv, '=Special'), 'Formula-like text exported literally.');
check_csv(!str_contains(export_readings_csv([]), '<br'), 'No HTML in export.');
echo "$checks CSV checks passed.\n";
