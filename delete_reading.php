<?php
require_once __DIR__ . '/app/config.php';
start_session();
require_admin();

$readingId = (int)get('reading_id', 0);
$from = get('from', 'admin.php');

if ($readingId) {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND type = 'reading'");
    $stmt->execute([$readingId]);
    flash('success', 'Reading deleted successfully');
}

redirect($from);
