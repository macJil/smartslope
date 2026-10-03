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

function migrate_awareness_schema(): void {
    $pdo = db();
    if (!schema_table_exists('events')) throw new RuntimeException('The events table does not exist. Import database/schema.sql first.');

    $eventColumns = schema_table_columns('events');
    $hasLegacyData = in_array('rainfall_1h', $eventColumns, true) && in_array('message', $eventColumns, true);

    // Earlier awareness deployments stored these fields directly on events.
    if ($hasLegacyData) {
        $legacyAwareness = [
            'rule_version'=>'VARCHAR(40) NULL', 'rainfall_window_end'=>'DATETIME NULL',
            'provider_payload'=>'LONGTEXT NULL', 'adjustment_log'=>'LONGTEXT NULL',
            'report_type'=>'VARCHAR(30) NULL', 'occurred_at'=>'DATETIME NULL',
            'reviewed_by'=>'INT UNSIGNED NULL', 'reviewed_at'=>'DATETIME NULL'
        ];
        foreach ($legacyAwareness as $name => $definition) {
            if (!in_array($name, $eventColumns, true)) $pdo->exec('ALTER TABLE events ADD COLUMN `' . $name . '` ' . $definition);
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS readings (
        event_id INT UNSIGNED NOT NULL,
        rainfall_1h DECIMAL(7,2) NULL,
        rainfall_24h DECIMAL(7,2) NULL,
        rainfall_72h DECIMAL(7,2) NULL,
        rainfall_forecast_24h DECIMAL(7,2) NULL,
        precipitation_probability_24h TINYINT UNSIGNED NULL,
        soil_moisture_9_27cm DECIMAL(6,4) NULL,
        soil_moisture_27_81cm DECIMAL(6,4) NULL,
        risk_level ENUM('low','normal','medium','high') NULL,
        temperature DECIMAL(5,2) NULL,
        humidity DECIMAL(5,2) NULL,
        wind_speed DECIMAL(6,2) NULL,
        weather_code INT NULL,
        observed_at DATETIME NULL,
        source ENUM('openmeteo','manual') DEFAULT 'openmeteo',
        rule_version VARCHAR(40) NULL,
        rainfall_window_end DATETIME NULL,
        provider_payload LONGTEXT NULL,
        adjustment_log LONGTEXT NULL,
        archived TINYINT(1) NOT NULL DEFAULT 0,
        stale TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (event_id),
        KEY reading_history (archived, observed_at, event_id),
        CONSTRAINT readings_event_fk FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        event_id INT UNSIGNED NOT NULL,
        message TEXT NULL,
        contact_phone VARCHAR(20) NULL,
        contact_email VARCHAR(254) NULL,
        house_landmark VARCHAR(255) NULL,
        status ENUM('pending','reviewed','resolved') DEFAULT 'pending',
        report_type VARCHAR(30) NULL,
        occurred_at DATETIME NULL,
        reviewed_by INT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        PRIMARY KEY (event_id),
        KEY report_status (status, event_id),
        CONSTRAINT reports_event_fk FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        CONSTRAINT reports_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    schema_ensure_index($pdo, 'events', 'event_location_type', 'INDEX event_location_type (location_id, type)');
    schema_ensure_index($pdo, 'events', 'event_type_created', 'INDEX event_type_created (type, created_at)');
    schema_ensure_index($pdo, 'readings', 'reading_history', 'INDEX reading_history (archived, observed_at, event_id)');
    schema_ensure_index($pdo, 'reports', 'report_status', 'INDEX report_status (status, event_id)');

    // Copy only while all legacy source columns are still present. INSERT IGNORE
    // makes an interrupted migration restartable without overwriting new details.
    $eventColumns = schema_table_columns('events');
    if (in_array('rainfall_1h', $eventColumns, true) && in_array('message', $eventColumns, true)) {
        $pdo->exec("INSERT IGNORE INTO readings (
            event_id, rainfall_1h, rainfall_24h, rainfall_72h, rainfall_forecast_24h,
            precipitation_probability_24h, soil_moisture_9_27cm, soil_moisture_27_81cm,
            risk_level, temperature, humidity, wind_speed, weather_code, observed_at, source,
            rule_version, rainfall_window_end, provider_payload, adjustment_log, archived, stale
        ) SELECT id, rainfall_1h, rainfall_24h, rainfall_72h, rainfall_forecast_24h,
            precipitation_probability_24h, soil_moisture_9_27cm, soil_moisture_27_81cm,
            risk_level, temperature, humidity, wind_speed, weather_code, observed_at, source,
            rule_version, rainfall_window_end, provider_payload, adjustment_log, archived, stale
          FROM events WHERE type = 'reading'");
        $pdo->exec("INSERT IGNORE INTO reports (
            event_id, message, contact_phone, contact_email, house_landmark, status,
            report_type, occurred_at, reviewed_by, reviewed_at
        ) SELECT e.id, e.message, e.contact_phone, e.contact_email, e.house_landmark, e.status,
            e.report_type, e.occurred_at,
            CASE WHEN reviewer.id IS NULL THEN NULL ELSE e.reviewed_by END, e.reviewed_at
          FROM events e LEFT JOIN users reviewer ON reviewer.id = e.reviewed_by
          WHERE e.type = 'report'");
    }

    $invalid = (int)$pdo->query("SELECT COUNT(*) FROM events e LEFT JOIN readings r ON r.event_id=e.id WHERE e.type='reading' AND r.event_id IS NULL")->fetchColumn()
        + (int)$pdo->query("SELECT COUNT(*) FROM events e LEFT JOIN reports r ON r.event_id=e.id WHERE e.type='report' AND r.event_id IS NULL")->fetchColumn()
        + (int)$pdo->query("SELECT COUNT(*) FROM readings r JOIN events e ON e.id=r.event_id WHERE e.type<>'reading'")->fetchColumn()
        + (int)$pdo->query("SELECT COUNT(*) FROM reports r JOIN events e ON e.id=r.event_id WHERE e.type<>'report'")->fetchColumn();
    if ($invalid !== 0) throw new RuntimeException('Event detail migration verification failed. Legacy columns were retained; restore from backup or inspect detail-table rows before retrying.');

    $dropColumns = array_values(array_intersect(array_merge(LEGACY_READING_COLUMNS, LEGACY_REPORT_COLUMNS), schema_table_columns('events')));
    if ($dropColumns) {
        $clauses = array_map(static fn(string $column): string => 'DROP COLUMN `' . $column . '`', $dropColumns);
        $pdo->exec('ALTER TABLE events ' . implode(', ', $clauses));
    }
}
