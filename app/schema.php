<?php
declare(strict_types=1);

const LEGACY_READING_COLUMNS = [
    'rainfall_1h','rainfall_24h','rainfall_72h','rainfall_forecast_24h',
    'precipitation_probability_24h','soil_moisture_9_27cm','soil_moisture_27_81cm',
    'risk_level','temperature','humidity','wind_speed','weather_code','observed_at','source',
    'rule_version','rainfall_window_end','provider_payload','adjustment_log','archived','stale'
];
const LEGACY_REPORT_COLUMNS = [
    'message','contact_phone','contact_email','house_landmark','status','report_type',
    'occurred_at','reviewed_by','reviewed_at'
];
const READING_AWARENESS_COLUMNS = ['rule_version','rainfall_window_end','provider_payload','adjustment_log'];
const REPORT_AWARENESS_COLUMNS = ['report_type','occurred_at','reviewed_by','reviewed_at'];

function schema_table_exists(string $table): bool {
    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function schema_table_columns(string $table): array {
    if (!preg_match('/^[a-z_]+$/', $table) || !schema_table_exists($table)) return [];
    return array_column(db()->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(), 'Field');
}

function schema_ensure_index(PDO $pdo, string $table, string $name, string $definition): void {
    $indexes = array_column($pdo->query('SHOW INDEX FROM `' . $table . '`')->fetchAll(), 'Key_name');
    if (!in_array($name, $indexes, true)) $pdo->exec('ALTER TABLE `' . $table . '` ADD ' . $definition);
}

function missing_awareness_columns(): array {
    $required = ['readings'=>READING_AWARENESS_COLUMNS, 'reports'=>REPORT_AWARENESS_COLUMNS];
    $missing = [];
    foreach ($required as $table => $columns) {
        $existing = schema_table_columns($table);
        if (!$existing) {
            $missing[] = $table;
            continue;
        }
        foreach (array_diff($columns, $existing) as $column) $missing[] = $table . '.' . $column;
    }
    return $missing;
}

function require_awareness_schema(): void {
    if (missing_awareness_columns()) throw new RuntimeException('Database setup is incomplete. Run php database/migrate-awareness.php before serving the updated application.');
}
