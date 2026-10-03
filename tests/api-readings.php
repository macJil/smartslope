<?php
require_once __DIR__ . '/bootstrap.php';
$source=file_get_contents(__DIR__ . '/../api/readings.php');
$checks=[
    ["if (!is_logged_in()) reading_json(401",'API requires a signed-in user.'],
    ["if (!in_array(\$method, ['GET', 'POST'], true)",'API explicitly limits methods.'],
    ["\$method === 'POST' && !valid_csrf()",'API POST requires CSRF.'],
    ["if (\$method === 'POST') refresh_location(\$locationId)",'Only POST triggers provider refresh.'],
    ['unset($latest[\'provider_payload\'], $latest[\'adjustment_log\'])','Sensitive/internal reading metadata is removed from response.'],
    ["reading_json(503",'Provider/database exceptions return service unavailable.'],
];
foreach ($checks as [$needle,$message]) test_check(str_contains($source,$needle),$message);
test_check(str_contains($source,"reading_json(422"),'Invalid location ID has a client error.');
test_check(str_contains($source,"reading_json(404"),'Missing location has a not-found response.');
echo "$testChecks API contract checks passed.\n";
