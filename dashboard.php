<?php
// Simple Dashboard Page
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/Location.php';
require_once __DIR__ . '/Reading.php';
require_once __DIR__ . '/ReportModel.php';

start_session();
require_login();

// Initialize database and models
$database = new Database();
$pdo = $database->connect();

$location = new Location($pdo);
$reading = new Reading($pdo);
$report = new Report($pdo);

// Get user info
$userId = $_SESSION['user_id'];
$isAdmin = is_admin();

// Get locations and their latest readings
$locations = $location->getAllActive();
$pendingCounts = $report->getPendingCounts();

foreach ($locations as &$loc) {
    $loc['latest'] = $reading->getLatestByLocation($loc['id']);
    $loc['pending'] = $pendingCounts[$loc['id']] ?? 0;
}
unset($loc);

$selectedLocId = (int)get('location_id', 0);
$selectedLoc = null;

foreach ($locations as $loc) {
    if ($loc['id'] == $selectedLocId) {
        $selectedLoc = $loc;
        break;
    }
}

// Get readings
if ($selectedLocId) {
    $readings = $reading->getByLocation($selectedLocId, 10);
} else {
    $readings = $reading->getAllRecent(20);
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
    <title>SmartSlope - Dashboard</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/vendor/leaflet/leaflet.css')) ?>">
    <style>
        #dashboard-map { height: 400px; width: 100%; }
        .marker-icon {
            width: 30px; height: 30px; border-radius: 50%;
            border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; color: white; font-size: 10px;
        }
        .marker-low { background: #28a745; }
        .marker-normal { background: #007bff; }
        .marker-medium { background: #ffc107; color: #000; }
        .marker-high { background: #dc3545; }
        .marker-unknown { background: #6c757d; }
        .stale { opacity: 0.7; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <main class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <aside class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mb-1">
                        Locations
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= !$selectedLocId ? 'active' : '' ?>" href="dashboard.php">
                                All Locations
                            </a>
                        </li>
                        <?php foreach ($locations as $loc): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $selectedLocId == $loc['id'] ? 'active' : '' ?>" 
                               href="dashboard.php?location_id=<?= e($loc['id']) ?>">
                                <?= e($loc['name']) ?>
                                <?php if ($loc['pending'] > 0): ?>
                                    <span class="badge bg-danger"><?= e($loc['pending']) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                </div>
                
                <!-- Map Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div id="dashboard-map"></div>
                    </div>
                </div>
                
                <!-- Readings Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Readings</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Location</th>
                                        <th>Risk Level</th>
                                        <th>Rainfall (24h)</th>
                                        <th>Temperature</th>
                                        <th>Observed At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($readings as $r): 
                                        $locName = '';
                                        foreach ($locations as $loc) {
                                            if ($loc['id'] == $r['location_id']) {
                                                $locName = $loc['name'];
                                                break;
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= e($locName) ?></td>
                                        <td>
                                            <span class="badge 
                                                <?= 'bg-' . ($r['risk_level'] ?? 'unknown') ?>">
                                                <?= e(ucfirst($r['risk_level'] ?? 'Unknown')) ?>
                                            </span>
                                        </td>
                                        <td><?= e($r['rainfall_24h'] ?? 'N/A') ?> mm</td>
                                        <td><?= e($r['temperature'] ?? 'N/A') ?>°C</td>
                                        <td><?= e(local_date($r['observed_at'] ?? '')) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </main>
    
    <script src="<?= e(url('assets/js/bootstrap.bundle.js')) ?>"></script>
    <script src="<?= e(url('assets/vendor/leaflet/leaflet.js')) ?>"></script>
    <script>
        // Simple map initialization
        var map = L.map('dashboard-map').setView([16.409, 120.611], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        
        // Add markers for locations
        <?php foreach ($locations as $loc): 
            if (!empty($loc['lat']) && !empty($loc['lng'])):
        ?>
        var marker = L.marker([<?= $loc['lat'] ?>, <?= $loc['lng'] ?>]).addTo(map);
        var riskLevel = '<?= $loc["latest"]["risk_level"] ?? "unknown" ?>';
        var icon = '<div class="marker-icon marker-' + riskLevel + '">' + 
                   (riskLevel.substring(0,1).toUpperCase()) + '</div>';
        marker.setIcon(L.divIcon({html: icon, className: 'marker-custom'}));
        marker.bindPopup('<?= e($loc["name"]) ?> - <?= e(ucfirst($loc["latest"]["risk_level"] ?? "Unknown")) ?>');
        <?php endif; endforeach; ?>
    </script>
</body>
</html>
