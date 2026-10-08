<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/risk.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/geography.php';
require_once __DIR__ . '/includes/weather.php';
require_once __DIR__ . '/includes/awareness.php';
require_once __DIR__ . '/includes/views.php';
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.php', true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && get('action') === 'select_location') {
    try {
        $locationId = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
        $address = trim((string)($_POST['address'] ?? ''));
        if (strlen($address) > 255) {
            throw new InvalidArgumentException('The selected address is too long.');
        }
        if ($locationId) {
            $location = get_location($locationId);
            if (!$location || !$location['active']) {
                throw new InvalidArgumentException('Unknown location.');
            }
            if ($address !== '' && $location['lat'] !== null && $location['lng'] !== null) {
                $location = get_or_create_location((float)$location['lat'], (float)$location['lng'], $address);
            }
        } else {
            $lat = filter_var($_POST['lat'] ?? null, FILTER_VALIDATE_FLOAT);
            $lng = filter_var($_POST['lng'] ?? null, FILTER_VALIDATE_FLOAT);
            if (!is_float($lat) || !is_float($lng) || !is_in_irisan($lat, $lng)) {
                throw new InvalidArgumentException('Select a point inside Barangay Irisan.');
            }
            $location = get_or_create_location($lat, $lng, $address);
        }
        refresh_location((int)$location['id']);
        flash('success', 'Weather reading saved.');
        redirect('dashboard.php?location_id=' . $location['id']);
    } catch (Throwable $error) {
        error_log('SmartSlope weather refresh: ' . $error->getMessage());
        flash('error', 'The location was selected, but current weather could not be saved. Try Refresh later.');
        if (isset($location)) {
            redirect('dashboard.php?location_id=' . $location['id']);
        }
        redirect('dashboard.php');
    }
}


// Get all active locations with latest readings
$locations = get_locations();
$uiLocations = array_column($locations, null, 'id');
$pendingCounts = get_pending_counts();

foreach ($locations as &$loc) {
    $loc['latest'] = get_latest_reading($loc['id']);
    $loc['pending'] = $pendingCounts[$loc['id']] ?? 0;
}
unset($loc); // End the reference before iterating locations again.

