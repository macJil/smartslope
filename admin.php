<?php
require_once __DIR__ . '/app/config.php';
start_session();
require_admin();

$locations = get_locations();
$readings = get_all_readings(50);
$reports = get_reports();
$pendingCounts = get_pending_counts();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update reading risk level
    if (isset($_POST['update_reading'])) {
        $readingId = (int)post('reading_id');
        $riskLevel = post('risk_level');
        
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE events SET risk_level = ? WHERE id = ? AND type = 'reading'");
        $stmt->execute([$riskLevel, $readingId]);
        flash('success', 'Reading risk level updated successfully');
        redirect('admin.php');
    }
}

// Update report status
if (get('action') === 'review' && get('report_id')) {
    update_report((int)get('report_id'), 'reviewed', (int)$_SESSION['user_id']);
    flash('success', 'Report marked as reviewed');
    redirect('admin.php');
}

if (get('action') === 'resolve' && get('report_id')) {
    update_report((int)get('report_id'), 'resolved', (int)$_SESSION['user_id']);
    flash('success', 'Report marked as resolved');
    redirect('admin.php');
}

if (get('action') === 'delete' && get('report_id')) {
    delete_report((int)get('report_id'));
    flash('success', 'Report deleted');
    redirect('admin.php');
}

// Delete reading
if (get('action') === 'delete_reading' && get('reading_id')) {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND type = 'reading'");
    $stmt->execute([(int)get('reading_id')]);
    flash('success', 'Reading deleted');
    redirect('admin.php');
}

// Export readings CSV
if (get('action') === 'export') {
    $allReadings = get_all_readings(1000);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="readings_'.date('Y-m-d').'.csv"');
    echo export_readings_csv($allReadings);
    exit;
}

// Delete location
if (get('action') === 'delete_location' && get('location_id')) {
    delete_location((int)get('location_id'));
    flash('success', 'Location deleted successfully');
    redirect('admin.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Admin</title>
    <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
    <style>
        .badge-risk { font-size: 0.85em; }
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
                <a class="nav-link" href="<?= url('logout.php') ?>">Logout</a>
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
                <h5>All Reports</h5>
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
                                <td><?= e($loc['name'] ?? 'Unknown') ?></td>
                                <td><?= e(substr($r['message'], 0, 40)) ?>...</td>
                                <td><?= e($reporterName) ?></td>
                                <td><?= e($r['contact_phone'] ?? 'N/A') ?></td>
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
                                            data-location="<?= e($loc['name'] ?? 'Unknown') ?>"
                                            data-message="<?= e($r['message']) ?>"
                                            data-reporter="<?= e($reporterName) ?>"
                                            data-contact="<?= e($r['contact_phone'] ?? 'N/A') ?>"
                                            data-status="<?= e($r['status']) ?>"
                                            data-date="<?= local_date($r['created_at']) ?>">
                                        View
                                    </button>
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <a href="<?= url('admin.php?action=review&report_id=' . $r['id']) ?>" 
                                           class="btn btn-xs btn-primary">Review</a>
                                    <?php endif; ?>
                                    <?php if ($r['status'] === 'reviewed'): ?>
                                        <a href="<?= url('admin.php?action=resolve&report_id=' . $r['id']) ?>" 
                                           class="btn btn-xs btn-success">Resolve</a>
                                    <?php endif; ?>
                                    <a href="<?= url('admin.php?action=delete&report_id=' . $r['id']) ?>" 
                                       class="btn btn-xs btn-danger" 
                                       onclick="return confirm('Delete this report? This cannot be undone!')">Delete</a>
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
                                    <a href="<?= url('admin.php?action=delete_location&location_id=' . $loc['id']) ?>" 
                                       class="btn btn-xs btn-outline-danger"
                                       onclick="return confirm('Delete this location? This cannot be undone!')">Delete</a>
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
                                <td><?= e($loc['name'] ?? 'Unknown') ?></td>
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
                                    <a href="<?= url('save_reading.php?reading_id=' . $r['id']) ?>" 
                                       class="btn btn-xs btn-outline-primary">Edit</a>
                                    <a href="<?= url('delete_reading.php?reading_id=' . $r['id'] . '&from=admin.php') ?>" 
                                       class="btn btn-xs btn-outline-danger"
                                       onclick="return confirm('Delete this reading? This cannot be undone!')">Delete</a>
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <dl class="row">
                        <dt class="col-sm-3">Report ID:</dt>
                        <dd class="col-sm-9" id="modalReportId"></dd>
                        
                        <dt class="col-sm-3">Location:</dt>
                        <dd class="col-sm-9" id="modalReportLocation"></dd>
                        
                        <dt class="col-sm-3">Message:</dt>
                        <dd class="col-sm-9" id="modalReportMessage"></dd>
                        
                        <dt class="col-sm-3">Reporter:</dt>
                        <dd class="col-sm-9" id="modalReportReporter"></dd>
                        
                        <dt class="col-sm-3">Contact:</dt>
                        <dd class="col-sm-9" id="modalReportContact"></dd>
                        
                        <dt class="col-sm-3">Status:</dt>
                        <dd class="col-sm-9" id="modalReportStatus"></dd>
                        
                        <dt class="col-sm-3">Date:</dt>
                        <dd class="col-sm-9" id="modalReportDate"></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Reading Modal -->
    <div class="modal fade" id="editReadingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="reading_id" id="modalReadingId">
                    <input type="hidden" name="update_reading" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Reading Risk Level</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Risk Level</label>
                            <select name="risk_level" id="modalRiskLevel" class="form-select" required>
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= url('assets/js/bootstrap.bundle.min.js') ?>"></script>
    <script>
        // View Report modal
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
                
                viewReportModal.querySelector('#modalReportId').textContent = reportId;
                viewReportModal.querySelector('#modalReportLocation').textContent = location;
                viewReportModal.querySelector('#modalReportMessage').textContent = message;
                viewReportModal.querySelector('#modalReportReporter').textContent = reporter;
                viewReportModal.querySelector('#modalReportContact').textContent = contact;
                viewReportModal.querySelector('#modalReportStatus').textContent = status;
                viewReportModal.querySelector('#modalReportDate').textContent = date;
                
                const bootstrapModal = new bootstrap.Modal(viewReportModal);
                bootstrapModal.show();
            });
        });
        
        // Edit reading modal
        const editModal = document.getElementById('editReadingModal');
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const readingId = button.getAttribute('data-reading-id');
            const riskLevel = button.getAttribute('data-risk-level');
            
            const modalReadingId = editModal.querySelector('#modalReadingId');
            const modalRiskLevel = editModal.querySelector('#modalRiskLevel');
            
            modalReadingId.value = readingId;
            modalRiskLevel.value = riskLevel;
        });
    </script>
</body>
</html>
