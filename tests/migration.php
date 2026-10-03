<?php
require_once __DIR__ . '/bootstrap.php';
$pdo=require_test_database();
migrate_awareness_schema();
$tables=array_column($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM),0);
foreach (['users','locations','events','readings','reports'] as $table) test_check(in_array($table,$tables,true),"Required normalized table exists: $table");
$columns=array_column($pdo->query('SHOW COLUMNS FROM events')->fetchAll(),'Field');
test_check(in_array('type',$columns,true),'Parent retains event type.');
test_check(!in_array('risk_level',$columns,true) && !in_array('message',$columns,true),'Parent excludes subtype columns.');
foreach (['readings','reports'] as $table) {
    $detailColumns=array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(),'Field');
    test_check(in_array('event_id',$detailColumns,true),"$table is keyed by event_id.");
}
$invalid=(int)$pdo->query("SELECT COUNT(*) FROM events e LEFT JOIN readings r ON r.event_id=e.id WHERE e.type='reading' AND r.event_id IS NULL")->fetchColumn()
    +(int)$pdo->query("SELECT COUNT(*) FROM events e LEFT JOIN reports r ON r.event_id=e.id WHERE e.type='report' AND r.event_id IS NULL")->fetchColumn();
test_check($invalid===0,'Every event has its matching detail row.');
echo "$testChecks normalized migration checks passed.\n";
