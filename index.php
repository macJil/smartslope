<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
$adminReportFilter = (string) ($_GET['status'] ?? '');
if (!in_array($adminReportFilter, ['', 'pending', 'reviewed', 'resolved'], true)) {
    $adminReportFilter = '';
}
$adminReports = (new ReportRepository($pdo))->adminQueue(
    $adminReportFilter === '' ? null : $adminReportFilter
);
$mapLocations=(new LocationRepository($pdo))->activeForStudyArea();
$mapIsAdmin=true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope Admin</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('assets/vendor/leaflet/leaflet.css')) ?>">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/location-map.css')) ?>">
    <script src="<?= e(app_url('assets/js/bootstrap.bundle.js')) ?>" defer></script>
</head>
<body>
<header class="nav" style="background-color: aliceblue; display:flex; justify-content:space-between; align-items:center; padding:10px 20px;">
    <h1>SmartSlope — Report Review</h1>
    <nav class="d-flex gap-2" aria-label="Admin pages">
        <a class="btn btn-outline-primary" href="<?= e(app_url('admin/readings.php')) ?>">Manage readings</a>
    </nav>
    <form action="<?= e(app_url('configs/logout.php')) ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-outline-danger" type="submit">Log out</button>
    </form>
</header>
<main class="container my-4">
    <h2>Welcome, <?= e($_SESSION['full_name'] ?? 'Administrator') ?></h2>
    <?php include __DIR__ . '/../components/location_map.php'; ?>
    <div class="row g-3 mt-1">
        <div class="col-xl-6 d-flex"><?php include __DIR__ . '/../resident/risk_area.php'; ?></div>
        <div class="col-xl-6 d-flex"><?php include __DIR__ . '/reports.php'; ?></div>
    </div>
    <div class="mt-3"><?php include __DIR__ . '/../resident/weather_readings.php'; ?></div>
</main>
<?php include __DIR__ . '/../components/footer.html'; ?>
<script src="<?= e(app_url('assets/js/vendor/jquery.min.js')) ?>" defer></script>
<script src="<?= e(app_url('assets/vendor/leaflet/leaflet.js')) ?>" defer></script>
<script src="<?= e(app_url('assets/js/location-map.js')) ?>" defer></script>
<script src="<?= e(app_url('assets/js/app.js?v=' . filemtime(__DIR__ . '/../assets/js/app.js'))) ?>" defer></script>
</body>
</html>
