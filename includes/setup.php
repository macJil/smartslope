<?php

// Database setup

const AWARENESS_COLUMNS = ['rule_version' => 'VARCHAR(40) NULL','rainfall_window_end' => 'DATETIME NULL',
    'provider_payload' => 'LONGTEXT NULL','adjustment_log' => 'LONGTEXT NULL','report_type' => 'VARCHAR(30) NULL',
    'occurred_at' => 'DATETIME NULL','reviewed_by' => 'INT UNSIGNED NULL','reviewed_at' => 'DATETIME NULL'];
function missing_awareness_columns(): array
{
    $existing = array_column(db()->query('SHOW COLUMNS FROM events')->fetchAll(), 'Field');
    return array_diff(array_keys(AWARENESS_COLUMNS), $existing);
}
function require_awareness_schema(): void
{
    if (missing_awareness_columns()) {
        throw new RuntimeException('Database setup is incomplete. Open Admin Panel and click Complete database setup.');
    }
}
function migrate_awareness_schema(): void
{
    $pdo = db();
    foreach (missing_awareness_columns() as $name) {
        $pdo->exec('ALTER TABLE events ADD COLUMN `' . $name . '` ' . AWARENESS_COLUMNS[$name]);
    }
    $indexes = array_column($pdo->query('SHOW INDEX FROM events')->fetchAll(), 'Key_name');
    if (!in_array('reading_history', $indexes, true)) {
        $pdo->exec('ALTER TABLE events ADD INDEX reading_history (location_id, type, archived, observed_at)');
    }
}
