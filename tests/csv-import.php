<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/risk.php';
require_once __DIR__ . '/../includes/geography.php';
require_once __DIR__ . '/../includes/csv.php';

if (!str_ends_with($config['db_name'], '_test')) {
    throw new RuntimeException('Use a disposable *_test database.');
}
$pdo = db();
$checks = 0;
function check_import(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
$location = $pdo->query('SELECT * FROM locations ORDER BY id LIMIT 1')->fetch();
$original = parse_locations_csv(export_locations_csv([$location]));
$row = $original[0];
try {
    check_import(import_locations_csv([$row])['unchanged'] === 1, 'Unchanged export imports without a new location.');
    $first = $row;
    $first['name'] = "First ' location";
    $second = $row;
    $second['name'] = 'Last duplicate';
    import_locations_csv([$first, $second]);
    $find = $pdo->prepare('SELECT name FROM locations WHERE id = ?');
    $find->execute([$location['id']]);
    check_import($find->fetchColumn() === 'Last duplicate', 'Repeated coordinates save in file order on the same ID.');
    $first['name'] = '=Literal CSV text';
    $bad = $row;
    $bad['lat'] = 'not a coordinate';
    try {
        import_locations_csv([$first, $bad]);
        throw new RuntimeException('Invalid coordinate accepted.');
    } catch (InvalidArgumentException $error) {
        check_import(str_contains($error->getMessage(), 'Earlier rows'), 'Partial-save message is explicit.');
    }
    $find->execute([$location['id']]);
    check_import($find->fetchColumn() === '=Literal CSV text', 'Earlier rows remain saved without rollback.');
    check_import(import_locations_csv($original)['updated'] === 1, 'Original export restores location details.');
} finally {
    import_locations_csv($original);
}
echo "$checks CSV import checks passed.\n";
