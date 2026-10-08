<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/RiskAnalyzer.php';
require_once __DIR__ . '/../app/assessment.php';
require_once __DIR__ . '/../app/repositories.php';

start_session();
require_login();
// ... logic (unchanged)
$id = (int)($_POST['reading_id'] ?? $_GET['reading_id'] ?? 0);
header('Location: ../admin.php?action=edit_reading&reading_id=' . $id, true, 307);
exit;
