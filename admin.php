<?php
require_once __DIR__ . '/app/config.php';
start_session();
require_admin();

$locations = get_locations();
$readings = get_all_readings(50);
$reports = get_reports();
$pendingCounts = get_pending_counts();

// Mutating actions require an administrator, POST, and a session-bound token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string)post('action');
    $id = filter_var(post('id'), FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        http_response_code(422);
        exit('Invalid record.');
    }
    if ($action === 'review' || $action === 'resolve') {
        update_report($id, $action === 'review' ? 'reviewed' : 'resolved', (int)$_SESSION['user_id']);
    } elseif ($action === 'delete_report') {
        delete_report($id);
    } elseif ($action === 'remove_location') {
        deactivate_location($id);
    } else {
        http_response_code(422);
        exit('Invalid action.');
    }
    flash('success', 'Changes saved.');
    redirect('admin.php');
}

if (get('action') === 'export' || get('action') === 'export_reports') {
    $reportsCsv = get('action') === 'export_reports';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . ($reportsCsv ? 'reports_' : 'readings_') . date('Y-m-d') . '.csv"');
    if ($reportsCsv) {
        $stream = fopen('php://output', 'w');
        fputcsv($stream, ['ID','Location','Reporter','Phone','Email','Message','Status','Submitted UTC']);
        foreach (get_reports() as $r) {
            fputcsv($stream, [$r['id'],csv_cell($r['location_name']),csv_cell($r['reporter_name']),
                csv_cell($r['contact_phone']),csv_cell($r['contact_email']),csv_cell($r['message']),
                $r['status'],$r['created_at']]);
        }
    } else {
        echo export_readings_csv(get_all_readings(PHP_INT_MAX));
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Admin</title>
    <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/vendor/leaflet/leaflet.css') ?>">
    <style>
        .badge-risk { font-size: 0.85em; }
        #reportMap { height: 300px; width: 100%; }
        .report-risk-icon { background: transparent; border: 0; }
        .report-risk-pin {
            display: block;
            padding: 0.35rem 0.55rem;
            border: 2px solid #fff;
            border-radius: 999px;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            text-align: center;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.45);
        }
        .report-risk-low { background: #198754; }
        .report-risk-normal { background: #0d6efd; }
        .report-risk-medium { background: #ffc107; color: #212529; }
        .report-risk-high { background: #dc3545; }
        .report-risk-unavailable { background: #6c757d; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= url() ?>">SmartSlope Admin</a>
            <div class="navbar-nav">
                <a class="nav-link" href="<?= url('dashboard.php') ?>">Dashboard</a>
                <a class="nav-link" href="<?= url('admin.php') ?>">Admin Panel</a>
                <a class="nav-link" href="<?= url('readings.php') ?>">All Readings</a>
                <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline"><?= csrf_field() ?><button class="nav-link btn btn-link" type="submit">Logout</button></form>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Admin Panel</h2>
            <div>
                <a href="<?= url('admin.php?action=export') ?>" class="btn btn-outline-success">
                    <i class="bi bi-download"></i> Export Readings CSV
                </a>
            </div>
        </div>

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>

        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger"><?= e($msg) ?></div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= count($locations) ?></h3>
                        <p class="text-muted mb-0 small">Locations</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= count($readings) ?></h3>
                        <p class="text-muted mb-0 small">Total Readings</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= count(array_filter($reports, fn($r) => $r['status'] === 'pending')) ?></h3>
                        <p class="text-muted mb-0 small">Pending Reports</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= count(array_filter($readings, fn($r) => $r['risk_level'] === 'high')) ?></h3>
                        <p class="text-muted mb-0 small">High Risk</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Reports Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>All Reports</h5><a class="btn btn-sm btn-outline-success" href="<?= e(url('admin.php?action=export_reports')) ?>">Download reports CSV</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
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
                            <?php foreach ($reports as $r):
                                $loc = get_location($r['location_id']);
                                $locationAddress = array_filter([
                                    $loc['name'] ?? 'Unknown',
                                    !empty($r['house_landmark']) ? 'Reported address: ' . $r['house_landmark'] : null,
                                    !empty($loc['landmark']) ? 'Street/Landmark: ' . $loc['landmark'] : null,
                                    !empty($loc['purok']) ? 'Purok ' . $loc['purok'] : null,
                                    'Barangay Irisan', 'Baguio City', 'Benguet', 'Philippines',
                                    !empty($loc['lat']) && !empty($loc['lng']) ? sprintf('Coordinates: %.5f, %.5f', $loc['lat'], $loc['lng']) : null
                                ]);
                                $reporterName = 'Anonymous';
                                if ($r['user_id']) {
                                    $pdo = db();
                                    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                                    $stmt->execute([$r['user_id']]);
                                    if ($user = $stmt->fetch()) {
                                        $reporterName = $user['full_name'];
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= $r['id'] ?></td>
                                <td>
                                    <?= e($loc['name'] ?? 'Unknown') ?>
                                    <?php if ($r['house_landmark'] ?? ''): ?><br><small class="text-muted">Reported address: <?= e($r['house_landmark']) ?></small><?php endif; ?>
                                    <?php if ($loc['landmark'] ?? ''): ?><br><small class="text-muted">Street/Landmark: <?= e($loc['landmark']) ?></small><?php endif; ?>
                                    <?php if ($loc['purok'] ?? ''): ?><br><small class="text-muted">Purok: <?= e($loc['purok']) ?></small><?php endif; ?>
                                    <br><small class="text-muted">Barangay Irisan, Baguio City, Benguet, Philippines</small>
                                    <?php if (!empty($loc['lat']) && !empty($loc['lng'])): ?><br><small class="text-muted">Coordinates: <?= sprintf('%.5f, %.5f', $loc['lat'], $loc['lng']) ?></small><?php endif; ?>
                                </td>
                                <td><?= e(substr($r['message'], 0, 40)) ?>...</td>
                                <td><?= e($reporterName) ?></td>
                                <td><?= e($r['contact_phone'] ?? 'N/A') ?><br><small><?= e($r['contact_email'] ?? '') ?></small></td>
                                <td>
                                    <span class="badge bg-<?=
                                        ['pending' => 'warning', 'reviewed' => 'info', 'resolved' => 'success'][$r['status']] ?? 'secondary'
                                    ?>">
                                        <?= ucfirst($r['status']) ?>
                                    </span>
                                </td>
                                <td><?= local_date($r['created_at']) ?></td>
                                <td>
                                    <button class="btn btn-xs btn-info view-report-btn"
                                            data-report-id="<?= $r['id'] ?>"
                                            data-location="<?= e(implode(', ', $locationAddress)) ?>"
                                            data-message="<?= e($r['message']) ?>"
                                            data-reporter="<?= e($reporterName) ?>"
                                            data-contact="<?= e(($r['contact_phone'] ?? 'N/A') . ' / ' . ($r['contact_email'] ?? 'No email')) ?>"
                                            data-status="<?= e($r['status']) ?>"
                                            data-date="<?= local_date($r['created_at']) ?>"
                                            data-lat="<?= $loc['lat'] ?? '' ?>"
                                            data-lng="<?= $loc['lng'] ?? '' ?>"
                                            data-risk="<?= e($r['location_risk_level'] ?? 'unavailable') ?>">
                                        View
                                    </button>
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-primary">Review</button></form>
                                    <?php endif; ?>
                                    <?php if ($r['status'] === 'reviewed'): ?>
                                        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-success">Resolve</button></form>
                                    <?php endif; ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this report?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_report"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($reports)): ?>
                                <tr><td colspan="8" class="text-center text-muted">No reports yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Locations Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>All Locations</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Purok</th>
                                <th>Coordinates</th>
                                <th>Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $loc): ?>
                            <tr class="<?= $loc['active'] ? '' : 'table-secondary' ?>">
                                <td><?= $loc['id'] ?></td>
                                <td><?= e($loc['name']) ?></td>
                                <td><?= e($loc['purok'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($loc['lat'] && $loc['lng']): ?>
                                        <?= sprintf('%.5f, %.5f', $loc['lat'], $loc['lng']) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $loc['active'] ? 'success' : 'secondary' ?>">
                                        <?= $loc['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Remove this location from active maps? Its history is kept.')"><?= csrf_field() ?><input type="hidden" name="action" value="remove_location"><input type="hidden" name="id" value="<?= (int)$loc['id'] ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($locations)): ?>
                                <tr><td colspan="6" class="text-center text-muted">No locations yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- All Readings Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>All Weather Readings</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Location</th>
                                <th>Risk</th>
                                <th>Rainfall (1h/24h/72h)</th>
                                <th>Forecast (24h)</th>
                                <th>Rain Chance</th>
                                <th>Soil Moisture (9-27 / 27-81 cm)</th>
                                <th>Temp</th>
                                <th>Observed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readings as $r):
                                $loc = get_location($r['location_id']);
                            ?>
                            <tr>
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
                                <td>
                                    <?= ($r['rainfall_1h'] ?? 'N/A') ?> /
                                    <?= ($r['rainfall_24h'] ?? 'N/A') ?> /
                                    <?= ($r['rainfall_72h'] ?? 'N/A') ?> mm
                                </td>
                                <td><?= $r['rainfall_forecast_24h'] ?? 'N/A' ?> mm</td>
                                <td><?= $r['precipitation_probability_24h'] ?? 'N/A' ?>%</td>
                                <td><?= $r['soil_moisture_9_27cm'] ?? 'N/A' ?> / <?= $r['soil_moisture_27_81cm'] ?? 'N/A' ?> m³/m³</td>
                                <td><?= $r['temperature'] ?? 'N/A' ?>°C</td>
                                <td><?= local_date($r['observed_at']) ?></td>
                                <td>
                                    <a href="<?= url('actions/save_reading.php?reading_id=' . $r['id']) ?>"
                                       class="btn btn-xs btn-outline-primary">Edit</a>
                                    <form method="post" action="<?= e(url('actions/delete_reading.php')) ?>" class="d-inline" onsubmit="return confirm('Remove this reading?')"><?= csrf_field() ?><input type="hidden" name="reading_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($readings)): ?>
                                <tr><td colspan="10" class="text-center text-muted">No readings yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Report Modal -->
    <div class="modal fade" id="viewReportModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">Report ID:</dt>
                                <dd class="col-sm-8" id="modalReportId"></dd>

                                <dt class="col-sm-4">Location:</dt>
                                <dd class="col-sm-8" id="modalReportLocation"></dd>

                                <dt class="col-sm-4">Risk status:</dt>
                                <dd class="col-sm-8" id="modalReportRisk"></dd>

                                <dt class="col-sm-4">Message:</dt>
                                <dd class="col-sm-8" id="modalReportMessage"></dd>

                                <dt class="col-sm-4">Reporter:</dt>
                                <dd class="col-sm-8" id="modalReportReporter"></dd>

                                <dt class="col-sm-4">Contact:</dt>
                                <dd class="col-sm-8" id="modalReportContact"></dd>

                                <dt class="col-sm-4">Status:</dt>
                                <dd class="col-sm-8" id="modalReportStatus"></dd>

                                <dt class="col-sm-4">Date:</dt>
                                <dd class="col-sm-8" id="modalReportDate"></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Report Location Map</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div id="reportMap"></div>
                                </div>
                            </div>
                            <input type="hidden" id="modalReportLat" value="">
                            <input type="hidden" id="modalReportLng" value="">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= url('assets/js/bootstrap.bundle.js') ?>"></script>
    <script src="<?= url('assets/vendor/leaflet/leaflet.js') ?>"></script>
    <script>
        const viewReportModal = document.getElementById('viewReportModal');
        document.querySelectorAll('.view-report-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reportId = this.getAttribute('data-report-id');
                const location = this.getAttribute('data-location');
                const message = this.getAttribute('data-message');
                const reporter = this.getAttribute('data-reporter');
                const contact = this.getAttribute('data-contact');
                const status = this.getAttribute('data-status');
                const date = this.getAttribute('data-date');
                const lat = this.getAttribute('data-lat');
                const lng = this.getAttribute('data-lng');
                const risk = this.getAttribute('data-risk');

                viewReportModal.querySelector('#modalReportId').textContent = reportId;
                viewReportModal.querySelector('#modalReportLocation').textContent = location;
                viewReportModal.querySelector('#modalReportRisk').textContent = risk.toUpperCase();
                viewReportModal.querySelector('#modalReportMessage').textContent = message;
                viewReportModal.querySelector('#modalReportReporter').textContent = reporter;
                viewReportModal.querySelector('#modalReportContact').textContent = contact;
                viewReportModal.querySelector('#modalReportStatus').textContent = status;
                viewReportModal.querySelector('#modalReportDate').textContent = date;
                viewReportModal.querySelector('#modalReportLat').value = lat;
                viewReportModal.querySelector('#modalReportLng').value = lng;

                const bootstrapModal = new bootstrap.Modal(viewReportModal);
                bootstrapModal.show();
            });
        });

        viewReportModal.addEventListener('shown.bs.modal', function() {
            const lat = parseFloat(viewReportModal.querySelector('#modalReportLat').value);
            const lng = parseFloat(viewReportModal.querySelector('#modalReportLng').value);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            const risk = viewReportModal.querySelector('#modalReportRisk').textContent.toLowerCase();
            const riskLabel = ['low', 'normal', 'medium', 'high'].includes(risk) ? risk : 'unavailable';
            const location = viewReportModal.querySelector('#modalReportLocation').textContent;

            if (!window.reportMap) {
                window.reportMap = L.map('reportMap').setView([lat, lng], 16);

                L.tileLayer('<?= e(url("assets/map-tiles/{z}/{x}/{y}.png")) ?>', {
                    attribution: 'Barangay Irisan offline map tiles',
                    maxNativeZoom: 15,
                    maxZoom: 16,
                    minZoom: 12,
                    tileSize: 256,
                    noWrap: true
                }).addTo(window.reportMap);
            }
            window.reportMap.setView([lat, lng], 16);
            window.reportMap.eachLayer(layer => {
                if (layer instanceof L.Marker) window.reportMap.removeLayer(layer);
            });
            const riskIcon = L.divIcon({
                html: `<span class="report-risk-pin report-risk-${riskLabel}">${riskLabel.toUpperCase()}</span>`,
                className: 'report-risk-icon',
                iconSize: [112, 34],
                iconAnchor: [56, 17]
            });
            const marker = L.marker([lat, lng], { icon: riskIcon }).addTo(window.reportMap);
            const popup = document.createElement('div');
            const address = document.createElement('div');
            address.textContent = location;
            const status = document.createElement('strong');
            status.textContent = 'Risk status: ' + riskLabel.toUpperCase();
            popup.append(address, status);
            marker.bindPopup(popup).openPopup();
            setTimeout(() => window.reportMap.invalidateSize(), 100);
        });

        viewReportModal.addEventListener('hidden.bs.modal', function() {
            if (window.reportMap) {
                window.reportMap.remove();
                window.reportMap = null;
            }
        });

    </script>
</body>
</html>
