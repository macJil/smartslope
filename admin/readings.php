<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
$readingRepository = new ReadingRepository($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        flash('reading_error', 'Your session expired. Reload the page and try again.');
        redirect_to('admin/readings.php');
    }

    $action = post_string('action');
    $readingId = filter_var($_POST['reading_id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'delete' && $readingId && $readingId > 0) {
        if ($readingRepository->setArchived($readingId, true)) {
            flash('reading_message', 'Reading removed from active listings; history preserved.');
        } else {
            flash('reading_error', 'The reading could not be updated.');
        }
    }
    redirect_to('admin/readings.php');
}

if (($_GET['action'] ?? '') === 'download') {
    redirect_to('admin/download_readings.php');
}

$readingRows = $readingRepository->adminList();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Readings | SmartSlope</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
</head>
<body>
<header class="container py-3 d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">Readings</h1>
    <div class="d-flex gap-2">
        <a class="btn btn-primary" href="<?= e(app_url('admin/readings.php?action=download')) ?>">Download CSV</a>
        <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/index.php')) ?>">Back to admin</a>
    </div>
</header>
<main class="container pb-4">
    <?php if ($message = flash('reading_message')): ?>
        <div class="alert alert-info" role="status"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($message = flash('reading_error')): ?>
        <div class="alert alert-danger" role="alert"><?= e($message) ?></div>
    <?php endif; ?>
    <section class="card">
        <div class="card-header"><h2 class="h5 mb-0">API-collected readings</h2></div>
        <div class="table-responsive"><table class="table table-striped align-middle mb-0">
            <thead><tr><th>Observed</th><th>Location</th><th>Rainfall (1h / 24h / 72h)</th><th>Risk</th><th>Source</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($readingRows as $reading): ?>
                <tr>
                    <td><?= e(display_local_datetime($reading['observed_at'])) ?></td>
                    <td><?= e($reading['location_name']) ?><?= $reading['purok_zone'] ? ' — ' . e($reading['purok_zone']) : '' ?></td>
                    <td><?= e($reading['rainfall_1h_mm'] ?? '—') ?> / <?= e($reading['rainfall_24h_mm'] ?? '—') ?> / <?= e($reading['rainfall_72h_mm'] ?? '—') ?> mm</td>
                    <td><?= e(ucfirst($reading['risk_level'])) ?></td>
                    <td><?= e($reading['source_name']) ?></td>
                    <td>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('admin/reading.php?id=' . ((int) $reading['reading_id']))) ?>">Edit</a>
                            <form method="post" action="<?= e(app_url('admin/readings.php')) ?>" >
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="reading_id" value="<?= (int) $reading['reading_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$readingRows): ?><tr><td colspan="6" class="text-center">No readings have been recorded.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</main>
</body>
</html>