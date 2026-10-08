<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
migrate_awareness_schema();
echo "Awareness migration complete. Existing data retained.\n";
