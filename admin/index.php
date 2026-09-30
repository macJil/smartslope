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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope Admin</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
    <script src="<?= e(app_url('assets/js/bootstrap.bundle.js')) ?>" defer></script>
</head>
<body>
<header class="nav" style="background-color: aliceblue; display:flex; justify-content:space-between; align-items:center; padding:10px 20px;">
    <h1>SmartSlope — Report Review</h1>
    <nav class="d-flex gap-2" aria-label="Admin pages">
        <a class="btn btn-outline-primary" href="<?= e(app_url('admin/locations.php')) ?>">Manage locations</a>
        <a class="btn btn-outline-primary" href="<?= e(app_url('admin/readings.php')) ?>">Manage readings</a>
    </nav>
    <form action="<?= e(app_url('configs/logout.php')) ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-outline-danger" type="submit">Log out</button>
    </form>
</header>
<main class="container my-4">
    <h2>Welcome, <?= e($_SESSION['full_name'] ?? 'Administrator') ?></h2>
    <?php include __DIR__ . '/reports.php'; ?>
</main>
<?php include __DIR__ . '/../components/footer.html'; ?>
</body>
</html>