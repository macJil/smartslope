<?php
declare(strict_types=1);
// Portable in-memory contract checks; MySQL-only ingestion/upsert requires MySQL acceptance.
foreach (['LocationRepository','ReadingRepository','ReportRepository','UserRepository','RiskAnalyzer'] as $class) {
    require_once __DIR__.'/../app/'.$class.'.php';
}
function check(bool $pass,string $label): void {
    if (!$pass) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec("PRAGMA foreign_keys=ON;
CREATE TABLE barangays(barangay_id INTEGER PRIMARY KEY,barangay_name TEXT,city_name TEXT,is_active INTEGER);
INSERT INTO barangays VALUES(1,'Barangay Irisan','Baguio City',1);
CREATE TABLE users(user_id INTEGER PRIMARY KEY,full_name TEXT,username TEXT UNIQUE,email TEXT UNIQUE,contact_number TEXT UNIQUE,password_hash TEXT,role TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE locations(location_id INTEGER PRIMARY KEY,barangay_id INTEGER REFERENCES barangays,location_name TEXT,purok_zone TEXT,landmark TEXT,latitude REAL,longitude REAL,susceptibility_class TEXT,hazard_source_name TEXT,hazard_source_url TEXT,hazard_source_date TEXT,is_active INTEGER DEFAULT 1,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT);
CREATE TABLE readings(reading_id INTEGER PRIMARY KEY,location_id INTEGER REFERENCES locations,rainfall_1h_mm REAL,rainfall_24h_mm REAL,rainfall_72h_mm REAL,risk_level TEXT,source_name TEXT,source_url TEXT,observed_at TEXT,recorded_by_user_id INTEGER,is_archived INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE reports(report_id INTEGER PRIMARY KEY,location_id INTEGER REFERENCES locations,reported_by_user_id INTEGER,house_landmark TEXT,message TEXT,status TEXT DEFAULT 'pending',reviewed_by_user_id INTEGER,reviewed_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);");
$users=new UserRepository($pdo);
$users->create('Resident','resident','resident@example.test','+639171234567','example-password');
$user=$users->authenticate('resident','example-password');
check($user!==null && $user['email']==='resident@example.test' && $user['contact_number']==='+639171234567' && $user['role']==='user','email and phone registration hashes and assigns resident');
check($users->authenticate('resident','wrong')===null,'bad password rejected');
$locations=new LocationRepository($pdo);
$location=['location_name'=>'TEST','purok_zone'=>null,'landmark'=>null,'latitude'=>16.4,'longitude'=>120.5,
    'susceptibility_class'=>'unknown','hazard_source_name'=>null,'hazard_source_url'=>null,'hazard_source_date'=>null];
$locations->create($location);
$id=(int)$pdo->lastInsertId();
check(count($locations->activeForStudyArea())===1,'location created');
$reports=new ReportRepository($pdo);
$reports->create($id,(int)$user['user_id'],null,'TEST ONLY');
check(count($reports->adminQueue('pending'))===1,'resident report review queue');
check($reports->adminQueue('pending')[0]['reporter_email']==='resident@example.test','admin sees reporter email');
check($reports->pendingCountsByLocation()[$id]===1,'admin map counts pending reports');
$locations->setActive($id,false);
check($locations->activeForStudyArea()===[] && $reports->activeLocations()===[],'archived location excluded');
foreach ([[0,0,0,'low'],[10,0,0,'normal'],[0,50,0,'medium'],[0,0,150,'high']] as [$a,$b,$c,$level]) {
    check(RiskAnalyzer::analyze((float)$a,(float)$b,(float)$c)===$level,'risk '.$level);
}
try { RiskAnalyzer::analyze(null,null,null); throw new RuntimeException('Missing rain was treated as low'); }
catch (InvalidArgumentException $exception) { check(true,'incomplete rainfall rejected'); }
