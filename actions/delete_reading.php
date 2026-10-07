<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/repositories.php';
start_session();
require_admin();

$readingId = filter_var($_POST['reading_id'] ?? null, FILTER_VALIDATE_INT);
if ($readingId && $readingId > 0) {
    archive_reading($readingId);
    flash('success', 'Reading removed from active lists.');
}
redirect('admin.php');
