<?php
/**
 * SmartSlope - User Dashboard
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

start_session();
require_login();

$userId = $_SESSION['user_id'];
$isAdmin = is_admin();

// Get all locations and their latest readings
$locations = get_all_locations();
$readings = [];
$assessments = [];

foreach ($locations as $location) {
    $latest = get_latest_reading($location['id']);
    $readings[$location['id']] = $latest;
    if ($latest) {
        $assessments[$location['id']] = assess_reading($latest);
    } else {
        $assessments[$location['id']] = [
            'risk_level' => null,
            'category' => null,
            'current_category' => null,
            'data_status' => 'unavailable',
            'is_current' => false
        ];
    }
}

// Get counts
$counts = get_reading_counts($readings);

// Get pending reports count for current user's locations
$pendingCounts = get_pending_counts();

// Handle location selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_location'])) {
    $locationId = (int)post('location_id');
    $_SESSION['selected_location_id'] = $locationId;
    redirect('dashboard.php');
}

// Handle weather refresh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_weather'])) {
    $locationId = (int)post('location_id');
    try {
        fetch_and_save_weather($locationId, $userId);
        flash('success', 'Weather data refreshed successfully!');
    } catch (Exception $e) {
        flash('error', 'Failed to refresh weather: ' . $e->getMessage());
    }
    redirect('dashboard.php');
}

// Get selected location from session
$selectedLocationId = $_SESSION['selected_location_id'] ?? null;
$selectedLocation = $selectedLocationId ? get_location_by_id($selectedLocationId) : null;

// Get readings for selected location
$locationReadings = [];
if ($selectedLocationId) {
    $locationReadings = get_readings_by_location($selectedLocationId, 10);
    foreach ($locationReadings as &$r) {
        $r['assessment'] = assess_reading($r);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Dashboard</title>
    <link rel="stylesheet" href="<?php echo url('assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/vendor/leaflet/leaflet.css'); ?>">
    <script src="<?php echo url('assets/vendor/leaflet/leaflet.js'); ?>"></script>
    <script src="<?php echo url('assets/js/vendor/jquery.min.js'); ?>"></script>
    <style>
        #map { height: 500px; width: 100%; }
        .location-card { cursor: pointer; transition: all 0.2s; }
        .location-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .location-card.selected { border: 2px solid var(--slope-green); }
        .risk-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .risk-low { background-color: var(--slope-green); }
        .risk-normal { background-color: #0d6efd; }
        .risk-medium { background-color: #ffc107; }
        .risk-high { background-color: #dc3545; }
        .risk-unavailable { background-color: #6c757d; }
        .dashboard-stats { background: var(--slope-surface); border-radius: 8px; padding: 1.5rem; }
        .stat-card { text-align: center; padding: 1rem; border-radius: 8px; }
        .stat-card .count { font-size: 2rem; font-weight: bold; }
        .stat-card .label { color: var(--slope-muted); }
        .stat-low { background: var(--slope-green-soft); }
        .stat-normal { background: #e7f1ff; }
        .stat-medium { background: #fff3cd; }
        .stat-high { background: #f8d7da; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <main class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <aside class="col-md-4 col-lg-3">
                <div class="sticky-top" style="top: 20px;">
                    <div class="card dashboard-stats mb-4">
                        <h5 class="card-header">Current Status</h5>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 col-sm-3 mb-3">
                                    <div class="stat-card stat-low">
                                        <div class="count"><?php echo $counts['low']; ?></div>
                                        <div class="label">Low</div>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3 mb-3">
                                    <div class="stat-card stat-normal">
                                        <div class="count"><?php echo $counts['normal']; ?></div>
                                        <div class="label">Normal</div>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3 mb-3">
                                    <div class="stat-card stat-medium">
                                        <div class="count"><?php echo $counts['medium']; ?></div>
                                        <div class="label">Medium</div>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3 mb-3">
                                    <div class="stat-card stat-high">
                                        <div class="count"><?php echo $counts['high']; ?></div>
                                        <div class="label">High</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h5 class="card-header">Locations
                            <?php if ($isAdmin): ?>
                                <span class="float-end badge bg-primary"><?php echo count($locations); ?> total</span>
                            <?php endif; ?>
                        </h5>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($locations as $location): ?>
                                    <?php 
                                        $assessment = $assessments[$location['id']] ?? [];
                                        $category = $assessment['category'] ?? 'unavailable';
                                        $riskClass = 'risk-' . ($category === 'unavailable' ? 'unavailable' : $category);
                                        $isSelected = $selectedLocationId === $location['id'];
                                    ?>
                                    <div class="list-group-item location-card <?php echo $isSelected ? 'selected' : ''; ?>"
                                         onclick="selectLocation(<?php echo $location['id']; ?>)">
                                        <div class="d-flex align-items-center">
                                            <span class="risk-indicator <?php echo $riskClass; ?>"></span>
                                            <div class="flex-grow-1">
                                                <strong><?php echo e($location['name']); ?></strong>
                                                <?php if (!empty($location['purok'])): ?>
                                                    <br><small class="text-muted">Purok <?php echo e($location['purok']); ?></small>
                                                <?php endif; ?>
                                                <div class="mt-1">
                                                    <?php echo get_risk_badge($assessment); ?>
                                                </div>
                                            </div>
                                            <?php if (isset($pendingCounts[$location['id']]) && $pendingCounts[$location['id']] > 0): ?>
                                                <span class="badge bg-warning text-dark rounded-pill">
                                                    <?php echo $pendingCounts[$location['id']]; ?> pending
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card-footer text-center">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="refresh_all" value="1">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="refreshAllWeather()">
                                    Refresh All Weather
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($isAdmin): ?>
                        <div class="mt-3 text-center">
                            <a href="admin.php" class="btn btn-primary">
                                <i class="bi bi-shield-check"></i> Admin Panel
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="col-md-8 col-lg-9">
                <div class="card mb-4">
                    <h5 class="card-header">
                        <?php if ($selectedLocation): ?>
                            <?php echo e($selectedLocation['name']); ?>
                            <?php if (!empty($selectedLocation['purok'])): ?>
                                - Purok <?php echo e($selectedLocation['purok']); ?>
                            <?php endif; ?>
                        <?php else: ?>
                            Select a Location
                        <?php endif; ?>
                    </h5>
                    <div class="card-body">
                        <?php if ($selectedLocation): ?>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Location Details</h6>
                                            <dl class="row">
                                                <dt class="col-sm-4">Name:</dt>
                                                <dd class="col-sm-8"><?php echo e($selectedLocation['name']); ?></dd>
                                                
                                                <dt class="col-sm-4">Purok:</dt>
                                                <dd class="col-sm-8"><?php echo e($selectedLocation['purok'] ?? 'N/A'); ?></dd>
                                                
                                                <dt class="col-sm-4">Landmark:</dt>
                                                <dd class="col-sm-8"><?php echo e($selectedLocation['landmark'] ?? 'N/A'); ?></dd>
                                                
                                                <dt class="col-sm-4">Coordinates:</dt>
                                                <dd class="col-sm-8">
                                                    <?php echo e($selectedLocation['lat']); ?>, <?php echo e($selectedLocation['lng']); ?>
                                                </dd>
                                                
                                                <dt class="col-sm-4">Susceptibility:</dt>
                                                <dd class="col-sm-8">
                                                    <span class="badge bg-<?php echo $selectedLocation['susceptibility'] === 'high' ? 'danger' : ($selectedLocation['susceptibility'] === 'medium' ? 'warning' : 'success'); ?>">
                                                        <?php echo e(ucfirst($selectedLocation['susceptibility'] ?? 'unknown')); ?>
                                                    </span>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">Current Weather Status</h6>
                                            <?php 
                                                $latest = $readings[$selectedLocation['id']];
                                                $assessment = $assessments[$selectedLocation['id']];
                                            ?>
                                            <?php if ($latest): ?>
                                                <div class="mb-3">
                                                    <?php echo get_risk_badge($assessment); ?>
                                                </div>
                                                <dl class="row">
                                                    <dt class="col-sm-6">Observed At:</dt>
                                                    <dd class="col-sm-6"><?php echo local_date($latest['observed_at']); ?></dd>
                                                    
                                                    <dt class="col-sm-6">Rainfall (1h):</dt>
                                                    <dd class="col-sm-6"><?php echo $latest['rainfall_1h'] ?? 'N/A'; ?> mm</dd>
                                                    
                                                    <dt class="col-sm-6">Rainfall (24h):</dt>
                                                    <dd class="col-sm-6"><?php echo $latest['rainfall_24h'] ?? 'N/A'; ?> mm</dd>
                                                    
                                                    <dt class="col-sm-6">Rainfall (72h):</dt>
                                                    <dd class="col-sm-6"><?php echo $latest['rainfall_72h'] ?? 'N/A'; ?> mm</dd>
                                                    
                                                    <dt class="col-sm-6">Temperature:</dt>
                                                    <dd class="col-sm-6"><?php echo $latest['temperature'] ?? 'N/A'; ?> °C</dd>
                                                    
                                                    <dt class="col-sm-6">Humidity:</dt>
                                                    <dd class="col-sm-6"><?php echo $latest['humidity'] ?? 'N/A'; ?> %</dd>
                                                    
                                                    <dt class="col-sm-6">Wind Speed:</dt>
                                                    <dd class="col-sm-6"><?php echo $latest['wind_speed'] ?? 'N/A'; ?> km/h</dd>
                                                    
                                                    <dt class="col-sm-6">Data Status:</dt>
                                                    <dd class="col-sm-6">
                                                        <span class="badge bg-<?php echo $assessment['is_current'] ? 'success' : 'warning'; ?>">
                                                            <?php echo e(ucfirst($assessment['data_status'] ?? 'unknown')); ?>
                                                        </span>
                                                    </dd>
                                                </dl>
                                            <?php else: ?>
                                                <p class="text-muted">No weather data available yet.</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="location_id" value="<?php echo $selectedLocation['id']; ?>">
                                                <input type="hidden" name="refresh_weather" value="1">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-arrow-clockwise"></i> Refresh Weather
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Map -->
                            <div class="card mb-4">
                                <h5 class="card-header">Map - Barangay Irisan</h5>
                                <div class="card-body p-0">
                                    <div id="map"></div>
                                </div>
                            </div>
                            
                            <!-- Recent Readings -->
                            <div class="card">
                                <h5 class="card-header">Recent Readings</h5>
                                <div class="card-body">
                                    <?php if (!empty($locationReadings)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Date/Time</th>
                                                        <th>Risk Level</th>
                                                        <th>1h Rainfall</th>
                                                        <th>24h Rainfall</th>
                                                        <th>72h Rainfall</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($locationReadings as $reading): ?>
                                                        <?php $a = $reading['assessment'] ?? assess_reading($reading); ?>
                                                        <tr>
                                                            <td><?php echo local_date($reading['observed_at']); ?></td>
                                                            <td><?php echo get_risk_badge($a); ?></td>
                                                            <td><?php echo $reading['rainfall_1h'] ?? 'N/A'; ?> mm</td>
                                                            <td><?php echo $reading['rainfall_24h'] ?? 'N/A'; ?> mm</td>
                                                            <td><?php echo $reading['rainfall_72h'] ?? 'N/A'; ?> mm</td>
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
                                        <p class="text-muted">No readings available for this location.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="mt-3 text-center">
                                <a href="report.php?location_id=<?php echo $selectedLocation['id']; ?>" 
                                   class="btn btn-primary me-2">
                                    <i class="bi bi-plus-circle"></i> Submit Report
                                </a>
                                <a href="readings.php?location_id=<?php echo $selectedLocation['id']; ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="bi bi-list"></i> View All Readings
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <p class="mb-0">Please select a location from the sidebar to view details and weather data.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </main>
    
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <script>
        // Map initialization
        var map;
        var markers = {};
        var selectedLocationId = <?php echo $selectedLocationId ? $selectedLocationId : 'null'; ?>;
        var locations = <?php echo json_encode($locations); ?>;
        var assessments = <?php echo json_encode($assessments); ?>;
        
        // Irisan boundary
        var irisanGeoJSON = <?php echo file_get_contents(__DIR__ . '/assets/map/irisan.geojson'); ?>;
        
        function initMap() {
            // Center on Irisan
            var center = [16.425, 120.56];
            map = L.map('map').setView(center, 14);
            
            // Add local tiles
            L.tileLayer('<?php echo url('assets/map-tiles/{z}/{x}/{y}.png'); ?>', {
                attribution: 'SmartSlope Local Tiles',
                minZoom: 12,
                maxZoom: 18,
                tms: false
            }).addTo(map);
            
            // Add Irisan boundary
            L.geoJSON(irisanGeoJSON, {
                style: { color: '#705139', weight: 3, fillOpacity: 0.1, fillColor: '#705139' }
            }).addTo(map);
            
            // Add location markers
            locations.forEach(function(loc) {
                var assessment = assessments[loc.id] || {};
                var category = assessment.category || 'unavailable';
                var colorClass = getRiskColor(category);
                var riskLevel = category ? category[0].toUpperCase() : '?';
                
                var icon = L.divIcon({
                    className: 'marker-icon ' + colorClass,
                    html: '<span class="marker-label">' + riskLevel + '</span>',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });
                
                var marker = L.marker([parseFloat(loc.lat), parseFloat(loc.lng)], {
                    icon: icon,
                    zIndexOffset: category === 'high' ? 1000 : (category === 'medium' ? 500 : 0)
                }).addTo(map);
                
                var popupContent = '<b>' + (loc.name || 'Location ' + loc.id) + '</b><br>';
                if (loc.purok) popupContent += 'Purok ' + loc.purok + '<br>';
                if (loc.landmark) popupContent += loc.landmark + '<br>';
                popupContent += '<small>' + (assessment.category ? 'Risk: ' + assessment.category : 'No data') + '</small>';
                
                marker.bindPopup(popupContent);
                
                markers[loc.id] = marker;
                
                // Highlight selected location
                if (selectedLocationId && selectedLocationId == loc.id) {
                    marker.setZIndexOffset(2000);
                }
            });
            
            // Fit map to Irisan boundary
            if (irisanGeoJSON.features && irisanGeoJSON.features[0]) {
                var coords = irisanGeoJSON.features[0].geometry.coordinates[0];
                var bounds = [];
                coords.forEach(function(coord) {
                    bounds.push([coord[1], coord[0]]);
                });
                map.fitBounds(bounds);
            }
            
            // Click handler for map
            map.on('click', function(e) {
                var lat = e.latlng.lat;
                var lng = e.latlng.lng;
                
                // Check if click is inside Irisan
                // For now, just show coordinates
                console.log('Map clicked at:', lat, lng);
            });
        }
        
        function getRiskColor(category) {
            var colors = {
                'low': 'marker-color-low',
                'normal': 'marker-color-normal',
                'medium': 'marker-color-medium',
                'high': 'marker-color-high',
                'unavailable': 'marker-color-unknown'
            };
            return colors[category] || colors['unavailable'];
        }
        
        function selectLocation(locationId) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'dashboard.php';
            form.innerHTML = '<input type="hidden" name="select_location" value="1"><input type="hidden" name="location_id" value="' + locationId + '">';
            document.body.appendChild(form);
            form.submit();
        }
        
        function refreshAllWeather() {
            if (confirm('Refresh weather data for all locations? This may take a moment.')) {
                // For now, just redirect - in production you'd use AJAX
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'dashboard.php';
                form.innerHTML = '<input type="hidden" name="refresh_all" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
        });
    </script>
</body>
</html>
