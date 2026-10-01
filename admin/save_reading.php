<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}
$locationId=filter_var($_POST['location_id'] ?? null,FILTER_VALIDATE_INT);
$readingId=filter_var($_POST['reading_id'] ?? null,FILTER_VALIDATE_INT);
$returnPath='admin/index.php'.($locationId && $locationId>0 ? '?location_id='.$locationId : '');
if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    flash('reading_error','Your session expired. Reload the admin dashboard and try again.');
    redirect_to($returnPath);
}

$repository=new ReadingRepository($pdo);
$action=post_string('action');
$location=$locationId && $locationId>0 ? (new LocationRepository($pdo))->find((int)$locationId) : null;
if (!$location || !(int)$location['is_active']) {
    flash('reading_error','Select an active Irisan location.');
    redirect_to($returnPath);
}
if (in_array($action,['current_update','current_delete'],true)) {
    $observationId=filter_var($_POST['observation_id'] ?? null,FILTER_VALIDATE_INT);
    if (!$observationId || $observationId<1 || !$repository->hasCurrent((int)$locationId,(int)$observationId)) {
        flash('reading_error','The selected current observation was not found.');
        redirect_to($returnPath);
    }
    $values=[];
    if ($action==='current_update') {
        $bounds=['temperature'=>[-99,99],'humidity'=>[0,100],
            'precipitation'=>[0,99999.99],'rain'=>[0,99999.99],'showers'=>[0,99999.99],
            'wind'=>[0,9999.99],'gusts'=>[0,9999.99]];
        foreach ($bounds as $field=>[$minimum,$maximum]) {
            $raw=trim(post_string($field));
            if ($raw==='' || !is_numeric($raw) || !is_finite((float)$raw)
                || (float)$raw<$minimum || (float)$raw>$maximum
                || !preg_match('/\A-?\d{1,5}(?:\.\d{1,2})?\z/',$raw)) {
                flash('reading_error','Enter valid numeric values for every observation field.');
                redirect_to($returnPath);
            }
            $values[$field]=(float)$raw;
        }
    }
    try {
        $pdo->beginTransaction();
        $saved=$action==='current_update'
            ? $repository->updateCurrent((int)$locationId,(int)$observationId,$values)
            : $repository->deleteCurrent((int)$locationId,(int)$observationId);
        if (!$saved) throw new RuntimeException('Observation unavailable.');
        $pdo->commit();
        flash('reading_message',$action==='current_update'
            ? 'Saved log entry corrected. Its rainfall assessment is based on the original hourly data.'
            : 'Log entry removed from the list. Future refreshes create new entries.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('SmartSlope current observation review failed: '.$exception->getMessage());
        flash('reading_error','The observation could not be saved.');
    }
    redirect_to($returnPath);
}
$reading=$readingId && $readingId>0 ? $repository->find((int)$readingId) : null;
if (!$reading || !$location || !(int)$location['is_active']
    || (int)$reading['location_id']!==(int)$locationId
    || (int)$reading['is_archived']===1 || $reading['source_name']!=='Open-Meteo') {
    flash('reading_error','Select an active reading for this Irisan location.');
    redirect_to($returnPath);
}

$values=[];
if ($action==='update') {
    foreach (['rainfall_1h_mm','rainfall_24h_mm','rainfall_72h_mm'] as $field) {
        $raw=trim(post_string($field));
        if (preg_match('/\A\d{1,5}(?:\.\d{1,2})?\z/',$raw)!==1 || (float)$raw>99999.99) {
            flash('reading_error','Enter nonnegative rainfall totals of at most 99,999.99 mm.');
            redirect_to($returnPath);
        }
        $values[$field]=(float)$raw;
    }
} elseif ($action!=='delete') {
    flash('reading_error','Invalid reading action.');
    redirect_to($returnPath);
}

try {
    $pdo->beginTransaction();
    if ($action==='update') {
        $saved=$repository->update((int)$readingId,$values,(int)$_SESSION['user_id']);
        $success='Saved the rainfall correction and recalculated the prototype risk.';
    } else {
        $saved=$repository->setArchived((int)$readingId,true);
        if ($saved) (new AlertRepository($pdo))->synchronize((int)$readingId,(int)$locationId,'low');
        $success='Reading removed from active results; provider history retained.';
    }
    if (!$saved) throw new RuntimeException('The selected reading was unavailable.');
    $pdo->commit();
    flash('reading_message',$success);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SmartSlope reading review failed: '.$exception->getMessage());
    flash('reading_error','The reading could not be saved.');
}
redirect_to($returnPath);
