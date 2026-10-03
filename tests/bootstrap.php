<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$testChecks = 0;
function test_check(bool $condition, string $message): void {
    global $testChecks;
    if (!$condition) throw new RuntimeException($message);
    $testChecks++;
}
function test_finish(string $suite): never {
    global $testChecks;
    echo $testChecks . ' ' . $suite . " checks passed.\n";
    exit(0);
}
function test_reset_count(): void {
    global $testChecks;
    $testChecks = 0;
}
function require_test_database(): PDO {
    $name = (string)($GLOBALS['config']['db_name'] ?? '');
    if (!str_ends_with($name, '_test')) throw new RuntimeException('Refusing database test: DB_DATABASE must end in _test.');
    return db();
}
