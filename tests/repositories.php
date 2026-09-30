<?php
declare(strict_types=1);
// Fast repository contract tests. SQLite checks CRUD behavior; MySQL acceptance is separate.
foreach (['LocationRepository', 'ReadingRepository', 'ReportRepository', 'UserRepository', 'RiskAnalyzer'] as $class) {
    require_once __DIR__ . '/../app/' . $class . '.php';
}
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$pdoClass = class_exists('Pdo\\Sqlite') ? 'Pdo\\Sqlite' : PDO::class;
$pdo = new $pdoClass('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
if (method_exists($pdo, 'createFunction')) {
    $pdo->createFunction('UTC_TIMESTAMP', fn() => gmdate('Y-m-d H:i:s'));
} else {
    $pdo->sqliteCreateFunction('UTC_TIMESTAMP', fn() => gmdate('Y-m-d H:i:s'));
}
$pdo->exec("PRAGMA foreign_keys=ON;
CREATE TABLE barangays (barangay_id INTEGER PRIMARY KEY, barangay_name TEXT, city_name TEXT, is_active INTEGER);
INSERT INTO barangays VALUES (1, 'Barangay Irisan', 'Baguio City', 1);
CREATE TABLE users (user_id INTEGER PRIMARY KEY, full_name TEXT, username TEXT UNIQUE, email TEXT UNIQUE, password_hash TEXT, role TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE locations (location_id INTEGER PRIMARY KEY, barangay_id INTEGER REFERENCES barangays, location_name TEXT, purok_zone TEXT, landmark TEXT, latitude REAL, longitude REAL, susceptibility_class TEXT, hazard_source_name TEXT, hazard_source_url TEXT, hazard_source_date TEXT, is_active INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT);
CREATE TABLE readings (reading_id INTEGER PRIMARY KEY, location_id INTEGER REFERENCES locations, rainfall_1h_mm REAL, rainfall_24h_mm REAL, rainfall_72h_mm REAL, risk_level TEXT, source_name TEXT, source_url TEXT, observed_at TEXT, recorded_by_user_id INTEGER REFERENCES users, is_archived INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(location_id, observed_at, source_name));
CREATE TABLE reports (report_id INTEGER PRIMARY KEY, location_id INTEGER REFERENCES locations, reported_by_user_id INTEGER REFERENCES users, house_landmark TEXT, message TEXT, status TEXT DEFAULT 'pending', reviewed_by_user_id INTEGER REFERENCES users, reviewed_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);");
$users = new UserRepository($pdo);
$users->create('Test Resident', 'testresident', 'test@example.invalid', 'test-password-only');
$user = $users->authenticate('testresident', 'test-password-only');
check($user !== null && $user['role'] === 'user', 'registration hashes passwords and assigns resident role');
check($users->authenticate('testresident', 'wrong-password') === null, 'wrong password rejected');
$locations = new LocationRepository($pdo);
$location = ['location_name'=>'TEST ONLY','purok_zone'=>'Test','landmark'=>null,'latitude'=>16.4,'longitude'=>120.5,'susceptibility_class'=>'unknown','hazard_source_name'=>null,'hazard_source_url'=>null,'hazard_source_date'=>null];
$locations->create($location);
$locationId = (int) $pdo->lastInsertId();
$location['location_name'] = 'Updated test';
check($locations->update($locationId, $location) && $locations->find($locationId)['location_name'] === 'Updated test', 'location create/read/update');
$readings = new ReadingRepository($pdo);
$reading = ['location_id'=>$locationId,'rainfall_1h_mm'=>1.0,'rainfall_24h_mm'=>2.0,'rainfall_72h_mm'=>3.0,'source_name'=>'Synthetic test','source_url'=>null,'observed_at'=>'2026-09-30 01:00:00'];
$readings->create($reading, (int)$user['user_id']);
$readingId = (int)$pdo->lastInsertId();
check($readings->latestForActiveLocation($locationId)['risk_level'] === 'low', 'reading create/read calculates risk');
$reading['rainfall_24h_mm'] = 101.0;
check($readings->update($readingId, $reading) && $readings->latestForActiveLocation($locationId)['risk_level'] === 'high', 'reading update includes source parameters and recalculates risk');
$readings->setArchived($readingId, true);
check($readings->latestForActiveLocation($locationId) === null && count($readings->adminList()) === 1, 'archive hides reading and preserves history');
$readings->setArchived($readingId, false);
check($readings->latestForActiveLocation($locationId) !== null, 'restore makes reading available');
$reports = new ReportRepository($pdo);
$reports->create($locationId, (int)$user['user_id'], null, 'Synthetic ground report');
$reportId = (int)$pdo->lastInsertId();
check(count($reports->adminQueue('pending')) === 1, 'resident report reaches pending queue');
check($reports->updateStatus($reportId, 'reviewed', (int)$user['user_id']) && count($reports->adminQueue('reviewed')) === 1, 'report review updates queue');
$locations->setActive($locationId, false);
check($locations->activeForStudyArea() === [] && $readings->latestForActiveLocation($locationId) === null, 'archiving location excludes public readings');
check($locations->find($locationId) !== null && count($readings->adminList()) === 1, 'location archival retains related records');
$locations->setActive($locationId, true);
check(count($locations->activeForStudyArea()) === 1, 'location restore');
foreach ([[0,0,0,'low'],[10,0,0,'normal'],[0,50,0,'medium'],[0,0,150,'high']] as [$a,$b,$c,$level]) {
    check(RiskAnalyzer::analyze((float)$a,(float)$b,(float)$c) === $level, 'rainfall boundary '.$level);
}
