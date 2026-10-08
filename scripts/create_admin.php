<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 4 || !preg_match('/^[a-zA-Z0-9_]{3,50}$/', $argv[1]) ||
    strlen($argv[2]) > 254 || !filter_var($argv[2], FILTER_VALIDATE_EMAIL) || !preg_match('/^\+?[0-9]{10,15}$/', $argv[3])) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php username email phone\n");
    exit(1);
}
fwrite(STDOUT, "New admin password (at least 12 characters): ");
$hide = PHP_OS_FAMILY !== 'Windows' && function_exists('system');
if ($hide) system('stty -echo 2>/dev/null');
try {
    $password = rtrim((string)fgets(STDIN), "\r\n");
} finally {
    if ($hide) system('stty echo 2>/dev/null');
    fwrite(STDOUT, "\n");
}
if (strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Password must be 12-72 bytes.\n");
    exit(1);
}
try {
    $pdo = db();
    $find = $pdo->prepare('SELECT id, role FROM users WHERE username = ?');
    $find->execute([$argv[1]]);
    $existing = $find->fetch();
    if ($existing && $existing['role'] !== 'admin') {
        throw new RuntimeException('Username belongs to a resident. Choose another username.');
    }
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ?, password = ? WHERE id = ? AND role = 'admin'");
        $stmt->execute([$argv[2], $argv[3], password_hash($password, PASSWORD_DEFAULT), $existing['id']]);
        fwrite(STDOUT, "Admin password updated.\n");
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, phone, password, role) VALUES ('Administrator', ?, ?, ?, ?, 'admin')");
        $stmt->execute([$argv[1], $argv[2], $argv[3], password_hash($password, PASSWORD_DEFAULT)]);
        fwrite(STDOUT, "Admin created.\n");
    }
} catch (PDOException $error) {
    fwrite(STDERR, "Unable to create admin; check your database and unique username/email/phone.\n");
    exit(1);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
