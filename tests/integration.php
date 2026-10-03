<?php
require_once __DIR__ . '/bootstrap.php';
$pdo=require_test_database();
$locations=get_locations();
if (!$locations) throw new RuntimeException('Seed an active test location before integration testing.');
$location=$locations[0];
$admin=$pdo->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();
if (!$admin) throw new RuntimeException('Seed an admin test account before integration testing.');
$suffix=bin2hex(random_bytes(4));
$userId=null; $eventIds=[];
try {
    $userId=create_user('Integration Resident','integration_'.$suffix,'integration_'.$suffix.'@test.invalid','+639'.random_int(100000000,999999999),'test-password-only');
    $readingId=create_reading(['location_id'=>(int)$location['id'],'risk_level'=>'medium','rainfall_1h'=>12,'rainfall_24h'=>60,'rainfall_72h'=>90,
        'observed_at'=>gmdate('Y-m-d H:i:s'),'source'=>'manual','rule_version'=>RiskAnalyzer::VERSION,
        'rainfall_window_end'=>gmdate('Y-m-d H:00:00'),'provider_payload'=>'{"classification":"TEST"}']);
    $eventIds[]=$readingId;
    test_check((int)get_latest_reading((int)$location['id'])['id']===$readingId,'New reading event/detail is joined and readable.');
    update_reading_risk($readingId,'high',(int)$admin,'Test-only correction');
    test_check(get_latest_reading((int)$location['id'])['assessment']['adjusted']===true,'Reading override and audit are visible.');
    $reportId=create_report(['location_id'=>(int)$location['id'],'user_id'=>$userId,'message'=>'TEST ONLY','contact_phone'=>'+639000000001',
        'house_landmark'=>'TEST ONLY','report_type'=>'ground_cracks','occurred_at'=>null]);
    $eventIds[]=$reportId;
    $reports=get_reports('pending');
    test_check((bool)array_filter($reports,static fn(array $row): bool => (int)$row['id']===$reportId),'New report appears in pending list.');
    test_check(update_report($reportId,'reviewed',(int)$admin),'Admin can review a pending report.');
    test_check(location_report_summary((int)$location['id'])['reviewed']>=1,'Report count groups by status.');
    test_check(archive_reading($readingId),'Reading can be archived.');
    test_check(!update_report($reportId,'reviewed',(int)$admin),'A repeated review does not claim another change.');
    $csv=export_readings_csv(get_all_readings());
    test_check(str_contains($csv,'Data source'),'Reading export includes its data-source column.');
} finally {
    if ($eventIds) {
        $placeholders=implode(',',array_fill(0,count($eventIds),'?'));
        $delete=$pdo->prepare("DELETE FROM events WHERE id IN ($placeholders)");
        $delete->execute($eventIds);
    }
    if ($userId) { $delete=$pdo->prepare('DELETE FROM users WHERE id=?'); $delete->execute([$userId]); }
}
echo "$testChecks database integration checks passed.\n";
