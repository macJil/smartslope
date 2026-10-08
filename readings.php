<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/risk.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/geography.php';
require_once __DIR__ . '/includes/csv.php';
require_once __DIR__ . '/includes/awareness.php';
require_once __DIR__ . '/includes/views.php';
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.php', true, 303);
    exit;
}

$readings = get_all_readings(100);
$isAdmin = (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin');
$uiLocations = array_column(get_locations(), null, 'id');

// Export CSV
if (get('action') === 'export') {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php', true, 303);
    exit;
}
    download_csv('smartslope_readings_' . gmdate('Y-m-d') . '.csv', export_readings_csv(get_all_readings(PHP_INT_MAX)));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Readings - SmartSlope</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        .badge-risk { font-size: 0.85em; }
    </style>
    <link rel="stylesheet" href="assets/css/frontend.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/frontend.css') ?>">
</head>
<body>
    <?php $active = 'readings'; require __DIR__ . '/partials/navbar.php'; ?>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="page-heading"><h1 class="h2 mb-1">Weather readings</h1><p class="page-subtitle mb-0">Saved observations for Barangay Irisan.</p></div>
            <div>
                <?php if ($isAdmin): ?>
                    <form id="bulkReadingsPageForm" method="post" action="admin.php" data-bulk-confirm="Remove %d reading(s) from active lists? They will be archived." class="d-inline">
                        <input type="hidden" name="action" value="bulk_archive_readings"><input type="hidden" name="return_to" value="readings.php">
                        <button class="btn btn-outline-danger">Remove selected</button>
                    </form>
                    <a href="readings.php?action=export" class="btn btn-outline-success">Export CSV</a>
                <?php endif; ?>
            </div>
        </div>

        <p class="small text-muted">Latest 100 saved readings across active locations. Current means observed within <?= e(round($config['freshness_seconds'] / 3600, 2)) ?> hours. SmartSlope is an academic prototype, not an official warning service. Scroll the table or open a details dialog for more weather information.</p>
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

    <?= ui_weather_modal() ?>
    <script src="assets/js/bootstrap.bundle.js"></script>
    <script src="assets/js/reading-modal.js"></script>
    <?php if ($isAdmin): ?><script src="assets/js/bulk-select.js"></script><?php endif; ?>
<footer class="container py-3 small site-footer">Weather data: <a href="https://open-meteo.com/" rel="noopener noreferrer">Open-Meteo</a> (CC BY 4.0). Map/address data where used: <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>. Manual updates; academic prototype.</footer>
</body>
</html>
