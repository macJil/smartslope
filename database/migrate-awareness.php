<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/bootstrap.php';
migrate_awareness_schema();
echo "Awareness migration complete. Existing data retained.\n";
