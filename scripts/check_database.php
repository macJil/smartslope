<?php
// Read-only setup check. Run from Terminal; never display credentials in a page.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

echo 'PHP: ' . PHP_VERSION . "\n";
echo 'Database: ' . DB_NAME . ' at ' . DB_HOST . ':' . DB_PORT . "\n";
try {
    $pdo = db();
    $pdo->query('SELECT id, full_name, username, email, phone, password, role FROM users LIMIT 1');
    $pdo->query('SELECT id, name, lat, lng, active FROM locations LIMIT 1');
    $pdo->query('SELECT id, location_id, type, risk_level FROM events LIMIT 1');
    echo "Connection and required login columns: OK\n";
    $missing = missing_awareness_columns();
    if ($missing) {
        echo "Missing awareness fields: " . implode(', ', $missing) . "\n";
        echo "Back up your database, then run php database/migrate-awareness.php.\n";
        exit(1);
    }
    echo "Database setup: OK\n";
} catch (PDOException $error) {
    fwrite(STDERR, database_error_message($error) . "\n");
    exit(1);
}
