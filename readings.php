<?php
/**
 * SmartSlope - All Readings
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

start_session();
require_login();

$userId = $_SESSION['user_id'];
$isAdmin = is_admin();

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : null;
    export_readings_csv($locationId);
}

// Get filter parameters
$locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : null;
$statusFilter = get('status', '');
$riskFilter = get('risk', '');

// Get all readings with filters
$readings = [];

if ($locationId > 0) {
    $rawReadings = get_readings_by_location($locationId, 100);
    foreach ($rawReadings as $r) {
        $r['assessment'] = assess_reading($r);
        $readings[] = $r;
    }
} else {
    $rawReadings = get_all_readings(100);
    foreach ($rawReadings as $r) {
        $r['assessment'] = assess_reading($r);
        $readings[] = $r;
    }
}

// Apply filters
$filteredReadings = [];
foreach ($readings as $r) {
    $assessment = $r['assessment'] ?? [];
    
    // Status filter
    if ($statusFilter) {
        $dataStatus = $assessment['data_status'] ?? '';
        if ($statusFilter === 'current' && $dataStatus !== 'current') continue;
        if ($statusFilter === 'outdated' && $dataStatus !== 'outdated') continue;
        if ($statusFilter === 'incomplete' && $dataStatus !== 'incomplete') continue;
    }
    
    // Risk filter
    if ($riskFilter) {
        $category = $assessment['category'] ?? '';
        if ($category !== $riskFilter) continue;
    }
    
    $filteredReadings[] = $r;
}

// Sort by observed_at descending
usort($filteredReadings, function($a, $b) {
    return strtotime($b['observed_at'] ?? '0') <=> strtotime($a['observed_at'] ?? '0');
});

// Get locations for filter
$locations = get_all_locations();

// Count by risk level
$riskCounts = [
    'low' => 0,
    'normal' => 0,
    'medium' => 0,
    'high' => 0,
    'unavailable' => 0
];
foreach ($filteredReadings as $r) {
    $category = ($r['assessment']['category'] ?? null) ?: 'unavailable';
    if (isset($riskCounts[$category])) {
        $riskCounts[$category]++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Readings</title>
    <link rel="stylesheet" href="<?php echo url('assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css'); ?>">
    <script src="<?php echo url('assets/js/vendor/jquery.min.js'); ?>"></script>
    <style>
        .filter-card { background: var(--slope-surface); border-radius: 8px; padding: 1.5rem; }
        .reading-row { transition: background-color 0.2s; }
        .reading-row:hover { background-color: #f8f9fa; }
        .risk-filter-badge { cursor: pointer; margin: 0.25rem; }
        .risk-filter-badge.active { opacity: 1; }
        .risk-filter-badge:not(.active) { opacity: 0.6; }
        .reading-details { font-size: 0.9rem; }
        .reading-details dt { color: var(--slope-muted); }
        .reading-details dd { margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <main class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar Filters -->
            <aside class="col-md-3 col-lg-2">
                <div class="card filter-card mb-4">
                    <h5 class="card-title">Filters</h5>
                    
                    <form method="GET" action="readings.php">
                        <div class="mb-3">
                            <label class="form-label small">Location</label>
                            <select class="form-select form-select-sm" name="location_id">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo $loc['id']; ?>" 
                                        <?php echo $locationId === $loc['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($loc['name']); ?>
                                        <?php if (!empty($loc['purok'])): ?>
                                            (Purok <?php echo e($loc['purok']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="">All Statuses</option>
                                <option value="current" <?php echo $statusFilter === 'current' ? 'selected' : ''; ?>>Current</option>
                                <option value="outdated" <?php echo $statusFilter === 'outdated' ? 'selected' : ''; ?>>Outdated</option>
                                <option value="incomplete" <?php echo $statusFilter === 'incomplete' ? 'selected' : ''; ?>>Incomplete</option>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                            <a href="readings.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <div class="card">
                    <h5 class="card-header">Risk Distribution</h5>
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between">
                            <?php 
                                $riskColors = [
                                    'low' => 'success',
                                    'normal' => 'primary',
                                    'medium' => 'warning',
                                    'high' => 'danger',
                                    'unavailable' => 'secondary'
                                ];
                                $riskLabels = [
                                    'low' => 'Low',
                                    'normal' => 'Normal',
                                    'medium' => 'Medium',
                                    'high' => 'High',
                                    'unavailable' => 'N/A'
                                ];
                            ?>
                            <?php foreach ($riskCounts as $risk => $count): ?>
                                <?php if ($count > 0 || $risk === 'unavailable'): ?>
                                    <a href="?risk=<?php echo $risk; ?>&<?php echo http_build_query(['location_id' => $locationId, 'status' => $statusFilter]); ?>"
                                       class="risk-filter-badge badge bg-<?php echo $riskColors[$risk]; ?> <?php echo $riskFilter === $risk ? 'active' : ''; ?>">
                                        <?php echo $riskLabels[$risk]; ?>: <?php echo $count; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 text-center">
                    <a href="?export=csv&<?php echo http_build_query(['location_id' => $locationId]); ?>"
                       class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </a>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            All Readings
                            <span class="badge bg-secondary ms-2"><?php echo count($filteredReadings); ?> total</span>
                        </h5>
                        
                        <div>
                            <?php if ($isAdmin): ?>
                                <a href="admin.php?view=readings" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-gear me-1"></i> Admin View
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($filteredReadings)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Location</th>
                                            <th>Risk Level</th>
                                            <th>Rainfall</th>
                                            <th>Temperature</th>
                                            <th>Humidity</th>
                                            <th>Wind</th>
                                            <th>Observed At</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($filteredReadings as $r): ?>
                                            <?php 
                                                $a = $r['assessment'] ?? [];
                                                $location = get_location_by_id($r['location_id']);
                                            ?>
                                            <tr class="reading-row">
                                                <td><?php echo $r['id']; ?></td>
                                                <td>
                                                    <strong><?php echo e($r['location_name'] ?? 'N/A'); ?></strong><br>
                                                    <small class="text-muted"><?php echo e($r['purok'] ?? ''); ?></small>
                                                </td>
                                                <td><?php echo get_risk_badge($a); ?></td>
                                                <td>
                                                    <small class="d-block">
                                                        1h: <?php echo $r['rainfall_1h'] ?? 'N/A'; ?> mm
                                                    </small>
                                                    <small class="d-block">
                                                        24h: <?php echo $r['rainfall_24h'] ?? 'N/A'; ?> mm
                                                    </small>
                                                    <small class="d-block">
                                                        72h: <?php echo $r['rainfall_72h'] ?? 'N/A'; ?> mm
                                                    </small>
                                                </td>
                                                <td><?php echo $r['temperature'] ?? 'N/A'; ?> °C</td>
                                                <td><?php echo $r['humidity'] ?? 'N/A'; ?> %</td>
                                                <td><?php echo $r['wind_speed'] ?? 'N/A'; ?> km/h</td>
                                                <td><?php echo local_date($r['observed_at']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $a['is_current'] ? 'success' : 'warning'; ?>">
                                                        <?php echo e(ucfirst($a['data_status'] ?? 'unknown')); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <p class="mb-0 text-center">No readings match the current filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </main>
</body>
</html>
