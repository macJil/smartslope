<?php
require_once __DIR__ . '/../app/bootstrap.php';
start_session();
require_admin();

$readingId = (int)(($_SERVER['REQUEST_METHOD']??'GET')==='POST' ? post('reading_id',get('reading_id',0)) : get('reading_id',0));
$error = '';
$missingColumns = missing_awareness_columns();

if (!$readingId) {
    flash('error', 'Reading not found');
    redirect('admin.php');
}

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND type = 'reading' AND archived = 0");
$stmt->execute([$readingId]);
$reading = $stmt->fetch();
if ($reading) $reading = assess_reading($reading);

if (!$reading) {
    flash('error', 'Reading not found');
    redirect('admin.php');
}

// CSRF is checked even when form fields are malformed.
if (($_SERVER['REQUEST_METHOD']??'GET') === 'POST') {
    require_post_csrf();
    try {
        $riskLevel=(string)post('risk_level'); $reason=trim((string)post('adjustment_reason'));
        update_reading_risk($readingId,$riskLevel,(int)$_SESSION['user_id'],$reason);
        flash('success','Reading category updated. Rainfall values are unchanged.'); redirect('admin.php');
    } catch (Throwable $exception) {
        error_log('Reading edit failed: '.$exception->getMessage());
        $error=$exception instanceof PDOException ? 'The update could not be saved. Complete database setup in Admin Panel if needed, then try again.' : $exception->getMessage();
        http_response_code($exception instanceof InvalidArgumentException ? 422 : 503);
    }
}

$loc = get_location($reading['location_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Reading</title>
    <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
</head>
<body>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Edit Reading Risk Level</h5>
                    </div>
                    <div class="card-body">
                        <p>Calculated rainfall category: <?= e($reading['assessment']['calculated_category'] ?? 'unavailable') ?>.
                        An edited category is an administrator assessment. New edits retain the administrator, UTC time, previous category and reason. Older edits have no reconstructed history.</p>
                        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
                        <?php if ($missingColumns): ?><div class="alert alert-warning">Database setup is incomplete. <a href="<?= e(url('admin.php')) ?>">Open Admin Panel to complete setup.</a></div><?php endif; ?>
                        <form method="post" action="<?= e(url('actions/save_reading.php?reading_id='.$readingId)) ?>">
                            <input type="hidden" name="reading_id" value="<?= $readingId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="update_reading" value="1">
                            <label for="adjustment_reason" class="form-label mt-3">Adjustment reason *</label>
                            <textarea name="adjustment_reason" id="adjustment_reason" class="form-control mb-3" maxlength="500" required><?= e(post('adjustment_reason')) ?></textarea>

                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <p class="form-control-plaintext">
                                    <?= e($loc['name'] ?? 'Unknown') ?>
                                    <?php if ($loc['lat'] && $loc['lng']): ?>
                                        (<?= sprintf('%.5f, %.5f', $loc['lat'], $loc['lng']) ?>)
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observed At</label>
                                <p class="form-control-plaintext"><?= local_date($reading['observed_at']) ?></p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Rainfall</label>
                                <p class="form-control-plaintext">
                                    1h: <?= $reading['rainfall_1h'] ?? 'N/A' ?> mm |
                                    24h: <?= $reading['rainfall_24h'] ?? 'N/A' ?> mm |
                                    72h: <?= $reading['rainfall_72h'] ?? 'N/A' ?> mm
                                </p>
                            </div>

                            <?php if ($reading['temperature'] || $reading['humidity'] || $reading['wind_speed']): ?>
                            <div class="mb-3">
                                <label class="form-label">Weather Data</label>
                                <p class="form-control-plaintext">
                                    <?php if ($reading['temperature']): ?>
                                        Temp: <?= $reading['temperature'] ?>°C |
                                    <?php endif; ?>
                                    <?php if ($reading['humidity']): ?>
                                        Humidity: <?= $reading['humidity'] ?>% |
                                    <?php endif; ?>
                                    <?php if ($reading['wind_speed']): ?>
                                        Wind: <?= $reading['wind_speed'] ?> km/h
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Current Risk Level</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-<?=
                                        ['low' => 'success', 'normal' => 'primary', 'medium' => 'warning', 'high' => 'danger']
                                        [$reading['risk_level']] ?? 'secondary'
                                    ?>">
                                        <?= e(ucfirst($reading['risk_level'] ?? 'Unavailable')) ?>
                                    </span>
                                </p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Risk Level *</label>
                                <select name="risk_level" class="form-select" required>
                                    <option value="">Select...</option>
                                    <option value="low" <?= post('risk_level', $reading['risk_level']) === 'low' ? 'selected' : '' ?>>Low</option>
                                    <option value="normal" <?= post('risk_level', $reading['risk_level']) === 'normal' ? 'selected' : '' ?>>Normal</option>
                                    <option value="medium" <?= post('risk_level', $reading['risk_level']) === 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="high" <?= post('risk_level', $reading['risk_level']) === 'high' ? 'selected' : '' ?>>High</option>
                                </select>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="<?= e(url('admin.php')) ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
