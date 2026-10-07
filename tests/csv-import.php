<?php
require_once __DIR__ . '/bootstrap.php';
$pdo = require_test_database();
$latitude = 16.42123;
$longitude = 120.55967;
$existing = $pdo->prepare('SELECT id FROM locations WHERE lat=? AND lng=?');
$existing->execute([$latitude, $longitude]);
if ($existing->fetch()) throw new RuntimeException('CSV test point already exists; use a fresh test database.');
$locationId = null;
try {
    $row = ['id'=>99999, 'name'=>'CSV test', 'purok'=>'', 'landmark'=>'Test, "quoted" address', 'lat'=>$latitude, 'lng'=>$longitude, 'active'=>1];
    $key = str_repeat('b', 64);
    $rows = parse_locations_csv(export_locations_csv([$row], $key), $key);
    $result = import_locations_csv($rows);
    test_check($result === ['added'=>1, 'updated'=>0, 'unchanged'=>0], 'Valid CSV adds one location.');
    $existing->execute([$latitude, $longitude]);
    $locationId = (int)$existing->fetchColumn();
    test_check($locationId !== 99999, 'CSV IDs do not overwrite another database ID.');
    $result = import_locations_csv($rows);
    test_check($result['unchanged'] === 1, 'Repeated import does not duplicate coordinates.');
    $rows[0]['landmark'] = 'Updated test address';
    $result = import_locations_csv($rows);
    test_check($result['updated'] === 1, 'An existing point is updated.');
    test_check(get_location($locationId)['landmark'] === 'Updated test address', 'Saved address matches import.');

    // The second insert fails; the preceding update must also roll back.
    $rows[0]['landmark'] = 'Must roll back';
    $invalid = $rows[0];
    $invalid['lat'] = 16.42124;
    $invalid['name'] = null;
    try {
        import_locations_csv([$rows[0], $invalid]);
        test_check(false, 'Invalid database row must fail.');
    } catch (PDOException $error) {
        test_check(get_location($locationId)['landmark'] === 'Updated test address', 'Import failure rolls back earlier changes.');
        test_check(!$pdo->inTransaction(), 'Failed import closes its transaction.');
    }
} finally {
    if ($locationId) {
        $delete = $pdo->prepare('DELETE FROM locations WHERE id=?');
        $delete->execute([$locationId]);
    }
}
echo "$testChecks CSV database checks passed.\n";
