<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
require_admin();
$repository=new ReadingRepository($pdo);
$id=filter_var($_SERVER['REQUEST_METHOD']==='POST' ? ($_POST['reading_id']??null) : ($_GET['id']??null),FILTER_VALIDATE_INT);
if (!$id || $id<1 || !($reading=$repository->find((int)$id)) || (int)$reading['is_archived']===1
    || $reading['source_name']!=='Open-Meteo') {
    flash('reading_error','Select an active API reading to edit.');
    redirect_to('admin/readings.php');
}
$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_is_valid($_POST['csrf_token']??null)) {
        $error='Your session expired. Reload and try again.';
    } else {
        $values=[];
        foreach (['rainfall_1h_mm','rainfall_24h_mm','rainfall_72h_mm'] as $field) {
            $raw=trim(post_string($field));
            $values[$field]=preg_match('/\A\d{1,5}(?:\.\d{1,2})?\z/',$raw)===1
                && (float)$raw<=99999.99 ? (float)$raw : null;
        }
        if (in_array(null,$values,true)) {
            $error='Enter valid nonnegative totals for all three periods.';
        } else {
            try {
                $pdo->beginTransaction();
                $saved=$repository->update((int)$id,$values,(int)$_SESSION['user_id']);
                if ($saved) {
                    $pdo->commit();
                    flash('reading_message','API reading corrected; risk and alert updated.');
                    redirect_to('admin/readings.php');
                }
                $pdo->rollBack();
                $error='Reading unavailable.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Reading correction failed: '.$exception->getMessage());
                $error='Reading could not be updated.';
            }
        }
        $reading=array_merge($reading,array_filter($values,static fn($v)=>$v!==null));
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit API reading | SmartSlope</title><link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>"></head>
<body><main class="container my-4"><h1>Edit API reading</h1>
<p><?= e($reading['source_name']) ?> · <?= e(display_local_datetime($reading['observed_at'])) ?> PHT</p>
<?php if ($error): ?><p class="alert alert-danger" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="<?= e(app_url('admin/reading.php?id='.(int)$id)) ?>">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="reading_id" value="<?= (int)$id ?>">
<?php foreach (['rainfall_1h_mm'=>'1 hour','rainfall_24h_mm'=>'24 hours','rainfall_72h_mm'=>'72 hours'] as $field=>$label): ?>
<div class="mb-3"><label for="<?= e($field) ?>" class="form-label">Rainfall, <?= e($label) ?> (mm)</label>
<input id="<?= e($field) ?>" class="form-control" name="<?= e($field) ?>" type="number" required min="0" max="99999.99" step="0.01" value="<?= e($reading[$field]??'') ?>"></div>
<?php endforeach; ?>
<button class="btn btn-primary">Save correction</button> <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/readings.php')) ?>">Cancel</a>
</form></main></body></html>
