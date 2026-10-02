<?php
require_once __DIR__ . '/app/bootstrap.php';
start_session();
require_login();

$readings = get_all_readings(100);
$isAdmin = is_admin();
$uiLocations = array_column(get_locations(), null, 'id');

// Export CSV
if (get('action') === 'export') {
    require_admin();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="readings_'.date('Y-m-d').'.csv"');
    echo export_readings_csv(get_all_readings(PHP_INT_MAX));
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Readings - SmartSlope</title>
    <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
    <style>
        .badge-risk { font-size: 0.85em; }
    </style>
    <link rel="stylesheet" href="<?= e(url('assets/css/frontend.css')) ?>">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= url() ?>">SmartSlope</a>
            <div class="navbar-nav">
                <a class="nav-link" href="<?= url('dashboard.php') ?>">Dashboard</a>
                <a class="nav-link" href="<?= url('readings.php') ?>">All Readings</a>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline"><?= csrf_field() ?><button class="nav-link btn btn-link" type="submit">Logout</button></form>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Weather Readings</h2>
            <div>
                <?php if ($isAdmin): ?>
                    <form id="bulkReadingsPageForm" method="post" action="<?= e(url('admin.php')) ?>" data-bulk-confirm="Remove %d reading(s) from active lists? They will be archived." class="d-inline">
                        <?= csrf_field() ?><input type="hidden" name="action" value="bulk_archive_readings"><input type="hidden" name="return_to" value="readings.php">
                        <button class="btn btn-outline-danger">Remove selected</button>
                    </form>
                    <a href="<?= url('readings.php?action=export') ?>" class="btn btn-outline-success">Export CSV</a>
                <?php endif; ?>
            </div>
        </div>

        <p class="small text-muted">Latest 100 saved readings across active locations. Current means observed within <?= e(round($config['freshness_seconds'] / 3600, 2)) ?> hours. SmartSlope is an academic prototype, not an official warning service. Scroll the table or expand details for more weather information.</p>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable records table">
                    <table class="table table-hover table-striped mb-0">
                        <?= ui_readings_head($isAdmin, 'page-readings') ?>
                        <tbody>
                            <?= ui_readings_rows($readings, $isAdmin, 'page-readings', 'bulkReadingsPageForm', $uiLocations) ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= url('assets/js/bootstrap.bundle.js') ?>"></script>
    <?php if ($isAdmin): ?><script src="<?= e(url('assets/js/bulk-select.js')) ?>"></script><?php endif; ?>
</body>
</html>
