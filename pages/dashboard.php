<?php
require_once __DIR__ . '/../app/config.php';
start_session();
require_login();

// Get all active locations with latest readings
$locations = get_locations();
$pendingCounts = get_pending_counts();

foreach ($locations as &$loc) {
    $loc['latest'] = get_latest_reading($loc['id']);
    $loc['pending'] = $pendingCounts[$loc['id']] ?? 0;
}

$isAdmin = is_admin();
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
    <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/leaflet/leaflet.css') ?>">
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
        .marker-color-low { background: #28a745; }
        .marker-color-normal { background: #007bff; }
        .marker-color-medium { background: #ffc107; color: #000; }
        .marker-color-high { background: #dc3545; }
        .marker-color-unknown { background: #6c757d; }
        .stale { opacity: 0.7; }
        .selected-marker {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #007bff;
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
            border-top: 15px solid #007bff;
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
            left: 1rem;
            max-width: calc(100% - 2rem);
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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= url() ?>">SmartSlope</a>
            <div class="navbar-nav ms-auto">
                <?php if ($isAdmin): ?>
                    <a class="nav-link" href="<?= url('admin.php') ?>">Admin</a>
                <?php else: ?>
                    <a class="nav-link" href="<?= url('report.php') ?>">Submit Report</a>
                <?php endif; ?>
                <a class="nav-link" href="<?= url('readings.php') ?>">All Readings</a>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline"><?= csrf_field() ?><button class="nav-link btn btn-link" type="submit">Logout</button></form>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-lg-4 my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Welcome, <?= e($_SESSION['full_name']) ?> (<?= e($_SESSION['role']) ?>)</h2>
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
                    <span><span class="badge bg-secondary risk-badge me-1"></span>Unknown</span>
                    <span><span class="badge bg-info risk-badge me-1"></span>Selected</span>
                </div>
            </div>
        </div>

        <?php
        $latestReading = null;
        if ($selectedLocId && $selectedLoc) {
            $latestReading = get_latest_reading($selectedLocId);
        }
        ?>
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Risk Status</h5>
                        <?php if ($selectedLocId && $selectedLoc): ?>
                            <button type="button" id="refresh-weather" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-arrow-clockwise"></i> Refresh Weather
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body" id="risk-content" data-location="<?= e($selectedLoc['name'] ?? '') ?>">
                        <?php if ($selectedLoc && $latestReading): ?>
                            <div class="display-6 fw-bold text-<?=
                                ['low' => 'success', 'normal' => 'primary', 'medium' => 'warning', 'high' => 'danger'][$latestReading['risk_level']] ?? 'secondary'
                            ?>">
                                <?= ucfirst($latestReading['risk_level']) ?> Risk
                            </div>
                            <p class="mb-0">
                                <strong>Location:</strong> <?= e($selectedLoc['name']) ?><br>
                                <strong>Coordinates:</strong>
                                <?= $selectedLoc['lat'] && $selectedLoc['lng'] ?
                                    sprintf('%.5f, %.5f', $selectedLoc['lat'], $selectedLoc['lng']) : 'N/A' ?><br>
                                <strong>1h Rainfall:</strong> <?= $latestReading['rainfall_1h'] ?? 'N/A' ?> mm<br>
                                <strong>24h Rainfall:</strong> <?= $latestReading['rainfall_24h'] ?? 'N/A' ?> mm<br>
                                <strong>72h Rainfall:</strong> <?= $latestReading['rainfall_72h'] ?? 'N/A' ?> mm<br>
                                <strong>Next 24h forecast:</strong> <?= $latestReading['rainfall_forecast_24h'] ?? 'N/A' ?> mm
                                (<?= $latestReading['precipitation_probability_24h'] ?? 'N/A' ?>% max chance)<br>
                                <strong>Modeled soil moisture:</strong>
                                <?= $latestReading['soil_moisture_9_27cm'] ?? 'N/A' ?> (9-27 cm) /
                                <?= $latestReading['soil_moisture_27_81cm'] ?? 'N/A' ?> (27-81 cm) m³/m³<br>
                                <strong>Observed:</strong> <?= local_date($latestReading['observed_at']) ?>
                            </p>
                            <p class="text-muted small">Forecast and soil moisture are model estimates, not on-site sensor readings. Risk category remains a preliminary rainfall screening.</p>
                            <p class="text-muted small mb-0">
                                Temperature: <?= $latestReading['temperature'] ?? 'N/A' ?>°C |
                                Humidity: <?= $latestReading['humidity'] ?? 'N/A' ?>% |
                                Wind: <?= $latestReading['wind_speed'] ?? 'N/A' ?> km/h
                            </p>
                        <?php else: ?>
                            <p class="text-muted">No location selected. Click a location on the map to view risk status.</p>
                            <div class="display-6 fw-bold text-secondary">Unavailable</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5>Quick Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h3 class="mb-0"><?= count($locations) ?></h3>
                                <small class="text-muted">Monitored Locations</small>
                            </div>
                            <div class="col-6 mb-3">
                                <h3 class="mb-0"><?= array_sum(array_column($locations, 'pending')) ?></h3>
                                <small class="text-muted">Pending Reports</small>
                            </div>
                            <div class="col-6">
                                <h3 class="mb-0"><?= count(array_filter($readings, fn($r) => $r['risk_level'] === 'high')) ?></h3>
                                <small class="text-muted">High Risk Readings</small>
                            </div>
                            <div class="col-6">
                                <h3 class="mb-0"><?= count(array_filter($readings, fn($r) => $r['risk_level'] === 'medium')) ?></h3>
                                <small class="text-muted">Medium Risk Readings</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Recent Weather Readings</h5>
                <a href="<?= url('readings.php') ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Location</th>
                                <th>Risk</th>
                                <th>1h Rain</th>
                                <th>24h Rain</th>
                                <th>72h Rain</th>
                                <th>24h Forecast</th>
                                <th>Rain Chance</th>
                                <th>Soil Moisture</th>
                                <th>Observed</th>
                                <th>Status</th>
                                <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="recent-readings-body">
                            <?php foreach ($readings as $r):
                                $loc = get_location($r['location_id']);
                                $staleClass = $r['stale'] ? 'text-muted' : '';
                            ?>
                            <tr class="<?= $staleClass ?>">
                                <td><?= e($loc['name'] ?? 'Unknown') ?></td>
                                <td>
                                    <span class="badge bg-<?=
                                        ['low' => 'success', 'normal' => 'primary', 'medium' => 'warning', 'high' => 'danger'][$r['risk_level']] ?? 'secondary'
                                    ?> risk-badge">
                                        <?= ucfirst($r['risk_level']) ?>
                                    </span>
                                </td>
                                <td><?= $r['rainfall_1h'] ?? 'N/A' ?></td>
                                <td><?= $r['rainfall_24h'] ?? 'N/A' ?></td>
                                <td><?= $r['rainfall_72h'] ?? 'N/A' ?></td>
                                <td><?= $r['rainfall_forecast_24h'] ?? 'N/A' ?> mm</td>
                                <td><?= $r['precipitation_probability_24h'] ?? 'N/A' ?>%</td>
                                <td><?= $r['soil_moisture_9_27cm'] ?? 'N/A' ?> / <?= $r['soil_moisture_27_81cm'] ?? 'N/A' ?> m³/m³</td>
                                <td><?= local_date($r['observed_at']) ?></td>
                                <td><?= $r['stale'] ? 'Stale' : 'Current' ?></td>
                                <?php if ($isAdmin): ?>
                                    <td>
                                        <a href="<?= url('actions/save_reading.php?reading_id=' . $r['id']) ?>"
                                           class="btn btn-xs btn-outline-primary">Edit</a>
                                        <form method="post" action="<?= e(url('actions/delete_reading.php')) ?>" class="d-inline" onsubmit="return confirm('Remove this reading?')"><?= csrf_field() ?><input type="hidden" name="reading_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($readings)): ?>
                                <tr>
                                    <td colspan="<?= $isAdmin ? 11 : 10 ?>" class="text-center text-muted">
                                        No readings yet. Select a location and click Refresh to fetch weather data.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Pending Reports</h5>
                <a href="<?= url('admin.php') ?>" class="btn btn-sm btn-outline-primary">All Reports</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
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
                                <td><?= e($loc['name'] ?? 'Unknown') ?></td>
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
                                    <form method="post" action="<?= e(url('admin.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-primary">Review</button></form>
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

    <form id="map-selection-form" method="post" action="<?= e(url('actions/save_location.php')) ?>" hidden>
        <?= csrf_field() ?><input type="hidden" name="location_id"><input type="hidden" name="lat"><input type="hidden" name="lng">
    </form>
    <script>window.SmartSlope = <?= json_encode(['locationId'=>$selectedLocId,'apiUrl'=>url('api/readings.php'),'csrf'=>csrf_token(),'isAdmin'=>$isAdmin,'editUrl'=>url('actions/save_reading.php?reading_id='),'deleteUrl'=>url('actions/delete_reading.php')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= e(url('assets/js/vendor/jquery.min.js')) ?>"></script>
    <script src="<?= e(url('assets/js/dashboard.js')) ?>"></script>
    <script src="<?= e(url('assets/js/bootstrap.bundle.js')) ?>"></script>
    <script src="<?= url('assets/vendor/leaflet/leaflet.js') ?>"></script>
    <script src="<?= e(url('assets/js/irisan-boundary.js')) ?>"></script>
    <script>
        const locations = <?= json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
        const selectedLocId = <?= $selectedLocId ?>;
        const baseUrl = '<?= url() ?>';

        const irisanBounds = L.latLngBounds(
            [16.407, 120.543],
            [16.435, 120.576]
        );

        const map = L.map('dashboard-map', {
            zoomControl: true,
            scrollWheelZoom: true,
            maxBounds: irisanBounds.pad(0.3),
            maxBoundsViscosity: 1,
            minZoom: 12,
            maxZoom: 16
        }).fitBounds(irisanBounds, { padding: [18, 18], maxZoom: 15 });

        L.tileLayer('<?= url("assets/map-tiles/{z}/{x}/{y}.png") ?>', {
            attribution: 'Barangay Irisan offline map tiles',
            maxNativeZoom: 15,
            maxZoom: 16,
            minZoom: 12,
            tileSize: 256,
            noWrap: true
        }).addTo(map);

        new ResizeObserver(() => map.invalidateSize({ pan: false })).observe(document.getElementById('dashboard-map'));
        requestAnimationFrame(() => map.invalidateSize());

        fetch('<?= url("assets/map/irisan.geojson") ?>')
            .then(response => response.json())
            .then(data => {
                IrisanBoundary.load(data);
                const boundaryLayer = L.geoJSON(data, {
                    interactive: false,
                    style: { color: '#13589e', weight: 3, fillOpacity: 0.08 }
                }).addTo(map);
                map.fitBounds(boundaryLayer.getBounds(), { padding: [18, 18], maxZoom: 15 });
            })
            .catch(() => {
                L.rectangle(irisanBounds, {color: '#13589e', weight: 3, fillOpacity: 0.08, interactive: false}).addTo(map);
                map.fitBounds(irisanBounds, { padding: [18, 18], maxZoom: 15 });
            });

        const markersLayer = L.layerGroup();
        const markersById = new Map();
        window.updateMapRisk = function(id, risk) {
            const marker = markersById.get(Number(id));
            if (!marker) return;
            marker.setIcon(L.divIcon({
                html: `<div class="marker-icon marker-color-${risk}">${risk[0].toUpperCase()}</div>`,
                className: 'marker-div-icon', iconSize: [30, 30], iconAnchor: [15, 15]
            }));
        };

        locations.forEach(loc => {
            if (loc.lat && loc.lng) {
                const latest = loc.latest;
                let colorClass = 'marker-color-unknown';
                let riskLevel = '?';

                if (latest && latest.risk_level) {
                    colorClass = 'marker-color-' + latest.risk_level;
                    riskLevel = latest.risk_level.toUpperCase().substring(0, 1);
                }

                const markerHtml = `<div class="marker-icon ${colorClass}">${riskLevel}</div>`;
                const icon = L.divIcon({
                    html: markerHtml,
                    className: 'marker-div-icon',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                const marker = L.marker([loc.lat, loc.lng], { icon: icon });

                let popupContent = `<b>${String(loc.name).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}</b><br>`;
                if (latest) {
                    popupContent += `Risk: <strong>${latest.risk_level ? latest.risk_level.toUpperCase() : 'Unknown'}</strong><br>`;
                    popupContent += `1h: ${latest.rainfall_1h ?? 'N/A'}mm, 24h: ${latest.rainfall_24h ?? 'N/A'}mm<br>`;
                    popupContent += `Temp: ${latest.temperature ?? 'N/A'}°C<br>`;
                    popupContent += `Observed: ${latest.observed_at ?? 'N/A'}`;
                }
                popupContent += `<br><small>Pending: ${loc.pending || 0}</small>`;

                marker.bindPopup(popupContent);
                marker.on('click', () => {
                    const form = document.getElementById('map-selection-form');
                    form.elements.location_id.value = loc.id;
                    form.submit();
                });
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

            let found = false;
            locations.forEach(loc => {
                if (loc.lat && loc.lng) {
                    const dist = Math.sqrt(
                        Math.pow(loc.lat - lat, 2) + Math.pow(loc.lng - lng, 2)
                    );
                    if (dist < 0.0005) {
                        const form = document.getElementById('map-selection-form');
                        form.elements.location_id.value = loc.id;
                        form.submit();
                        found = true;
                    }
                }
            });

            if (!found) {
                const form = document.getElementById('map-selection-form');
                form.elements.lat.value = lat;
                form.elements.lng.value = lng;
                form.submit();
            }
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
</body>
</html>
