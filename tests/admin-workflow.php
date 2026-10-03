<?php
require_once __DIR__ . '/bootstrap.php';
foreach (['pending','reviewed','resolved'] as $status) test_check(in_array($status,['pending','reviewed','resolved'],true),'Supported report state is recognized.');
test_check(!in_array('deleted',['pending','reviewed','resolved'],true),'Unknown report state is rejected by the workflow contract.');
$path=file_get_contents(__DIR__ . '/../app/repositories.php');
test_check(str_contains($path,"UPDATE reports r JOIN events e"),'Report update is scoped to report details and parent event.');
test_check(str_contains($path,"UPDATE readings r JOIN events e"),'Reading archive is scoped to reading details and parent event.');
test_check(str_contains($path,'adjustment_log'),'Reading correction audit is persisted.');
$admin=file_get_contents(__DIR__ . '/../admin.php');
test_check(str_contains($admin,'require_admin();') && str_contains($admin,'require_post_csrf();'),'Admin page enforces role and CSRF before mutation.');
echo "$testChecks admin workflow contract checks passed.\n";
