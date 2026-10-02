<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
$checks = 0;
function check_ui(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { fwrite(STDERR, $message . "\n"); exit(1); }
    $checks++;
}
$now = time();
$reading = ['id'=>1, 'location_id'=>1, 'rainfall_1h'=>35, 'rainfall_24h'=>200,
    'rainfall_72h'=>400, 'risk_level'=>'high', 'observed_at'=>gmdate('Y-m-d H:i:s', $now),
    'created_at'=>gmdate('Y-m-d H:i:s', $now), 'source'=>'openmeteo'];
$current = reading_assessment($reading, [], $now);
$old = $reading; $old['observed_at'] = gmdate('Y-m-d H:i:s', $now - 20000);
$outdated = reading_assessment($old, [], $now);
$missing = $reading; $missing['rainfall_24h'] = null;
$incomplete = reading_assessment($missing, [], $now);
check_ui(str_contains(ui_category_badge($current), 'bg-danger'), 'Fresh high reading needs high color.');
foreach ([$outdated, $incomplete, reading_assessment(null)] as $a) {
    $badge = ui_category_badge($a);
    check_ui(!str_contains($badge, 'bg-danger') && str_contains($badge, 'Unavailable'), 'Noncurrent readings must have neutral unavailable badges.');
}
check_ui(str_contains(ui_category_badge($outdated), 'Last saved: High'), 'Outdated assessment must retain last known category as context.');
check_ui(ui_current_count([['assessment'=>$current], ['assessment'=>$outdated], ['assessment'=>$incomplete]], 'high') === 1, 'Exclude outdated and incomplete rows from current counts.');
$location = ['id'=>1, 'name'=>'<img src=x onerror=alert(1)>', 'susceptibility'=>'unknown'];
$hostile = $current; $hostile['reasons'] = ['<script>alert(1)</script>'];
$html = ui_assessment_panel($reading, $location, $hostile);
check_ui(!str_contains($html, '<script>') && !str_contains($html, '<img '), 'Assessment data must be escaped.');
check_ui(str_contains($html, '&lt;script&gt;'), 'Escaped explanations should remain readable.');
foreach (['Data status:', 'Baseline susceptibility:', '24-hour forecast outlook', 'Observed:', 'Retrieved:', 'Source:', 'not official warnings'] as $label) {
    check_ui(str_contains($html, $label), 'Missing assessment context: ' . $label);
}
$reading['assessment'] = $current;
foreach ([false, true] as $admin) {
    $row = ui_readings_rows([$reading], $admin, 'test', 'testForm', [1=>$location]);
    check_ui(substr_count($row, '<td') === ($admin ? 8 : 6), 'Table column count mismatch.');
    check_ui(str_contains($row, 'reading_ids[]') === $admin, 'Bulk controls must be admin-only.');
    check_ui(str_contains($row, 'actions/save_reading.php') === $admin, 'Edit links must be admin-only.');
    check_ui(str_contains($row, '<details') && !str_contains($row, '<img '), 'Details must preserve escaped content.');
}
echo "$checks presentation checks passed.\n";
