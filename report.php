<?php
/**
 * SmartSlope - Submit Report
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

start_session();
require_login();

$userId = $_SESSION['user_id'];

// Get location ID from query or session
$locationId = (int)get('location_id', $_SESSION['selected_location_id'] ?? 0);

// Redirect to dashboard if no location selected
if ($locationId <= 0) {
    flash('error', 'Please select a location first.');
    redirect('dashboard.php');
}

$location = get_location_by_id($locationId);
if (!$location) {
    flash('error', 'Selected location not found.');
    redirect('dashboard.php');
}

// Get report types
$reportTypes = [
    'landslide' => 'Landslide',
    'flood' => 'Flood',
    'crack' => 'Ground Crack',
    'erosion' => 'Soil Erosion',
    'other' => 'Other'
];

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportType = post('report_type', 'other');
    $message = trim(post('message'));
    $houseLandmark = trim(post('house_landmark'));
    $contactPhone = trim(post('contact_phone'));
    $contactEmail = trim(post('contact_email'));
    
    // Validation
    if (empty($message)) {
        flash('error', 'Message is required.');
        redirect('report.php?location_id=' . $locationId);
    }
    
    $reportData = [
        'location_id' => $locationId,
        'message' => $message,
        'report_type' => $reportType,
        'house_landmark' => $houseLandmark,
        'contact_phone' => $contactPhone,
        'contact_email' => $contactEmail,
        'occurred_at' => date('Y-m-d H:i:s')
    ];
    
    try {
        $eventId = create_report($locationId, $userId, $reportData);
        flash('success', 'Report submitted successfully! Thank you for your contribution.');
        redirect('dashboard.php');
    } catch (Exception $e) {
        flash('error', 'Failed to submit report: ' . $e->getMessage());
        redirect('report.php?location_id=' . $locationId);
    }
}

// Get latest reading for this location
$latestReading = get_latest_reading($locationId);
$assessment = $latestReading ? assess_reading($latestReading) : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Submit Report</title>
    <link rel="stylesheet" href="<?php echo url('assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/vendor/leaflet/leaflet.css'); ?>">
    <script src="<?php echo url('assets/vendor/leaflet/leaflet.js'); ?>"></script>
    <script src="<?php echo url('assets/js/vendor/jquery.min.js'); ?>"></script>
    <style>
        #report-map { height: 300px; }
        .report-form { background: var(--slope-surface); border-radius: 8px; padding: 2rem; }
        .report-header { border-left: 4px solid var(--slope-green); padding-left: 1rem; }
        .form-section { margin-bottom: 2rem; }
        .form-section h6 { color: var(--slope-green); border-bottom: 1px solid var(--slope-border); padding-bottom: 0.5rem; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card report-form">
                    <div class="card-header report-header">
                        <h4 class="mb-0">Submit Ground Condition Report</h4>
                    </div>
                    <div class="card-body">
                        <!-- Location Info -->
                        <div class="alert alert-info mb-4">
                            <h6>Selected Location:</h6>
                            <p class="mb-0">
                                <strong><?php echo e($location['name']); ?></strong>
                                <?php if (!empty($location['purok'])): ?>
                                    - Purok <?php echo e($location['purok']); ?>
                                <?php endif; ?>
                                <?php if (!empty($location['landmark'])): ?>
                                    <br><?php echo e($location['landmark']); ?>
                                <?php endif; ?>
                                <br><small class="text-muted">
                                    Coordinates: <?php echo e($location['lat']); ?>, <?php echo e($location['lng']); ?>
                                </small>
                            </p>
                        </div>
                        
                        <!-- Current Weather Status -->
                        <?php if ($latestReading && $assessment): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Current Weather Context</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <dl class="row">
                                                <dt class="col-sm-6">Risk Level:</dt>
                                                <dd class="col-sm-6"><?php echo get_risk_badge($assessment); ?></dd>
                                                
                                                <dt class="col-sm-6">Rainfall (1h):</dt>
                                                <dd class="col-sm-6"><?php echo $latestReading['rainfall_1h'] ?? 'N/A'; ?> mm</dd>
                                                
                                                <dt class="col-sm-6">Rainfall (24h):</dt>
                                                <dd class="col-sm-6"><?php echo $latestReading['rainfall_24h'] ?? 'N/A'; ?> mm</dd>
                                                
                                                <dt class="col-sm-6">Rainfall (72h):</dt>
                                                <dd class="col-sm-6"><?php echo $latestReading['rainfall_72h'] ?? 'N/A'; ?> mm</dd>
                                            </dl>
                                        </div>
                                        <div class="col-md-6">
                                            <dl class="row">
                                                <dt class="col-sm-6">Temperature:</dt>
                                                <dd class="col-sm-6"><?php echo $latestReading['temperature'] ?? 'N/A'; ?> °C</dd>
                                                
                                                <dt class="col-sm-6">Humidity:</dt>
                                                <dd class="col-sm-6"><?php echo $latestReading['humidity'] ?? 'N/A'; ?> %</dd>
                                                
                                                <dt class="col-sm-6">Wind Speed:</dt>
                                                <dd class="col-sm-6"><?php echo $latestReading['wind_speed'] ?? 'N/A'; ?> km/h</dd>
                                                
                                                <dt class="col-sm-6">Data Status:</dt>
                                                <dd class="col-sm-6">
                                                    <span class="badge bg-<?php echo $assessment['is_current'] ? 'success' : 'warning'; ?>">
                                                        <?php echo e(ucfirst($assessment['data_status'] ?? 'unknown')); ?>
                                                    </span>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Map Preview -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Location on Map</h6>
                            </div>
                            <div class="card-body p-0">
                                <div id="report-map"></div>
                            </div>
                        </div>
                        
                        <!-- Report Form -->
                        <form method="POST" action="report.php?location_id=<?php echo $locationId; ?>">
                            <input type="hidden" name="location_id" value="<?php echo $locationId; ?>">
                            
                            <div class="form-section">
                                <h6>Report Details</h6>
                                
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="report_type" name="report_type" required>
                                        <?php foreach ($reportTypes as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo $value === 'other' ? 'selected' : ''; ?>>
                                                <?php echo e($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="report_type">Report Type</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <textarea class="form-control" id="message" name="message" 
                                              placeholder="Describe the ground condition..." required style="height: 150px;"></textarea>
                                    <label for="message">Description</label>
                                    <div class="form-text">Provide as much detail as possible about the observed condition.</div>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="house_landmark" name="house_landmark" 
                                           placeholder="House number, street, or landmark">
                                    <label for="house_landmark">House/Landmark (optional)</label>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h6>Contact Information</h6>
                                <p class="text-muted small">This information will help the admin contact you for follow-up.</p>
                                
                                <div class="form-floating mb-3">
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                           placeholder="Phone number" value="<?php echo e($_SESSION['phone'] ?? ''); ?>">
                                    <label for="contact_phone">Phone Number</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                           placeholder="Email address" value="<?php echo e($_SESSION['email'] ?? ''); ?>">
                                    <label for="contact_email">Email Address</label>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <strong>Important:</strong> Only submit genuine observations. False reports may result in account suspension.
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i> Submit Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        var reportMap;
        var location = <?php echo json_encode(['lat' => $location['lat'], 'lng' => $location['lng']]); ?>;
        var irisanGeoJSON = <?php echo file_get_contents(__DIR__ . '/assets/map/irisan.geojson'); ?>;
        
        function initReportMap() {
            reportMap = L.map('report-map').setView([parseFloat(location.lat), parseFloat(location.lng)], 16);
            
            L.tileLayer('<?php echo url('assets/map-tiles/{z}/{x}/{y}.png'); ?>', {
                attribution: 'SmartSlope Local Tiles',
                minZoom: 12,
                maxZoom: 18,
                tms: false
            }).addTo(reportMap);
            
            L.geoJSON(irisanGeoJSON, {
                style: { color: '#705139', weight: 3, fillOpacity: 0.1, fillColor: '#705139' }
            }).addTo(reportMap);
            
            // Add selected location marker
            var icon = L.divIcon({
                className: 'marker-icon marker-color-high',
                html: '<span class="marker-label">X</span>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            L.marker([parseFloat(location.lat), parseFloat(location.lng)], {
                icon: icon
            }).addTo(reportMap)
             .bindPopup('<b>Selected Location</b><br><?php echo e($location['name']); ?>')
             .openPopup();
            
            // Fit to location
            reportMap.setView([parseFloat(location.lat), parseFloat(location.lng)], 16);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            initReportMap();
        });
    </script>
</body>
</html>
