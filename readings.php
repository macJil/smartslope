<?php
require_once __DIR__ . '/app/config.php';
start_session();
require_login();

$readings = get_all_readings(100);
$isAdmin = is_admin();

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
            <h2>All Weather Readings</h2>
            <div>
                <?php if ($isAdmin): ?><a href="<?= url('readings.php?action=export') ?>" class="btn btn-outline-success">
                    Export CSV
                </a><?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Location</th>
                                <th>Risk</th>
                                <th>1h Rain</th>
                                <th>24h Rain</th>
                                <th>72h Rain</th>
                                <th>Next 24h Rain</th>
                                <th>Rain Chance</th>
                                <th>Soil Moisture (9-27 / 27-81 cm)</th>
                                <th>Temp</th>
                                <th>Wind</th>
                                <th>Observed</th>
                                <th>Status</th>
                                <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readings as $r):
                                $loc = get_location($r['location_id']);
                                $staleClass = $r['stale'] ? 'text-muted' : '';
                            ?>
                            <tr class="<?= $staleClass ?>">
                                <td><?= $r['id'] ?></td>
                                <td>
                                    <?= e($loc['name'] ?? 'Unknown') ?>
                                    <?php if ($loc['purok'] ?? ''): ?>
                                        <br><small class="text-muted">Purok: <?= e($loc['purok']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($loc['landmark'] ?? ''): ?>
                                        <br><small class="text-muted">Street/Landmark: <?= e($loc['landmark']) ?></small>
                                    <?php endif; ?>
                                    <br><small class="text-muted">Barangay Irisan, Baguio City, Benguet, Philippines</small>
                                    <?php if ($loc['lat'] && $loc['lng']): ?>
                                        <br><small class="text-muted">Coor: <?= sprintf('%.5f, %.5f', $loc['lat'], $loc['lng']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?=
                                        ['low' => 'success', 'normal' => 'primary', 'medium' => 'warning', 'high' => 'danger']
                                        [$r['risk_level']] ?? 'secondary'
                                    ?> badge-risk">
                                        <?= ucfirst($r['risk_level']) ?>
                                    </span>
                                </td>
                                <td><?= $r['rainfall_1h'] ?? 'N/A' ?></td>
                                <td><?= $r['rainfall_24h'] ?? 'N/A' ?></td>
                                <td><?= $r['rainfall_72h'] ?? 'N/A' ?></td>
                                <td><?= $r['rainfall_forecast_24h'] ?? 'N/A' ?> mm</td>
                                <td><?= $r['precipitation_probability_24h'] ?? 'N/A' ?>%</td>
                                <td><?= $r['soil_moisture_9_27cm'] ?? 'N/A' ?> / <?= $r['soil_moisture_27_81cm'] ?? 'N/A' ?> m³/m³</td>
                                <td><?= $r['temperature'] ?? 'N/A' ?>°C</td>
                                <td><?= $r['wind_speed'] ?? 'N/A' ?> km/h</td>
                                <td><?= local_date($r['observed_at']) ?></td>
                                <td><?= $r['archived'] ? 'Archived' : ($r['stale'] ? 'Stale' : 'Current') ?></td>
                                <?php if ($isAdmin): ?>
                                    <td>
                                        <a href="<?= url('actions/save_reading.php?reading_id=' . $r['id']) ?>"
                                           class="btn btn-xs btn-outline-primary">Edit</a>
                                        <form method="post" action="<?= e(url('actions/delete_reading.php')) ?>" class="d-inline" onsubmit="return confirm('Remove this reading?')">
                                            <?= csrf_field() ?><input type="hidden" name="reading_id" value="<?= (int)$r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($readings)): ?>
                                <tr>
                                    <td colspan="<?= $isAdmin ? 14 : 13 ?>" class="text-center text-muted">
                                        No readings yet
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= url('assets/js/bootstrap.bundle.js') ?>"></script>
</body>
</html>
