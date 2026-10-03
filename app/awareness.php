<?php
declare(strict_types=1);
const REPORT_TYPES = ['ground_cracks'=>'Ground cracks','fallen_material'=>'Fallen soil or rock',
    'drainage_problem'=>'Drainage problem','other'=>'Other observed condition'];

function report_occurrence(string $input, ?int $now = null): ?string {
    if ($input === '') return null;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $input, new DateTimeZone('Asia/Manila'));
    if (!$date || $date->format('Y-m-d\TH:i')!==$input || $date->getTimestamp()>($now ?? time())+300) throw new InvalidArgumentException('Enter a valid occurrence time that is not in the future.');
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function awareness_notices(array $assessment, array $baseline): array {
    $notices=[];
    // Administrator edits are presented separately; automated notices use the calculated category.
    $level=$assessment['data_status']==='current' ? $assessment['calculated_category'] : null;
    if ($level===null) $notices[]=['tone'=>'secondary','title'=>'Current assessment unavailable',
        'text'=>'Select a location and refresh weather. Last saved readings remain historical context.'];
    elseif (in_array($level,['medium','high'],true)) $notices[]=['tone'=>$level==='high'?'danger':'warning',
        'title'=>'Prototype rainfall notice: ' . ucfirst($level),
        'text'=>implode(' ', $assessment['reasons'])];
    else $notices[]=['tone'=>'info','title'=>'Prototype rainfall screening: ' . ucfirst($level),
        'text'=>'Rainfall is below the medium and high prototype thresholds. This does not establish slope safety.'];
    if ($baseline['classification']==='VERIFIED' && in_array($baseline['category'],['high','very_high','debris_flow'],true)) {
        $notices[]=['tone'=>'warning','title'=>'Baseline susceptibility notice',
            'text'=>'Reviewed MGB class: ' . str_replace('_',' ', $baseline['category']) . '. This baseline is separate from rainfall screening. Boundaries are approximate.'];
    }
    return $notices;
}

function location_report_summary(int $id): array {
    $stmt=db()->prepare("SELECT status, COUNT(*) AS total FROM events WHERE type='report' AND location_id=? GROUP BY status");
    $stmt->execute([$id]);
    return array_map('intval', array_column($stmt->fetchAll(),'total','status'));
}

function ui_report_summary(array $counts, bool $admin): string {
    $html='<h4 class="h6">Community reports <span class="badge bg-secondary">USER-SUBMITTED</span></h4><p>';
    foreach (['pending','reviewed','resolved'] as $state) $html.=e(ucfirst($state)).': '.(int)($counts[$state]??0).' &nbsp; ';
    $html.='</p><p class="small text-muted">Review status is a workflow label, not scientific confirmation. Reports do not change the rainfall category.</p>';
    if ($admin && ($counts['pending']??0)>0) $html.='<a class="btn btn-sm btn-outline-primary" href="'.e(url('admin.php?status=pending')).'">Review pending reports</a>';
    return $html;
}
