<?php
require_once __DIR__ . '/../app/config.php';
start_session();
require_login();

$locations = get_locations();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $locationId = (int)post('location_id');
    $message = post('message');
    $contactPhone = post('contact_phone');
    $contactEmail = post('contact_email');
    $houseLandmark = post('house_landmark');

    $location = get_location($locationId);
    if (!$location || !$location['active'] || trim((string)$message) === '' ||
        !preg_match('/^\+?[0-9]{10,15}$/', trim((string)$contactPhone)) ||
        ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) ||
        trim((string)$houseLandmark) === '') {
        flash('error', 'Please fill all required fields');
        redirect('report.php');
    }

    create_report([
        'location_id' => $locationId,
        'user_id' => $_SESSION['user_id'],
        'message' => $message,
        'contact_phone' => $contactPhone,
        'contact_email' => $contactEmail,
        'house_landmark' => $houseLandmark
    ]);

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
        .marker-color-low { background: #28a745; }
        .marker-color-normal { background: #007bff; }
        .marker-color-medium { background: #ffc107; color: #000; }
        .marker-color-high { background: #dc3545; }
        .marker-color-unknown { background: #6c757d; }
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
        .map-heading {
            position: absolute;
            z-index: 500;
            top: 1rem;
            left: 1rem;
            max-width: calc(100% - 2rem);
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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= url() ?>">SmartSlope</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?= url('dashboard.php') ?>">Dashboard</a>
                <a class="nav-link" href="<?= url('report.php') ?>">Submit Report</a>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline"><?= csrf_field() ?><button class="nav-link btn btn-link" type="submit">Logout</button></form>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-3 px-lg-4 my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Submit Ground Report</h2>
            <a href="<?= url('dashboard.php') ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
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
                            <p class="text-muted small">Click a monitoring point or use the dropdown.</p>
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
                        <form method="post" id="reportForm">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label">Location *</label>
                                <select name="location_id" id="locationSelect" class="form-select" required>
                                    <option value="">Select a location...</option>
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
                            </div>

                            <div class="mb-3">
                                <label class="form-label">House/Landmark *</label>
                                <input type="text" name="house_landmark" class="form-control"
                                       placeholder="Your address or nearby landmark" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message *</label>
                                <textarea name="message" class="form-control" rows="4"
                                          placeholder="Describe the landslide risk or condition..." required></textarea>
                                <small class="text-muted">Be as specific as possible about what you observed.</small>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Contact Phone *</label>
                                    <input type="tel" name="contact_phone" class="form-control"
                                           value="<?= e($_SESSION['phone'] ?? '') ?>" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" name="contact_email" class="form-control"
                                           value="<?= e($_SESSION['email'] ?? '') ?>">
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

    <script src="<?= url('assets/js/bootstrap.bundle.js') ?>"></script>
    <script src="<?= url('assets/vendor/leaflet/leaflet.js') ?>"></script>
    <script src="<?= e(url('assets/js/irisan-boundary.js')) ?>"></script>
    <script>
        const locations = <?= json_encode($locations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;

        const irisanBounds = L.latLngBounds(
            [16.407, 120.543],
            [16.435, 120.576]
        );

        const map = L.map('report-map', {
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

        new ResizeObserver(() => map.invalidateSize({ pan: false })).observe(document.getElementById('report-map'));
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
                        document.getElementById('locationSelect').value = loc.id;
                        document.getElementById('locationSelect').dispatchEvent(new Event('change'));
                        found = true;
                    }
                }
            });

            if (!found) {
                alert('No monitoring location near this point. Please use the dropdown.');
            }
        });

        document.getElementById('locationSelect').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            if (selected && selected.dataset.lat && selected.dataset.lng) {
                const lat = parseFloat(selected.dataset.lat);
                const lng = parseFloat(selected.dataset.lng);

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
            }
        });
    </script>
</body>
</html>
