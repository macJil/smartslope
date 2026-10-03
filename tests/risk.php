<?php
require_once __DIR__ . '/bootstrap.php';
$analyzer = new RiskAnalyzer();
test_check($analyzer->analyze(0, 0, 0) === 'low', 'Complete values below all thresholds are low.');
test_check($analyzer->analyze(10, 0, 0) === 'normal', 'One-hour normal boundary is inclusive.');
test_check($analyzer->analyze(0, 25, 0) === 'normal', 'Twenty-four-hour normal boundary is inclusive.');
test_check($analyzer->analyze(0, 0, 50) === 'normal', 'Seventy-two-hour normal boundary is inclusive.');
test_check($analyzer->analyze(25, 0, 0) === 'medium', 'Medium boundary is inclusive.');
test_check($analyzer->analyze(0, 50, 0) === 'medium', 'Medium daily boundary is inclusive.');
test_check($analyzer->analyze(0, 0, 100) === 'medium', 'Medium three-day boundary is inclusive.');
test_check($analyzer->analyze(50, 0, 0) === 'high', 'High boundary is inclusive.');
test_check($analyzer->analyze(null, 0, 0) === null, 'Missing history is unavailable.');
test_check($analyzer->analyze(-1, 1, 1) === null, 'Negative rainfall is invalid.');
test_check($analyzer->analyze(INF, 1, 1) === null, 'Infinite rainfall is invalid.');
$explanation = $analyzer->explain(0, 0, 0);
test_check(str_contains(implode(' ', $explanation['reasons']), 'does not mean'), 'Low explanation avoids safety claims.');
echo "$testChecks risk checks passed.\n";
