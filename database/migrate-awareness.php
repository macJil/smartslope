<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/bootstrap.php';
$columns = ['rule_version' => 'VARCHAR(40) NULL', 'rainfall_window_end' => 'DATETIME NULL', 'provider_payload' => 'LONGTEXT NULL', 'adjustment_log' => 'LONGTEXT NULL', 'report_type' => 'VARCHAR(30) NULL', 'occurred_at' => 'DATETIME NULL', 'reviewed_by' => 'INT UNSIGNED NULL', 'reviewed_at' => 'DATETIME NULL'];
$pdo=db();
$existing=array_column($pdo->query('SHOW COLUMNS FROM events')->fetchAll(),'Field');
foreach ($columns as $name=>$type) {
    if (!in_array($name,$existing,true)) { $pdo->exec("ALTER TABLE events ADD COLUMN `$name` $type"); echo "Added $name\n"; }
}
$indexes=array_column($pdo->query('SHOW INDEX FROM events')->fetchAll(),'Key_name');
if (!in_array('reading_history',$indexes,true)) $pdo->exec('ALTER TABLE events ADD INDEX reading_history (location_id, type, archived, observed_at)');
echo "Awareness migration complete. Existing data retained.\n";
