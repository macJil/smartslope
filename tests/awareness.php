<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$checks=0;
function expect(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
$now=time();
$r=['rainfall_1h'=>12,'rainfall_24h'=>60,'rainfall_72h'=>90,'risk_level'=>'medium','observed_at'=>gmdate('Y-m-d H:i:s',$now),'created_at'=>gmdate('Y-m-d H:i:s',$now),'rule_version'=>RiskAnalyzer::VERSION];
$a=reading_assessment($r,[],$now);
$unknown=susceptibility_lookup([]);
expect(awareness_notices($a,$unknown)[0]['title']==='Prototype rainfall notice: Medium','Fresh medium must produce notice.');
$old=reading_assessment($r,[],$now+20000);
expect(awareness_notices($old,$unknown)[0]['title']==='Current assessment unavailable','Old high/medium must not produce current notice.');
$bad=$r; $bad['rainfall_1h']=61;
expect(reading_assessment($bad,[],$now)['data_status']==='incomplete','Reject inconsistent totals.');
$bad=$r; $bad['rule_version']='different';
expect(reading_assessment($bad,[],$now)['current_category']===null,'Reject unknown rule version.');
$bad=$r; $bad['risk_level']='high';
expect(awareness_notices(reading_assessment($bad,[],$now),$unknown)[0]['title']==='Prototype rainfall notice: Medium','Administrator edit must not replace automated notice category.');
$dataset=['type'=>'FeatureCollection','metadata'=>['reviewed'=>true,'crs'=>'EPSG:4326','source_url'=>'https://mgb.gov.ph/','edition'=>'TEST/DEMO','scale'=>'TEST/DEMO','reuse_terms'=>'TEST/DEMO','coverage_review'=>'Synthetic fixture only','reviewed_by'=>'TEST/DEMO','reviewed_at'=>'TEST/DEMO'],
'features'=>[['properties'=>['OBJECTID'=>1,'LndslideSusc'=>'HL'],'geometry'=>['type'=>'Polygon','coordinates'=>[[[120.55,16.41],[120.57,16.41],[120.57,16.43],[120.55,16.43],[120.55,16.41]],[[120.557,16.417],[120.563,16.417],[120.563,16.423],[120.557,16.423],[120.557,16.417]]]]]]];
// Synthetic geometry tests only; never import this fixture into production.
$point=['lat'=>16.425,'lng'=>120.56];
$b=susceptibility_lookup($point,$dataset);
expect($b['category']==='high','Outer polygon lookup.');
expect(count(awareness_notices($old,$b))===2,'Baseline notice must survive outdated rainfall.');
expect(susceptibility_lookup(['lat'=>16.42,'lng'=>120.56],$dataset)['category']==='unknown','Polygon hole must not match.');
expect(susceptibility_lookup(['lat'=>16.43,'lng'=>120.56],$dataset)['category']==='unknown','Boundary ambiguity remains unknown.');
$d=$dataset; $d['metadata']['reviewed']=false;
expect(susceptibility_lookup($point,$d)['category']==='unknown','Unreviewed dataset must not be used.');
$d=$dataset; $d['features'][1]=$d['features'][0]; $d['features'][1]['properties']['LndslideSusc']='LL';
expect(susceptibility_lookup($point,$d)['category']==='unknown','Conflicting classes remain unknown.');
$d=$dataset; $d['features'][0]['geometry']=['type'=>'MultiPolygon','coordinates'=>[$d['features'][0]['geometry']['coordinates']]];
expect(susceptibility_lookup($point,$d)['category']==='high','MultiPolygon must work.');
$d=$dataset; $d['metadata']['source_url']='javascript://mgb.gov.ph/';
expect(susceptibility_lookup($point,$d)['category']==='unknown','Non-HTTPS metadata link rejected.');
expect(susceptibility_lookup(['lat'=>16.425,'lng'=>120.56,'susceptibility'=>'high'])['category']==='unknown','Legacy DB class alone is not verified.');
expect(report_occurrence('2026-01-01T08:00',$now)==='2026-01-01 00:00:00','Philippine occurrence converts to UTC.');
foreach (['2026-02-30T08:00','2099-01-01T00:00'] as $input) {
    try { report_occurrence($input,$now); throw new LogicException('Invalid occurrence accepted.'); }
    catch (InvalidArgumentException $e) { expect(true,'Invalid time rejected.'); }
}
$hostile=$a; $hostile['reasons']=['<script>alert(1)</script>'];
$html=ui_assessment_panel($r,[], $hostile);
expect(!str_contains($html,'<script>') && str_contains($html,'&lt;script&gt;'),'Notice/reasons escaped.');
expect(str_contains(ui_report_summary(['pending'=>2],true),'Review pending reports'),'Admin queue notice.');
expect(!str_contains(ui_report_summary(['pending'=>2],false),'Review pending reports'),'Resident summary has no admin action.');
echo "$checks awareness checks passed.\n";
