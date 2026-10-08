
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
// Compatibility route; the page logic now lives at the project root.
header('Location: ../dashboard.php?action=select_location', true, 307);
exit;