$isAdmin = (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin');
$selectedLocId = (int)get('location_id', 0);
$selectedLoc = null;
foreach ($locations as $loc) {
    if ($loc['id'] == $selectedLocId) {
        $selectedLoc = $loc;
        break;
    }
}

if ($selectedLocId) {
    $readings = get_readings($selectedLocId, 10);
} else {
    $readings = get_all_readings(20);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Dashboard</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css">
    <style>
        .map-card {
            width: 100%;
            overflow: hidden;
            border: 0;
            border-radius: .5rem;
        }
        .map-surface {
            position: relative;
            width: 100%;
            height: clamp(420px, 64vh, 680px);
            overflow: hidden;
        }
        #dashboard-map {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }
        .leaflet-container {
            width: 100%;
            height: 100%;
        }
        .card-body {
            position: relative;
        }
        .marker-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 5px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 10px;
        }
        .marker-color-low { background: var(--slope-green); }
        .marker-color-normal { background: #0d6efd; }
        .marker-color-medium { background: #ffc107; color: #212529; }
        .marker-color-high { background: #dc3545; }
        .marker-color-unknown { background: #6c757d; }
        .stale { opacity: 0.7; }
        .selected-marker {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0d6efd;
            border: 3px solid white;
            box-shadow: 0 0 10px rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .selected-marker::after {
            content: "";
            position: absolute;
            width: 0;
            height: 0;
            border-left: 10px solid transparent;
            border-right: 10px solid transparent;
            border-top: 15px solid #0d6efd;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
        }
        .risk-badge {
            font-size: 0.85em;
            padding: 4px 8px;
        }
        .map-heading,
        .map-legend {
            position: absolute;
            z-index: 500;
            background: rgba(255, 255, 255, 0.94);
            padding: 0.5rem 0.75rem;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }
        .map-heading {
            top: 1rem;
            left: 3.5rem;
            max-width: calc(100% - 4.5rem);
            pointer-events: none;
        }
        .map-heading h5,
        .map-heading p {
            margin: 0;
        }
        .map-legend {
            right: 1rem;
            bottom: 1rem;
            max-width: calc(100% - 2rem);
        }
        @media (max-width: 575.98px) {
            .map-surface {
                height: 420px;
            }
            .map-legend {
                left: 1rem;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/frontend.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/frontend.css') ?>">
</head>
<body>
    <?php $active = 'dashboard'; require __DIR__ . '/partials/navbar.php'; ?>

    <div class="container-fluid px-3 px-lg-4 my-4">
        <div class="page-heading mb-4">
            <h1 class="h2 mb-1">Irisan dashboard</h1>
            <p class="page-subtitle mb-0">Welcome, <?= e($_SESSION['full_name']) ?>. Select a point on the map to view its saved information.</p>
        </div>

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>

        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger"><?= e($msg) ?></div>
        <?php endif; ?>

        <div class="card mb-4 map-card">
            <div class="map-surface">
                <div id="dashboard-map"></div>
                <div class="map-heading">
                    <h5>Barangay Irisan Map</h5>
                    <p class="text-muted small">Click inside the outline to select a monitoring point.</p>
                </div>
                <div class="map-legend d-flex gap-3 flex-wrap">
                    <span><span class="badge bg-success risk-badge me-1"></span>Low</span>
                    <span><span class="badge bg-primary risk-badge me-1"></span>Normal</span>
                    <span><span class="badge bg-warning risk-badge me-1"></span>Medium</span>
                    <span><span class="badge bg-danger risk-badge me-1"></span>High</span>
                    <span><span class="badge bg-secondary risk-badge me-1"></span>Unavailable</span>
                    <span><span class="badge bg-info risk-badge me-1"></span>Selected</span>
                </div>
            </div>
        </div>

        <?php
        $latestReading = null;
        if ($selectedLocId && $selectedLoc) {
            $latestReading = get_latest_reading($selectedLocId);
        }
        $assessment = reading_assessment($latestReading, $selectedLoc ?? []);
        ?>
        <p id="weather-feedback" class="alert d-none" role="status" aria-live="polite"></p>
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Reading summary</h2>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 col-md-3 mb-3">
                                <h3 class="mb-0"><?= count($locations) ?></h3>
                                <small class="text-muted">Monitored Locations</small>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <h3 class="mb-0"><?= array_sum(array_column($locations, 'pending')) ?></h3>
                                <small class="text-muted">Pending Reports</small>
                            </div>
                            <div class="col-6 col-md-3">
                                <h3 class="mb-0" id="current-high-count"><?= ui_current_count($readings, 'high') ?></h3>
                                <small class="text-muted">Current high readings</small>
                            </div>
                            <div class="col-6 col-md-3">
                                <h3 class="mb-0" id="current-medium-count"><?= ui_current_count($readings, 'medium') ?></h3>
                                <small class="text-muted">Current medium readings</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Landslide awareness analyzer</h2>
                        <?php if ($selectedLocId && $selectedLoc): ?>
                            <button type="button" id="refresh-weather" class="btn btn-sm btn-outline-primary">
                                Refresh weather
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body" id="risk-content" aria-live="polite" aria-atomic="true" data-location="<?= e($selectedLoc['name'] ?? '') ?>">
                        <?= ui_assessment_panel($latestReading, $selectedLoc ?? [], $assessment) ?>
                    </div>
                </div>
            </div>

        </div>

        <?php if ($selectedLoc): ?>
        <div class="card mb-4"><div class="card-header">Selected location: community reports</div><div class="card-body" id="location-reports"><?= ui_report_summary(location_report_summary($selectedLocId),$isAdmin) ?></div></div>
        <div class="card mb-4"><div class="card-header">Selected location: rainfall history</div><div class="card-body" id="rainfall-history"><?= ui_history($readings) ?></div></div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Recent Weather Readings</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($isAdmin): ?>
                        <form id="bulkDashboardReadingsForm" method="post" action="admin.php" data-bulk-confirm="Remove %d reading(s) from active lists? They will be archived." class="m-0">
                            <input type="hidden" name="action" value="bulk_archive_readings"><input type="hidden" name="return_to" value="dashboard.php">
                            <button class="btn btn-sm btn-outline-danger">Remove selected</button>
                        </form>
                    <?php endif; ?>
                    <a href="readings.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable records table">
                    <table class="table table-hover mb-0">
                        <?= ui_readings_head($isAdmin, 'dashboard-readings') ?>
                        <tbody id="recent-readings-body">
                            <?= ui_readings_rows($readings, $isAdmin, 'dashboard-readings', 'bulkDashboardReadingsForm', $uiLocations) ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Pending Reports</h5>
                <a href="admin.php" class="btn btn-sm btn-outline-primary">All Reports</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable records table">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Location</th>
                                <th>Message</th>
                                <th>Reporter</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (get_reports('pending') as $r):
                                $loc = get_location($r['location_id']);
                            ?>
                            <tr>
                                <td>
                                    <?= e($loc['name'] ?? 'Unknown') ?>
                                    <?php if ($r['house_landmark'] ?? ''): ?>
                                        <br><small class="text-muted">Reported address: <?= e($r['house_landmark']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($loc['landmark'] ?? ''): ?>
                                        <br><small class="text-muted">Street/Landmark: <?= e($loc['landmark']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($loc['lat'] && $loc['lng']): ?>
                                        <br><small class="text-muted">Coor: <?= sprintf('%.5f, %.5f', $loc['lat'], $loc['lng']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(substr($r['message'], 0, 50)) ?>...</td>
                                <td>
                                    <?php
                                    $reporter = [];
                                    if ($r['user_id']) {
                                        $pdo = db();
                                        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                                        $stmt->execute([$r['user_id']]);
                                        $reporter = $stmt->fetch();
                                    }
                                    echo e($reporter['full_name'] ?? 'Anonymous');
                                    ?>
                                </td>
                                <td><?= e($r['contact_phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-warning">Pending</span>
                                </td>
                                <td><?= local_date($r['created_at']) ?></td>
                                <td>
                                    <form method="post" action="admin.php"><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-primary">Review</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty(get_reports('pending'))): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No pending reports</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?= ui_weather_modal() ?>
    <form id="map-selection-form" method="post" action="dashboard.php?action=select_location" hidden>
        <input type="hidden" name="location_id"><input type="hidden" name="lat"><input type="hidden" name="lng"><input type="hidden" name="address">
    </form>
    <script>window.SmartSlope = <?= json_encode(['locationId'=>$selectedLocId,'apiUrl'=>'api/readings.php','isAdmin'=>$isAdmin,'editUrl'=>'admin.php?action=edit_reading&reading_id='], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="assets/js/vendor/jquery.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/bootstrap.bundle.js"></script>
    <script src="assets/js/reading-modal.js"></script>
    <?php if ($isAdmin): ?><script src="assets/js/bulk-select.js"></script><?php endif; ?>
    <script src="assets/vendor/leaflet/leaflet.js"></script>
    <script src="assets/js/offline-map.js"></script>
    <script src="assets/js/location-address.js"></script>
    <script src="assets/js/irisan-boundary.js"></script>
    <script>
        const locations = <?= json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
        const selectedLocId = <?= $selectedLocId ?>;
        const baseUrl = './';

        const irisanBounds = L.latLngBounds(
            [16.407, 120.543],
            [16.435, 120.576]
        );

        const map = L.map('dashboard-map', {
            zoomControl: true,
            zoomSnap: 0.25,
            scrollWheelZoom: true,
            maxBounds: irisanBounds.pad(0.3),
            maxBoundsViscosity: 1,
            minZoom: 12,
            maxZoom: 16
        }).fitBounds(irisanBounds, { padding: [32, 32], maxZoom: 14 });

        irisanTiles('assets/map-tiles/{z}/{x}/{y}.png', {
            attribution: 'Barangay Irisan offline map tiles',
            maxNativeZoom: 15,
            maxZoom: 16,
            minZoom: 12,
            tileSize: 256,
            noWrap: true
        }).addTo(map);

        new ResizeObserver(() => map.invalidateSize({ pan: false })).observe(document.getElementById('dashboard-map'));
        requestAnimationFrame(() => map.invalidateSize());

        fetch('assets/map/irisan.geojson')
            .then(response => response.json())
            .then(data => {
                IrisanBoundary.load(data);
                const boundaryLayer = L.geoJSON(data, {
                    interactive: false,
                    style: { color: '#705139', weight: 3, fillOpacity: 0.08 }
                }).addTo(map);
                map.fitBounds(boundaryLayer.getBounds(), { padding: [32, 32], maxZoom: 14 });
            })
            .catch(() => {
                L.rectangle(irisanBounds, {color: '#705139', weight: 3, fillOpacity: 0.08, interactive: false}).addTo(map);
                map.fitBounds(irisanBounds, { padding: [32, 32], maxZoom: 14 });
            });

        const markersLayer = L.layerGroup();
        const markersById = new Map();
        const riskLevels = ['low', 'normal', 'medium', 'high'];
        const riskTextColors = {low: 'success', normal: 'primary', medium: 'warning', high: 'danger', unavailable: 'secondary'};
        let selectionPending = false;
        async function submitLocationSelection(locationId, lat, lng) {
            if (selectionPending) return;
            selectionPending = true;
            const form = document.getElementById('map-selection-form');
            form.elements.location_id.value = locationId || '';
            form.elements.lat.value = lat;
            form.elements.lng.value = lng;
            try { form.elements.address.value = await window.lookupLocationAddress(lat, lng); }
            catch (_) { form.elements.address.value = ''; }
            form.submit();
        }
        function normalizedRisk(risk) {
            return riskLevels.includes(String(risk || '').toLowerCase()) ? String(risk).toLowerCase() : 'unavailable';
        }
        function createLocationPopupContent(location, risk) {
            const content = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = location.name || 'Unknown';
            content.append(name);

            [
                location.landmark ? 'Street/Landmark: ' + location.landmark : '',
                location.purok ? 'Purok: ' + location.purok : '',
                'Barangay Irisan, Baguio City, Benguet, Philippines',
                location.lat && location.lng ? 'Coordinates: ' + Number(location.lat).toFixed(5) + ', ' + Number(location.lng).toFixed(5) : ''
            ].filter(Boolean).forEach(addressLine => {
                const line = document.createElement('div');
                line.textContent = addressLine;
                content.append(line);
            });

            const riskStatus = document.createElement('div');
            riskStatus.className = 'location-risk-status fw-bold mt-2 text-' + riskTextColors[risk];
            riskStatus.textContent = 'Risk status: ' + risk.toUpperCase();
            content.append(riskStatus);
            const freshness = document.createElement('div');
            freshness.className = 'location-data-status';
            const assessment = location.latest?.assessment;
            freshness.textContent = assessment
                ? assessment.data_status + ' — last saved: ' + (assessment.category || 'unavailable')
                : 'No saved reading';
            content.append(freshness);

            if (location.latest) {
                const reading = document.createElement('small');
                reading.className = 'location-weather d-block mt-1';
                reading.textContent = `1h: ${location.latest.rainfall_1h ?? 'N/A'}mm, 24h: ${location.latest.rainfall_24h ?? 'N/A'}mm; Temp: ${location.latest.temperature ?? 'N/A'}°C`;
                content.append(reading);
            }
            const pendingReports = document.createElement('small');
            pendingReports.className = 'd-block';
            pendingReports.textContent = 'Pending reports: ' + (location.pending || 0);
            content.append(pendingReports);

            const selectButton = document.createElement('button');
            selectButton.type = 'button';
            selectButton.className = 'btn btn-sm btn-primary mt-2';
            selectButton.textContent = 'View readings';
            selectButton.addEventListener('click', () => {
                submitLocationSelection(location.id, location.lat, location.lng);
            });
            content.append(selectButton);
            return content;
        }
        window.updateMapRisk = function(id, risk, assessment, latest) {
            const marker = markersById.get(Number(id));
            if (!marker) return;
            const level = normalizedRisk(risk);
            marker.setIcon(L.divIcon({
                html: `<div class="marker-icon marker-color-${level === 'unavailable' ? 'unknown' : level}">${level === 'unavailable' ? '?' : level[0].toUpperCase()}</div>`,
                className: 'marker-div-icon', iconSize: [30, 30], iconAnchor: [15, 15]
            }));
            const popupContent = marker.getPopup().getContent();
            let weather = popupContent.querySelector('.location-weather');
            if (!weather && latest) {
                weather = document.createElement('small');
                weather.className = 'location-weather d-block mt-1';
                popupContent.append(weather);
            }
            if (weather && latest) weather.textContent = `1h: ${latest.rainfall_1h ?? 'N/A'}mm, 24h: ${latest.rainfall_24h ?? 'N/A'}mm; Temp: ${latest.temperature ?? 'N/A'}°C`;
            const freshness = popupContent.querySelector('.location-data-status');
            if (freshness && assessment) freshness.textContent = assessment.data_status + ' — last saved: ' + (assessment.category || 'unavailable');
            const riskStatus = popupContent.querySelector('.location-risk-status');
            if (riskStatus) {
                riskStatus.className = 'location-risk-status fw-bold mt-2 text-' + riskTextColors[level];
                riskStatus.textContent = 'Risk status: ' + level.toUpperCase();
            }
        };

        locations.forEach(loc => {
            if (loc.lat && loc.lng) {
                const latest = loc.latest;
                let colorClass = 'marker-color-unknown';
                let riskLevel = '?';

                const locationRisk = normalizedRisk(latest?.assessment?.current_category);
                if (locationRisk !== 'unavailable') {
                    colorClass = 'marker-color-' + locationRisk;
                    riskLevel = locationRisk[0].toUpperCase();
                }

                const markerHtml = `<div class="marker-icon ${colorClass}">${riskLevel}</div>`;
                const icon = L.divIcon({
                    html: markerHtml,
                    className: 'marker-div-icon',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                const marker = L.marker([loc.lat, loc.lng], { icon: icon });

                marker.bindPopup(createLocationPopupContent(loc, locationRisk));
                marker.locId = loc.id;
                markersById.set(Number(loc.id), marker);
                markersLayer.addLayer(marker);
            }
        });

        map.addLayer(markersLayer);

        let selectedMarker = null;

        map.on('click', function(e) {
            const {lat, lng} = e.latlng;

            if (!IrisanBoundary.contains(lat, lng)) {
                alert('Please click inside Barangay Irisan boundary');
                return;
            }

            if (selectedMarker) {
                map.removeLayer(selectedMarker);
            }

            const selectedIcon = L.divIcon({
                html: '<div class="selected-marker"></div>',
                className: 'selected-marker-icon',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });

            selectedMarker = L.marker([lat, lng], {
                icon: selectedIcon,
                zIndexOffset: 1000
            }).addTo(map);

            map.setView([lat, lng], 16);

            let nearestLocation = null;
            let nearestDistance = Infinity;
            locations.forEach(loc => {
                if (loc.lat && loc.lng) {
                    const dist = Math.sqrt(
                        Math.pow(loc.lat - lat, 2) + Math.pow(loc.lng - lng, 2)
                    );
                    if (dist < nearestDistance) {
                        nearestLocation = loc;
                        nearestDistance = dist;
                    }
                }
            });

            const locationId = nearestLocation && nearestDistance < 0.0005 ? nearestLocation.id : '';
            const target = locationId ? nearestLocation : { lat, lng };
            submitLocationSelection(locationId, target.lat, target.lng);
        });

        <?php if ($selectedLocId && $selectedLoc && $selectedLoc['lat'] && $selectedLoc['lng']): ?>
            const selectedLoc = { lat: <?= $selectedLoc['lat'] ?>, lng: <?= $selectedLoc['lng'] ?> };
            const selectedIcon = L.divIcon({
                html: '<div class="selected-marker"></div>',
                className: 'selected-marker-icon',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });
            selectedMarker = L.marker([selectedLoc.lat, selectedLoc.lng], {
                icon: selectedIcon,
                zIndexOffset: 1000
            }).addTo(map);
            map.setView([selectedLoc.lat, selectedLoc.lng], 16);
        <?php endif; ?>
    </script>
<footer class="container py-3 small site-footer">Weather data: <a href="https://open-meteo.com/" rel="noopener noreferrer">Open-Meteo</a> (CC BY 4.0). Map/address data where used: <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>. Manual updates; academic prototype.</footer>
</body>
</html>
