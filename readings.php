<?php
// Simple Readings Page
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Reading.php';
require_once __DIR__ . '/Location.php';

start_session();
require_login();

// Initialize database and models
$database = new Database();
$pdo = $database->connect();

$reading = new Reading($pdo);
$location = new Location($pdo);

$readings = $reading->getAllRecent(100);
$isAdmin = is_admin();
$locations = $location->getAllActive();
$uiLocations = array_column($locations, null, 'id');

// Export CSV
if (isset($_GET['export']) && $isAdmin) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="readings-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Location', 'Risk Level', 'Rainfall 1h', 'Rainfall 24h', 'Temperature', 'Humidity', 'Observed At']);
    
    foreach ($readings as $r) {
        $locName = $uiLocations[$r['location_id']]['name'] ?? 'Unknown';
        fputcsv($output, [
            $r['id'],
            $locName,
            $r['risk_level'] ?? '',
            $r['rainfall_1h'] ?? '',
            $r['rainfall_24h'] ?? '',
            $r['temperature'] ?? '',
            $r['humidity'] ?? '',
            $r['observed_at'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// UI helper function
function ui_location_label($loc) {
    $parts = [];
    if (!empty($loc['purok'])) $parts[] = e($loc['purok']);
    if (!empty($loc['landmark'])) $parts[] = e($loc['landmark']);
    return implode(' - ', $parts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Readings - SmartSlope</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/bootstrap.min.css')) ?>">
</head>
<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">All Readings</h1>
            <?php if ($isAdmin): ?>
            <a href="?export=1" class="btn btn-outline-primary">Export CSV</a>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Risk Level</th>
                                <th>Rainfall (1h)</th>
                                <th>Rainfall (24h)</th>
                                <th>Temperature</th>
                                <th>Humidity</th>
                                <th>Observed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readings as $r): 
                                $loc = $uiLocations[$r['location_id']] ?? [];
                            ?>
                            <tr>
                                <td><?= e($loc['name'] ?? 'Unknown') ?></td>
                                <td>
                                    <span class="badge bg-<?= e($r['risk_level'] ?? 'unknown') ?>">
                                        <?= e(ucfirst($r['risk_level'] ?? 'Unknown')) ?>
                                    </span>
                                </td>
                                <td><?= e($r['rainfall_1h'] ?? 'N/A') ?> mm</td>
                                <td><?= e($r['rainfall_24h'] ?? 'N/A') ?> mm</td>
                                <td><?= e($r['temperature'] ?? 'N/A') ?>°C</td>
                                <td><?= e($r['humidity'] ?? 'N/A') ?>%</td>
                                <td><?= e(local_date($r['observed_at'] ?? '')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script src="<?= e(url('assets/js/bootstrap.bundle.js')) ?>"></script>
</body>
</html>
