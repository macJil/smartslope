<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/RiskAnalyzer.php';
require_once __DIR__ . '/../app/assessment.php';
require_once __DIR__ . '/../app/susceptibility.php';
require_once __DIR__ . '/../app/awareness.php';
require_once __DIR__ . '/../app/presentation.php';
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
check_ui(str_contains($html, 'Why this category?') && str_contains($html, 'Baseline susceptibility:'), 'Analyzer needs explanation and separate baseline.');
foreach (['Data status:', 'Prototype rainfall-screening category', 'Provider valid time:', 'Retrieved:', 'Source:', 'More weather details'] as $label) {
    check_ui(str_contains($html, $label), 'Missing assessment context: ' . $label);
}
check_ui(str_contains($html, '<template id="assessment-weather-details">'), 'Analyzer details need a dialog template.');
check_ui(str_contains(ui_location_label(['name'=>'Irisan 16.42428, 120.55864','lat'=>16.42428,'lng'=>120.55864]),
    'Barangay Irisan, Baguio City, Benguet, Philippines (16.42428, 120.55864)'), 'Generated names need a meaningful geographic fallback.');
check_ui(str_contains(ui_location_label(['name'=>'Irisan 16.42428, 120.55864','landmark'=>'Purok 7, Irisan']),
    'Purok 7, Irisan, Barangay Irisan, Baguio City'), 'A stored landmark should lead the address.');
$reading['assessment'] = $current;
foreach ([false, true] as $admin) {
    $row = ui_readings_rows([$reading], $admin, 'test', 'testForm', [1=>$location]);
    check_ui(substr_count($row, '<td') === ($admin ? 8 : 6), 'Table column count mismatch.');
    check_ui(str_contains($row, 'reading_ids[]') === $admin, 'Bulk controls must be admin-only.');
    check_ui(str_contains($row, 'actions/save_reading.php') === $admin, 'Edit links must be admin-only.');
    check_ui(str_contains($row, 'data-weather-dialog') && str_contains($row, '<template') &&
        !str_contains($row, '<details') && !str_contains($row, '<img '), 'Modal details must preserve escaped content.');
}
check_ui(str_contains(ui_weather_modal(), 'aria-labelledby="readingWeatherTitle"'), 'Weather dialog needs an accessible title.');
echo "$checks presentation checks passed.\n";
