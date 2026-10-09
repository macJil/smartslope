<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/risk.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/geography.php';
require_once __DIR__ . '/includes/awareness.php';
require_once __DIR__ . '/includes/views.php';

session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: index.php', true, 303);
    exit;
}

$locations = get_locations();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportType = (string)post('report_type', 'other');
    try {
        if (!isset(REPORT_TYPES[$reportType])) {
            throw new InvalidArgumentException('Choose a valid report type.');
        }
        $occurredAt = report_occurrence((string)post('occurred_at'));
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
        redirect('report.php');
    }
    $locationId = filter_var(post('location_id'), FILTER_VALIDATE_INT);
    $reportLat = post('report_lat');
    $reportLng = post('report_lng');
    $reportAddress = post('report_address');
    $message = post('message');
    $contactPhone = post('contact_phone');
    $contactEmail = post('contact_email');
    $houseLandmark = post('house_landmark');
    $reportAddress = strlen((string)$reportAddress) <= 255 ? $reportAddress : '';

    if (
        trim((string)$message) === '' || strlen((string)$message) > 65535 ||
        strlen((string)$contactEmail) > 254 || strlen((string)$contactPhone) > 20 ||
        trim((string)$contactPhone) === '' ||
        trim((string)$houseLandmark) === '' || strlen((string)$houseLandmark) > 255
    ) {
        flash('error', 'Please fill all required fields');
        redirect('report.php');
    }

    $location = null;
    if ($reportLat !== '' || $reportLng !== '') {
        $lat = filter_var($reportLat, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($reportLng, FILTER_VALIDATE_FLOAT);
        if (is_float($lat) && is_float($lng) && is_in_irisan($lat, $lng)) {
            $location = get_or_create_location(
                $lat,
                $lng,
                trim((string)$reportAddress) !== '' ? trim((string)$reportAddress) : trim((string)$houseLandmark)
            );
        }
    } elseif ($locationId) {
        $location = get_location((int)$locationId);
    }
    if (!$location || !$location['active']) {
        flash('error', 'Please select a location inside Barangay Irisan.');
        redirect('report.php');
    }

    try {
        create_report([
        'location_id' => $location['id'],
        'user_id' => $_SESSION['user_id'],
        'message' => $message,
        'contact_phone' => $contactPhone,
        'contact_email' => $contactEmail,
        'house_landmark' => $houseLandmark,
        'report_type' => $reportType, 'occurred_at' => $occurredAt
        ]);
    } catch (PDOException $error) {
        error_log('Report submission failed: ' . $error->getMessage());
        flash('error', 'Your report could not be saved. Please try again.');
        redirect('report.php');
    }

    flash('success', 'Thank you! Your report has been submitted.');
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Submit Report</title>
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
            height: clamp(380px, 55vh, 580px);
            overflow: hidden;
        }
        #report-map {
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
        .map-heading {
            position: absolute;
            z-index: 500;
            top: 1rem;
            left: 3.5rem;
            max-width: calc(100% - 4.5rem);
            pointer-events: none;
            background: rgba(255, 255, 255, 0.94);
            padding: 0.5rem 0.75rem;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }
        .map-heading h5,
        .map-heading p {
            margin: 0;
        }
        @media (max-width: 575.98px) {
            .map-surface {
                height: 380px;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/frontend.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/frontend.css') ?>">
</head>
<body>
    <?php $active = 'report'; require __DIR__ . '/partials/navbar.php'; ?>

    <div class="container-fluid px-3 px-lg-4 my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="page-heading"><h1 class="h2 mb-1">Submit ground report</h1><p class="page-subtitle mb-0">Share an observation within Barangay Irisan.</p></div>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger"><?= e($msg) ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card map-card">
                    <div class="map-surface">
                        <div id="report-map"></div>
                        <div class="map-heading">
                            <h5>Select Location</h5>
                            <p class="text-muted small">Click anywhere inside Barangay Irisan or choose a monitoring point.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Report Details</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">USER-SUBMITTED observations. Submission does not confirm a landslide or change the rainfall category.</p>
                        <form method="post" id="reportForm">
                            <div class="form-floating mb-3">
                                <select id="report_type" name="report_type" class="form-select" required>
                                    <option value="" selected disabled>Choose a condition</option>
                                    <?php foreach (REPORT_TYPES as $key=>$label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
                                </select>
                                <label for="report_type">Observed condition *</label>
                            </div>
                            <div class="mb-3"><label for="occurred_at" class="form-label">When did you observe it? (Philippine time)</label>
                            <input type="datetime-local" id="occurred_at" name="occurred_at" class="form-control"><small class="text-muted">Leave blank if unknown.</small></div>

                            <div class="mb-3">
                                <div class="form-floating">
                                    <select name="location_id" id="locationSelect" class="form-select" required aria-describedby="locationHelp selectedAddress">
                                    <option value="">Select a location...</option>
                                    <option id="mapPointOption" value="map-point" hidden>Selected map point</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>"
                                                data-lat="<?= $loc['lat'] ?? '' ?>"
                                                data-lng="<?= $loc['lng'] ?? '' ?>">
                                            <?= e($loc['name']) ?>
                                            <?= $loc['purok'] ? '(' . e($loc['purok']) . ')' : '' ?>
                                            <?= $loc['lat'] ? ' - ' . sprintf('%.5f, %.5f', $loc['lat'], $loc['lng']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                    </select>
                                    <label for="locationSelect">Location *</label>
                                </div>
                                <input type="hidden" name="report_lat" id="reportLat" value="">
                                <input type="hidden" name="report_lng" id="reportLng" value="">
                                <input type="hidden" name="report_address" id="reportAddress" value="">
                                <small id="locationHelp" class="form-text d-block">Choose an existing location or click inside the Irisan map to select a new point.</small>
                                <div class="report-location-status p-2 mt-2 small"><strong>Selected location:</strong> <span id="selectedAddress" aria-live="polite">Choose a point on the map or an existing location.</span></div>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" name="house_landmark" id="house_landmark" class="form-control"
                                        placeholder="House or landmark" maxlength="255" required>
                                <label for="house_landmark">House or nearby landmark *</label>
                            </div>

                            <div class="mb-3">
                                <div class="form-floating">
                                    <textarea name="message" id="message" class="form-control" style="height: 8rem"
                                              placeholder="Describe what you observed" required></textarea>
                                    <label for="message">Describe what you observed *</label>
                                </div>
                                <small class="text-muted">Be as specific as possible about what you observed.</small>
                            </div>

                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <div class="form-floating">
                                        <input type="tel" name="contact_phone" id="contact_phone" class="form-control" maxlength="16" pattern="\+?[0-9]{10,15}" autocomplete="tel" title="Use 10 to 15 digits, optionally starting with +."
                                               placeholder="Contact phone" value="<?= e($_SESSION['phone'] ?? '') ?>" required>
                                        <label for="contact_phone">Contact phone *</label>
                                    </div>
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <div class="form-floating">
                                        <input type="email" name="contact_email" id="contact_email" class="form-control" maxlength="254" autocomplete="email"
                                               placeholder="Contact email" value="<?= e($_SESSION['email'] ?? '') ?>">
                                        <label for="contact_email">Contact email</label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Submit Report
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.js"></script>
    <script src="assets/vendor/leaflet/leaflet.js"></script>
    <script src="assets/js/offline-map.js"></script>
    <script src="assets/js/location-address.js"></script>
    <script src="assets/js/irisan-boundary.js"></script>
    <script>
        const locations = <?= json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;

        const irisanBounds = L.latLngBounds(
            [16.407, 120.543],
            [16.435, 120.576]
        );

        const map = L.map('report-map', {
            zoomControl: true,
            zoomSnap: 0.25,
            scrollWheelZoom: true,
            maxBounds: irisanBounds.pad(0.3),
            maxBoundsViscosity: 1,
            minZoom: 12,
            maxZoom: 16
        }).fitBounds(irisanBounds, { padding: [32, 32], maxZoom: 14 });

        irisanTiles('assets/map-tiles/{z}/{x}/{y}.png', {
            attribution: 'Barangay Irisan offline map tiles; Address data © OpenStreetMap contributors',
            maxNativeZoom: 15,
            maxZoom: 16,
            minZoom: 12,
            tileSize: 256,
            noWrap: true
        }).addTo(map);

        new ResizeObserver(() => map.invalidateSize({ pan: false })).observe(document.getElementById('report-map'));
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

        locations.forEach(loc => {
            if (loc.lat && loc.lng) {
                const markerHtml = `<div class="marker-icon marker-color-unknown">?</div>`;
                const icon = L.divIcon({
                    html: markerHtml,
                    className: 'marker-div-icon',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                const marker = L.marker([loc.lat, loc.lng], { icon: icon });
                let popupContent = `<b>${String(loc.name).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}</b><br>`;
                popupContent += `<br><small>Click to select</small>`;
                marker.bindPopup(popupContent);
                marker.on('click', () => {
                    document.getElementById('locationSelect').value = loc.id;
                    document.getElementById('locationSelect').dispatchEvent(new Event('change'));
                });
                marker.locId = loc.id;
                markersLayer.addLayer(marker);
            }
        });

        map.addLayer(markersLayer);

        let selectedMarker = null;
        function lookupAddress(lat, lng) {
            const addressField = document.getElementById('reportAddress');
            const addressLabel = document.getElementById('selectedAddress');
            addressField.value = '';
            addressLabel.textContent = 'Looking up street address...';
            window.lookupLocationAddress(lat, lng).then(address => {
                if (Number(addressField.dataset.lat) !== lat || Number(addressField.dataset.lng) !== lng) return;
                addressField.value = address;
                addressLabel.textContent = address || 'Street address unavailable; use the house/landmark field below.';
            });
        }

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

            document.getElementById('reportLat').value = lat;
            document.getElementById('reportLng').value = lng;
            document.getElementById('reportAddress').dataset.lat = lat;
            document.getElementById('reportAddress').dataset.lng = lng;
            lookupAddress(lat, lng);
            const mapOption = document.getElementById('mapPointOption');
            mapOption.hidden = false;
            mapOption.textContent = `Selected map point (${lat.toFixed(5)}, ${lng.toFixed(5)})`;
            document.getElementById('locationSelect').value = 'map-point';
        });

        document.getElementById('locationSelect').addEventListener('change', function() {
            if (this.value === 'map-point') return;
            document.getElementById('mapPointOption').hidden = true;
            const selected = this.options[this.selectedIndex];
            if (selected && selected.dataset.lat && selected.dataset.lng) {
                const lat = parseFloat(selected.dataset.lat);
                const lng = parseFloat(selected.dataset.lng);
                document.getElementById('reportLat').value = lat;
                document.getElementById('reportLng').value = lng;
                document.getElementById('reportAddress').dataset.lat = lat;
                document.getElementById('reportAddress').dataset.lng = lng;
                lookupAddress(lat, lng);

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
            } else {
                if (selectedMarker) { map.removeLayer(selectedMarker); selectedMarker = null; }
                delete document.getElementById('reportAddress').dataset.lat;
                delete document.getElementById('reportAddress').dataset.lng;
                document.getElementById('reportLat').value = '';
                document.getElementById('reportLng').value = '';
                document.getElementById('reportAddress').value = '';
                document.getElementById('selectedAddress').textContent = 'Choose a point on the map or an existing location.';
            }
        });
    </script>
<footer class="container py-3 small site-footer">Weather data: <a href="https://open-meteo.com/" rel="noopener noreferrer">Open-Meteo</a> (CC BY 4.0). Map/address data where used: <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>. Manual updates; academic prototype.</footer>
</body>
</html>
