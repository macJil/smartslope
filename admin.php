<?php
/**
 * SmartSlope - Admin Dashboard
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

start_session();
require_admin();

$userId = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update report status
    if (isset($_POST['update_report_status'])) {
        $reportId = (int)post('report_id');
        $status = post('status');
        
        if (update_report_status($reportId, $status, $userId)) {
            flash('success', 'Report status updated successfully.');
        } else {
            flash('error', 'Failed to update report status.');
        }
        redirect('admin.php');
    }
    
    // Delete report
    if (isset($_POST['delete_report'])) {
        $reportId = (int)post('report_id');
        
        if (delete_report($reportId)) {
            flash('success', 'Report deleted successfully.');
        } else {
            flash('error', 'Failed to delete report.');
        }
        redirect('admin.php');
    }
    
    // Deactivate location
    if (isset($_POST['deactivate_location'])) {
        $locationId = (int)post('location_id');
        
        if (deactivate_location($locationId)) {
            flash('success', 'Location deactivated successfully.');
        } else {
            flash('error', 'Failed to deactivate location.');
        }
        redirect('admin.php');
    }
    
    // Create location
    if (isset($_POST['create_location'])) {
        $name = trim(post('name'));
        $purok = trim(post('purok'));
        $landmark = trim(post('landmark'));
        $lat = (float)post('lat');
        $lng = (float)post('lng');
        $susceptibility = post('susceptibility');
        
        if ($name && $lat && $lng) {
            $locationId = create_location($name, $purok, $landmark, $lat, $lng, $susceptibility);
            flash('success', 'Location created successfully.');
        } else {
            flash('error', 'Name, latitude, and longitude are required.');
        }
        redirect('admin.php');
    }
}

// Get data
$reports = get_all_reports();
$locations = get_all_locations();
$pendingCounts = get_pending_counts();
$users = get_all_users();

// Count reports by status
$reportStatusCounts = [
    'pending' => 0,
    'reviewed' => 0,
    'resolved' => 0,
    'rejected' => 0
];
foreach ($reports as $report) {
    $status = $report['status'] ?? 'pending';
    if (isset($reportStatusCounts[$status])) {
        $reportStatusCounts[$status]++;
    }
}

// Get readings for all locations
$allReadings = [];
foreach ($locations as $loc) {
    $latest = get_latest_reading($loc['id']);
    if ($latest) {
        $allReadings[$loc['id']] = $latest;
    }
}

// View parameter
$view = get('view', 'reports');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Admin Dashboard</title>
    <link rel="stylesheet" href="<?php echo url('assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/vendor/leaflet/leaflet.css'); ?>">
    <script src="<?php echo url('assets/vendor/leaflet/leaflet.js'); ?>"></script>
    <script src="<?php echo url('assets/js/vendor/jquery.min.js'); ?>"></script>
    <style>
        .admin-sidebar { min-height: calc(100vh - 200px); }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .stat-label { color: var(--slope-muted); }
        .report-card { border-left: 4px solid var(--slope-green); }
        .report-pending { border-left-color: #ffc107; }
        .report-reviewed { border-left-color: #0d6efd; }
        .report-resolved { border-left-color: var(--slope-green); }
        .report-rejected { border-left-color: #dc3545; }
        #admin-map { height: 400px; }
        .location-marker { cursor: pointer; }
        .action-btn { font-size: 0.9rem; padding: 0.25rem 0.5rem; margin: 0.1rem; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <main class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <aside class="col-md-3 col-lg-2">
                <div class="card admin-sidebar">
                    <div class="card-body p-0">
                        <ul class="nav nav-pills flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $view === 'reports' ? 'active' : ''; ?>" 
                                   href="admin.php?view=reports">
                                    <i class="bi bi-file-text me-2"></i> Reports
                                    <?php if ($reportStatusCounts['pending'] > 0): ?>
                                        <span class="badge bg-warning text-dark ms-2"><?php echo $reportStatusCounts['pending']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $view === 'locations' ? 'active' : ''; ?>" 
                                   href="admin.php?view=locations">
                                    <i class="bi bi-geo-alt me-2"></i> Locations
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $view === 'users' ? 'active' : ''; ?>" 
                                   href="admin.php?view=users">
                                    <i class="bi bi-people me-2"></i> Users
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $view === 'readings' ? 'active' : ''; ?>" 
                                   href="admin.php?view=readings">
                                    <i class="bi bi-graph-up me-2"></i> All Readings
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $view === 'map' ? 'active' : ''; ?>" 
                                   href="admin.php?view=map">
                                    <i class="bi bi-map me-2"></i> Map View
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10">
                
                <!-- Alerts -->
                <?php if ($success = flash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo e($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error = flash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo e($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Dashboard Overview -->
                <?php if ($view === 'reports' || $view === 'admin'): ?>
                    <div class="row mb-4">
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="card stat-card stat-pending">
                                <div class="card-body text-center">
                                    <div class="stat-number"><?php echo $reportStatusCounts['pending']; ?></div>
                                    <div class="stat-label">Pending Reports</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="card stat-card stat-reviewed">
                                <div class="card-body text-center">
                                    <div class="stat-number"><?php echo $reportStatusCounts['reviewed']; ?></div>
                                    <div class="stat-label">Reviewed Reports</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="card stat-card stat-resolved">
                                <div class="card-body text-center">
                                    <div class="stat-number"><?php echo $reportStatusCounts['resolved']; ?></div>
                                    <div class="stat-label">Resolved Reports</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="card stat-card">
                                <div class="card-body text-center">
                                    <div class="stat-number"><?php echo count($locations); ?></div>
                                    <div class="stat-label">Active Locations</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Reports View -->
                <?php if ($view === 'reports' || $view === 'admin'): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">All Reports</h5>
                            <div>
                                <a href="admin.php?view=reports&status=pending" class="btn btn-sm btn-outline-warning">
                                    Pending (<?php echo $reportStatusCounts['pending']; ?>)
                                </a>
                                <a href="admin.php?view=reports&status=reviewed" class="btn btn-sm btn-outline-primary">
                                    Reviewed (<?php echo $reportStatusCounts['reviewed']; ?>)
                                </a>
                                <a href="admin.php?view=reports&status=resolved" class="btn btn-sm btn-outline-success">
                                    Resolved (<?php echo $reportStatusCounts['resolved']; ?>)
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($reports)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Reporter</th>
                                                <th>Location</th>
                                                <th>Type</th>
                                                <th>Message</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'reviewed' => 'primary',
                                                'resolved' => 'success',
                                                'rejected' => 'danger'
                                            ];
                                            $statusBadges = [
                                                'pending' => 'Pending',
                                                'reviewed' => 'Reviewed',
                                                'resolved' => 'Resolved',
                                                'rejected' => 'Rejected'
                                            ];
                                            
                                            foreach ($reports as $report):
                                                $status = $report['status'] ?? 'pending';
                                                $color = $statusColors[$status] ?? 'secondary';
                                                $badge = $statusBadges[$status] ?? $status;
                                            ?>
                                            <tr class="report-<?php echo $status; ?>">
                                                <td>#<?php echo $report['event_id']; ?></td>
                                                <td>
                                                    <?php echo e($report['reporter_name'] ?? 'Unknown'); ?><br>
                                                    <small class="text-muted">
                                                        <?php echo e($report['reporter_username'] ?? ''); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo e($report['location_name'] ?? 'N/A'); ?><br>
                                                    <small class="text-muted">
                                                        <?php echo e($report['location_purok'] ?? ''); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo e(ucfirst($report['report_type'] ?? 'other')); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo e(substr($report['message'], 0, 50)) . (strlen($report['message']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo $badge; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo local_date($report['event_created_at']); ?></td>
                                                <td>
                                                    <?php if ($status === 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="report_id" value="<?php echo $report['event_id']; ?>">
                                                            <select name="status" class="form-select form-select-sm action-btn" 
                                                                    onchange="this.form.submit()">
                                                                <option value="pending" selected>Pending</option>
                                                                <option value="reviewed">Reviewed</option>
                                                                <option value="resolved">Resolved</option>
                                                                <option value="rejected">Rejected</option>
                                                            </select>
                                                            <input type="hidden" name="update_report_status" value="1">
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to delete this report?')">
                                                        <input type="hidden" name="report_id" value="<?php echo $report['event_id']; ?>">
                                                        <input type="hidden" name="delete_report" value="1">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger action-btn">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">No reports found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Locations View -->
                <?php if ($view === 'locations'): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Manage Locations</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createLocationModal">
                                <i class="bi bi-plus-circle me-1"></i> Add Location
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($locations)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Purok</th>
                                                <th>Landmark</th>
                                                <th>Coordinates</th>
                                                <th>Susceptibility</th>
                                                <th>Latest Reading</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($locations as $loc): ?>
                                                <?php 
                                                    $latest = $allReadings[$loc['id']] ?? null;
                                                    $assessment = $latest ? assess_reading($latest) : null;
                                                ?>
                                                <tr>
                                                    <td><?php echo $loc['id']; ?></td>
                                                    <td><?php echo e($loc['name']); ?></td>
                                                    <td><?php echo e($loc['purok'] ?? 'N/A'); ?></td>
                                                    <td><?php echo e($loc['landmark'] ?? 'N/A'); ?></td>
                                                    <td><?php echo e($loc['lat']); ?>, <?php echo e($loc['lng']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $loc['susceptibility'] === 'high' ? 'danger' : ($loc['susceptibility'] === 'medium' ? 'warning' : 'success'); ?>">
                                                            <?php echo e(ucfirst($loc['susceptibility'] ?? 'unknown')); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($latest && $assessment): ?>
                                                            <?php echo get_risk_badge($assessment); ?>
                                                            <br><small class="text-muted">
                                                                <?php echo local_date($latest['observed_at']); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <span class="text-muted">No data</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Deactivate this location?')">
                                                            <input type="hidden" name="location_id" value="<?php echo $loc['id']; ?>">
                                                            <input type="hidden" name="deactivate_location" value="1">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger action-btn">
                                                                <i class="bi bi-x-circle"></i> Deactivate
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">No locations found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Users View -->
                <?php if ($view === 'users'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">User Management</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($users)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Full Name</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Role</th>
                                                <th>Created At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td><?php echo $user['id']; ?></td>
                                                    <td><?php echo e($user['full_name']); ?></td>
                                                    <td><?php echo e($user['username']); ?></td>
                                                    <td><?php echo e($user['email']); ?></td>
                                                    <td><?php echo e($user['phone']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                            <?php echo e(ucfirst($user['role'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo local_date($user['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">No users found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- All Readings View -->
                <?php if ($view === 'readings'): ?>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">All Readings</h5>
                            <a href="readings.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Full Page
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php 
                                $allReadingsList = get_all_readings(100);
                                foreach ($allReadingsList as &$r) {
                                    $r['assessment'] = assess_reading($r);
                                }
                            ?>
                            <?php if (!empty($allReadingsList)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Location</th>
                                                <th>Risk Level</th>
                                                <th>1h</th>
                                                <th>24h</th>
                                                <th>72h</th>
                                                <th>Temp</th>
                                                <th>Humidity</th>
                                                <th>Observed At</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allReadingsList as $r): ?>
                                                <?php $a = $r['assessment']; ?>
                                                <tr>
                                                    <td><?php echo $r['id']; ?></td>
                                                    <td>
                                                        <?php echo e($r['location_name'] ?? 'N/A'); ?><br>
                                                        <small class="text-muted"><?php echo e($r['purok'] ?? ''); ?></small>
                                                    </td>
                                                    <td><?php echo get_risk_badge($a); ?></td>
                                                    <td><?php echo $r['rainfall_1h'] ?? 'N/A'; ?> mm</td>
                                                    <td><?php echo $r['rainfall_24h'] ?? 'N/A'; ?> mm</td>
                                                    <td><?php echo $r['rainfall_72h'] ?? 'N/A'; ?> mm</td>
                                                    <td><?php echo $r['temperature'] ?? 'N/A'; ?> °C</td>
                                                    <td><?php echo $r['humidity'] ?? 'N/A'; ?> %</td>
                                                    <td><?php echo local_date($r['observed_at']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $a['is_current'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo e(ucfirst($a['data_status'] ?? 'unknown')); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">No readings found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Map View -->
                <?php if ($view === 'map'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Locations Map</h5>
                        </div>
                        <div class="card-body p-0">
                            <div id="admin-map"></div>
                        </div>
                    </div>
                    
                    <script>
                        var adminMap;
                        var irisanGeoJSON = <?php echo file_get_contents(__DIR__ . '/assets/map/irisan.geojson'); ?>;
                        
                        function initAdminMap() {
                            var center = [16.425, 120.56];
                            adminMap = L.map('admin-map').setView(center, 14);
                            
                            L.tileLayer('<?php echo url('assets/map-tiles/{z}/{x}/{y}.png'); ?>', {
                                attribution: 'SmartSlope Local Tiles',
                                minZoom: 12,
                                maxZoom: 18,
                                tms: false
                            }).addTo(adminMap);
                            
                            L.geoJSON(irisanGeoJSON, {
                                style: { color: '#705139', weight: 3, fillOpacity: 0.1, fillColor: '#705139' }
                            }).addTo(adminMap);
                            
                            var locations = <?php echo json_encode($locations); ?>;
                            var allReadings = <?php echo json_encode($allReadings); ?>;
                            
                            locations.forEach(function(loc) {
                                var reading = allReadings[loc.id] || null;
                                var assessment = reading ? <?php echo json_encode(assess_reading([])); ?> : null;
                                if (reading) {
                                    assessment = {
                                        category: reading.risk_level,
                                        is_current: true,
                                        data_status: 'current'
                                    };
                                }
                                
                                var category = assessment ? assessment.category : 'unavailable';
                                var colorClass = getAdminRiskColor(category);
                                var riskLevel = category ? category[0].toUpperCase() : '?';
                                
                                var icon = L.divIcon({
                                    className: 'marker-icon ' + colorClass,
                                    html: '<span class="marker-label">' + riskLevel + '</span>',
                                    iconSize: [30, 30],
                                    iconAnchor: [15, 15]
                                });
                                
                                var marker = L.marker([parseFloat(loc.lat), parseFloat(loc.lng)], {
                                    icon: icon
                                }).addTo(adminMap);
                                
                                var popupContent = '<b>' + (loc.name || 'Location ' + loc.id) + '</b><br>';
                                if (loc.purok) popupContent += 'Purok ' + loc.purok + '<br>';
                                if (reading) {
                                    popupContent += 'Risk: ' + (assessment.category || 'N/A') + '<br>';
                                    popupContent += '1h: ' + (reading.rainfall_1h || 'N/A') + 'mm<br>';
                                    popupContent += '24h: ' + (reading.rainfall_24h || 'N/A') + 'mm<br>';
                                    popupContent += '72h: ' + (reading.rainfall_72h || 'N/A') + 'mm';
                                } else {
                                    popupContent += '<small>No reading data</small>';
                                }
                                
                                marker.bindPopup(popupContent);
                            });
                            
                            if (irisanGeoJSON.features && irisanGeoJSON.features[0]) {
                                var coords = irisanGeoJSON.features[0].geometry.coordinates[0];
                                var bounds = [];
                                coords.forEach(function(coord) {
                                    bounds.push([coord[1], coord[0]]);
                                });
                                adminMap.fitBounds(bounds);
                            }
                        }
                        
                        function getAdminRiskColor(category) {
                            var colors = {
                                'low': 'marker-color-low',
                                'normal': 'marker-color-normal',
                                'medium': 'marker-color-medium',
                                'high': 'marker-color-high',
                                'unavailable': 'marker-color-unknown'
                            };
                            return colors[category] || colors['unavailable'];
                        }
                        
                        document.addEventListener('DOMContentLoaded', function() {
                            initAdminMap();
                        });
                    </script>
                <?php endif; ?>
                
            </main>
        </div>
    </main>
    
    <!-- Create Location Modal -->
    <div class="modal fade" id="createLocationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Location</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="location_name" name="name" required>
                            <label for="location_name">Location Name</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="location_purok" name="purok">
                            <label for="location_purok">Purok (optional)</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="location_landmark" name="landmark">
                            <label for="location_landmark">Landmark (optional)</label>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="location_lat" name="lat" step="0.000001" required>
                                    <label for="location_lat">Latitude</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-floating">
                                    <input type="number" class="form-control" id="location_lng" name="lng" step="0.000001" required>
                                    <label for="location_lng">Longitude</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="location_susceptibility" name="susceptibility">
                                <option value="unknown" selected>Unknown</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                            <label for="location_susceptibility">Susceptibility</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="create_location" value="1">Create Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="<?php echo url('assets/js/bootstrap.bundle.js'); ?>"></script>
</body>
</html>
