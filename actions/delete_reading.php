<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
start_session();
require_admin();
require_post_csrf();

$readingId = filter_var($_POST['reading_id'] ?? null, FILTER_VALIDATE_INT);
if ($readingId && $readingId > 0) {
    archive_reading($readingId);
    flash('success', 'Reading removed from active lists.');
}
redirect('admin.php');
