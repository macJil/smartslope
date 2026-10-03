<?php
require_once __DIR__ . '/app/bootstrap.php';
start_session();
require_admin();

$reportFilter = (string)get('status', post('return_status'));
$reportFilter = in_array($reportFilter, ['pending','reviewed','resolved'],true) ? $reportFilter : null;
$reportReturn = 'admin.php' . ($reportFilter ? '?status='.$reportFilter : '') . '#reports';

// Mutating actions require an administrator, POST, and a session-bound token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string)post('action');
    try {
    if ($action === 'complete_setup') {
        migrate_awareness_schema(); flash('success','Database setup completed. Review and reading edits are ready.'); redirect('admin.php');
    }
    if ($action === 'import_locations') {
        $file=$_FILES['locations_csv']??[];
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'') || strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION))!=='csv' || ($file['size']??0)>5*1024*1024) throw new InvalidArgumentException('Invalid/not applicable file. Choose a downloaded Locations CSV (maximum 5 MB).');
        $rows=parse_locations_csv((string)file_get_contents($file['tmp_name']));
        $result=import_locations_csv($rows);
        flash('success','Locations imported: '.$result['added'].' added, '.$result['updated'].' restored/updated, '.$result['unchanged'].' unchanged.'); redirect('admin.php#locations');
    }
    $bulkActions = [
        'bulk_delete_reports' => ['field' => 'report_ids', 'operation' => 'delete_report', 'label' => 'reports deleted'],
        'bulk_archive_readings' => ['field' => 'reading_ids', 'operation' => 'archive_reading', 'label' => 'readings removed from active lists'],
        'bulk_remove_locations' => ['field' => 'location_ids', 'operation' => 'deactivate_location', 'label' => 'locations removed; history retained'],
    ];
    if (isset($bulkActions[$action])) {
        $bulkAction = $bulkActions[$action];
        $selectedIds = $_POST[$bulkAction['field']] ?? [];
        if (!is_array($selectedIds)) $selectedIds = [];
        $selectedIds = array_unique(array_filter(array_map(
            static fn($value) => filter_var($value, FILTER_VALIDATE_INT),
            $selectedIds
        ), static fn($id) => $id !== false && $id > 0));
        if (!$selectedIds) {
            flash('error', 'Select at least one item first.');
            $returnTo = post('return_to', 'admin.php');
            redirect(in_array($returnTo, ['admin.php', 'dashboard.php', 'readings.php'], true) ? $returnTo : 'admin.php');
        }

        $changed = 0;
        $pdo = db();
        $pdo->beginTransaction();
        try {
        foreach ($selectedIds as $selectedId) {
            $changed += match ($bulkAction['operation']) {
                'delete_report' => delete_report((int)$selectedId),
                'archive_reading' => archive_reading((int)$selectedId),
                'deactivate_location' => deactivate_location((int)$selectedId),
            } ? 1 : 0;
        }
        $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Bulk operation failed: ' . $error->getMessage());
            flash('error', 'No selected changes were saved. Please try again.');
            redirect('admin.php');
        }
        flash('success', $changed . ' ' . $bulkAction['label'] . '.');
        $returnTo = post('return_to', 'admin.php');
        redirect(in_array($returnTo, ['admin.php', 'dashboard.php', 'readings.php'], true) ? $returnTo : 'admin.php');
    }

    $id = filter_var(post('id'), FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        http_response_code(422);
        exit('Invalid record.');
    }
    if ($action === 'review' || $action === 'resolve') {
        if (!update_report($id, $action === 'review' ? 'reviewed' : 'resolved', (int)$_SESSION['user_id'])) throw new InvalidArgumentException('No report changed. It may already have been updated. Reload and check its current status.');
    } elseif ($action === 'delete_report') {
        delete_report($id);
    } elseif ($action === 'remove_location') {
        deactivate_location($id);
    } else {
        http_response_code(422);
        exit('Invalid action.');
    }
    flash('success', 'Changes saved.');
    redirect($reportReturn);
    } catch (Throwable $error) {
        error_log('Admin action failed: '.$error->getMessage());
        flash('error', $error instanceof PDOException ? 'Changes could not be saved. Complete database setup if shown below, then try again.' : $error->getMessage());
        redirect($action === 'import_locations' ? 'admin.php#locations' : $reportReturn);
    }
}

$exportAction=(string)get('action');
if (in_array($exportAction,['export','export_reports','export_locations'],true)) {
    try {
        $csv=match($exportAction) {
            'export_reports'=>export_reports_csv(get_reports($reportFilter)),
            'export_locations'=>export_locations_csv(get_locations()),
            default=>export_readings_csv(get_all_readings(PHP_INT_MAX)),
        };
        $kind=match($exportAction) {'export_reports'=>'reports','export_locations'=>'locations',default=>'readings'};
        download_csv('smartslope_'.$kind.'_'.gmdate('Y-m-d').'.csv',$csv);
    } catch (Throwable $error) {
        error_log('CSV export failed: '.$error->getMessage());
        flash('error','CSV export failed: '.($error instanceof PDOException ? 'Database could not be read.' : $error->getMessage())); redirect('admin.php');
    }
}
$missingColumns=missing_awareness_columns();
$locations = get_locations();
$uiLocations = array_column($locations, null, 'id');
$readings = get_all_readings(50);
$reports = get_reports($reportFilter);
$pendingCounts = get_pending_counts();
$reportedAddressesByLocation = [];
foreach ($reports as $report) {
    $locationId = (int)$report['location_id'];
    $address = trim((string)($report['house_landmark'] ?? ''));
    if ($address !== '' && !isset($reportedAddressesByLocation[$locationId])) {
        $reportedAddressesByLocation[$locationId] = $address;
    }
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
        #reportMap { height: 420px; width: 100%; }
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
        .report-risk-low { background: var(--slope-green); }
        .report-risk-normal { background: #0d6efd; }
        .report-risk-medium { background: #ffc107; color: #212529; }
        .report-risk-high { background: #dc3545; }
        .report-risk-unavailable { background: #6c757d; }
        .report-message { white-space: pre-wrap; overflow-wrap: anywhere; min-height: 6rem; }
    </style>
    <link rel="stylesheet" href="<?= e(url('assets/css/frontend.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/assets/css/frontend.css') ?>">
</head>
<body>
    <?= ui_navigation('admin') ?>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="page-heading"><h1 class="h2 mb-1">Admin panel</h1><p class="page-subtitle mb-0">Review community reports, locations, and saved readings.</p></div>
            <div>
                <a href="<?= url('admin.php?action=export') ?>" class="btn btn-outline-success">
                    Export readings CSV
                </a>
            </div>
        </div>

        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>

        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger"><?= e($msg) ?></div>
        <?php endif; ?>

        <?php if ($missingColumns): ?>
        <div class="alert alert-warning" role="alert"><h3 class="h6">Database setup is incomplete</h3><p>The previous awareness upgrade needs additional fields for report review and reading edits. Back up your database, then complete setup. Existing records are retained.</p>
        <form method="post" action="<?= e(url('admin.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="complete_setup"><button class="btn btn-warning" type="submit">Complete database setup</button></form></div>
        <?php endif; ?>
        <p class="small text-muted">Reading counts cover the latest 50 saved readings across active locations. Current means observed within <?= e(round($config['freshness_seconds'] / 3600, 2)) ?> hours; these are reading counts, not location counts.</p>
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
                        <p class="text-muted mb-0 small">Readings shown (latest 50)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= array_sum($pendingCounts) ?></h3>
                        <p class="text-muted mb-0 small">Pending Reports</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="mb-0"><?= ui_current_count($readings, 'high') ?></h3>
                        <p class="text-muted mb-0 small">Current high readings</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Reports Section -->
        <div class="card mb-4" id="reports">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><h5 class="mb-0">Community report review queue</h5><small>USER-SUBMITTED; workflow status does not establish scientific verification.</small>
                <nav class="d-flex flex-wrap gap-2 mt-2" aria-label="Filter reports">
                <?php foreach ([''=>'All','pending'=>'Pending','reviewed'=>'Reviewed','resolved'=>'Resolved'] as $value=>$label): ?>
                <a class="btn btn-sm <?= ($reportFilter??'')===$value?'btn-primary':'btn-outline-primary' ?>" <?= ($reportFilter??'')===$value?'aria-current="page"':'' ?> href="<?= e(url('admin.php'.($value!==''?'?status='.$value:'').'#reports')) ?>"><?= e($label) ?></a>
                <?php endforeach; ?></nav><p class="small mb-0 mt-2"><?= count($reports) ?> <?= e($reportFilter??'total') ?> report(s)</p></div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <form id="bulkReportsForm" method="post" data-bulk-confirm="Delete %d selected report(s)?" class="m-0">
                        <?= csrf_field() ?><input type="hidden" name="action" value="bulk_delete_reports"><input type="hidden" name="return_to" value="admin.php">
                        <button class="btn btn-sm btn-outline-danger">Delete selected</button>
                    </form>
                    <a class="btn btn-sm btn-outline-success" href="<?= e(url('admin.php?action=export_reports'.($reportFilter?'&status='.$reportFilter:''))) ?>">Download reports CSV</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable records table">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><span class="visually-hidden">Select</span><input type="checkbox" data-select-all="reports" aria-label="Select all reports"></th>
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
                                $locationAddress = trim((string)($loc['landmark'] ?? ''));
                                if ($locationAddress === '') $locationAddress = trim((string)($r['house_landmark'] ?? ''));
                                if ($locationAddress === '') $locationAddress = $loc['name'] ?? 'Unknown';
                                $locationCoordinates = !empty($loc['lat']) && !empty($loc['lng'])
                                    ? sprintf('Coordinates: %.5f, %.5f', $loc['lat'], $loc['lng'])
                                    : 'Coordinates unavailable';
                                $reportMapLocation = $locationAddress . ' | ' . $locationCoordinates;
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
                                <td><input type="checkbox" form="bulkReportsForm" name="report_ids[]" value="<?= (int)$r['id'] ?>" data-bulk-item="reports" aria-label="Select report <?= (int)$r['id'] ?>"></td>
                                <td>
                                    <strong><?= e($locationAddress) ?></strong>
                                    <br><small class="text-muted"><?= e($locationCoordinates) ?></small>
                                </td>
                                <td><strong><?= e(REPORT_TYPES[$r['report_type'] ?? 'other'] ?? 'Legacy observation') ?></strong><br><?= e(substr($r['message'], 0, 40)) ?>...<br><small>Occurred: <?= e(ui_time($r['occurred_at'] ?? null)) ?><br>Last review: <?= e(ui_time($r['reviewed_at'] ?? null)) ?></small></td>
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
                                    <button type="button" class="btn btn-sm btn-info view-report-btn"
                                            data-report-id="<?= $r['id'] ?>"
                                            data-location="<?= e($reportMapLocation) ?>"
                                            data-message="<?= e($r['message']) ?>"
                                            data-reporter="<?= e($reporterName) ?>"
                                            data-contact="<?= e(($r['contact_phone'] ?? 'N/A') . ' / ' . ($r['contact_email'] ?? 'No email')) ?>"
                                            data-status="<?= e($r['status']) ?>"
                                            data-date="<?= local_date($r['created_at']) ?>"
                                            data-lat="<?= e($r['lat'] ?? '') ?>"
                                            data-lng="<?= e($r['lng'] ?? '') ?>"
                                            data-risk="<?= e($r['location_risk_level'] ?? 'unavailable') ?>"
                                            data-assessment="<?= e(ucfirst($r['location_assessment']['data_status']) . ' — last saved category: ' . ($r['location_assessment']['category'] ?? 'unavailable')) ?>">
                                        View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($reports)): ?>
                                <tr><td colspan="8" class="text-center text-muted">No <?= e($reportFilter??'') ?> reports found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Locations Section -->
        <div class="card mb-4" id="locations">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">All Locations</h5>
                <a class="btn btn-sm btn-outline-success" href="<?= e(url('admin.php?action=export_locations')) ?>">Download locations CSV</a>
                <form method="post" action="<?= e(url('admin.php')) ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                    <?= csrf_field() ?><input type="hidden" name="action" value="import_locations">
                    <label for="locations_csv" class="visually-hidden">Locations CSV to import</label>
                    <input type="file" class="form-control form-control-sm w-auto" id="locations_csv" name="locations_csv" accept=".csv,text/csv" required>
                    <button class="btn btn-sm btn-primary" type="submit">Import locations CSV</button>
                    <small class="w-100 text-muted">Use an unchanged Locations CSV downloaded here. Import restores its location details and active status; existing readings and reports stay linked.</small>
                </form>
                <form id="bulkLocationsForm" method="post" data-bulk-confirm="Remove %d selected location(s)? Their reading and report history will be kept." class="m-0">
                    <?= csrf_field() ?><input type="hidden" name="action" value="bulk_remove_locations"><input type="hidden" name="return_to" value="admin.php">
                    <button class="btn btn-sm btn-outline-danger">Remove selected</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable records table">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><input type="checkbox" data-select-all="locations" aria-label="Select all locations"></th>
                                <th>Location address</th>
                                <th>Coordinates</th>
                                <th>Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $loc):
                                $locationAddress = trim((string)($loc['landmark'] ?? ''));
                                if ($locationAddress === '') {
                                    $locationAddress = $reportedAddressesByLocation[(int)$loc['id']] ?? '';
                                }
                                if ($locationAddress === '') {
                                    $storedName = trim((string)($loc['name'] ?? ''));
                                    $isGeneratedName = preg_match('/^Irisan\s+-?\d+(?:\.\d+)?,\s*-?\d+(?:\.\d+)?$/i', $storedName);
                                    $locationAddress = $isGeneratedName
                                        ? 'Barangay Irisan, Baguio City, Benguet, Philippines'
                                        : ($storedName !== '' ? $storedName : 'Address unavailable');
                                }
                            ?>
                            <tr class="<?= $loc['active'] ? '' : 'table-secondary' ?>">
                                <td><input type="checkbox" form="bulkLocationsForm" name="location_ids[]" value="<?= (int)$loc['id'] ?>" data-bulk-item="locations" aria-label="Select location <?= (int)$loc['id'] ?>"></td>
                                <td><?= e($locationAddress) ?></td>
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
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($locations)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No locations yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- All Readings Section -->
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">All Weather Readings</h5><a class="btn btn-sm btn-outline-success" href="<?= e(url('admin.php?action=export')) ?>">Download readings CSV</a>
                <form id="bulkAdminReadingsForm" method="post" data-bulk-confirm="Remove %d reading(s) from active lists? They will be archived." class="m-0">
                    <?= csrf_field() ?><input type="hidden" name="action" value="bulk_archive_readings"><input type="hidden" name="return_to" value="admin.php">
                    <button class="btn btn-sm btn-outline-danger">Remove selected</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable records table">
                    <table class="table table-hover mb-0">
                        <?= ui_readings_head(true, 'admin-readings') ?>
                        <tbody>
                            <?= ui_readings_rows($readings, true, 'admin-readings', 'bulkAdminReadingsForm', $uiLocations) ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?= ui_weather_modal() ?>
    <!-- View Report Modal -->
    <div class="modal fade" id="viewReportModal" tabindex="-1" aria-labelledby="reportModalTitle">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalTitle">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close report details"></button>
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
                    <section class="mt-3">
                        <h6>Full report message</h6>
                        <div id="modalReportMessage" class="report-message border rounded p-3 bg-light"></div>
                    </section>
                </div>
                <div class="modal-footer">
                    <form method="post" action="<?= e(url('admin.php')) ?>" id="modalReportAction" hidden>
                        <?= csrf_field() ?><input type="hidden" name="id" id="modalReportActionId">
                        <input type="hidden" name="action" id="modalReportActionName">
                        <input type="hidden" name="return_status" value="<?= e($reportFilter??'') ?>">
                        <button type="submit" class="btn btn-primary" id="modalReportActionButton">Mark as reviewed</button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= url('assets/js/bootstrap.bundle.js') ?>"></script>
    <script src="<?= e(url('assets/js/reading-modal.js')) ?>"></script>
    <script src="<?= url('assets/vendor/leaflet/leaflet.js') ?>"></script>
    <script src="<?= e(url('assets/js/offline-map.js')) ?>"></script>
    <script src="<?= e(url('assets/js/bulk-select.js')) ?>"></script>
    <script>
        let reportMapInstance = null;
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
                viewReportModal.querySelector('#modalReportRisk').textContent = risk.toUpperCase() + ' (' + this.getAttribute('data-assessment') + ')';
                viewReportModal.dataset.risk = risk;
                viewReportModal.querySelector('#modalReportMessage').textContent = message;
                viewReportModal.querySelector('#modalReportReporter').textContent = reporter;
                viewReportModal.querySelector('#modalReportContact').textContent = contact;
                viewReportModal.querySelector('#modalReportStatus').textContent = status;
                viewReportModal.querySelector('#modalReportDate').textContent = date;
                viewReportModal.querySelector('#modalReportLat').value = lat;
                viewReportModal.querySelector('#modalReportLng').value = lng;

                const actionForm = document.getElementById('modalReportAction');
                actionForm.hidden = !['pending', 'reviewed'].includes(status);
                document.getElementById('modalReportActionId').value = reportId;
                document.getElementById('modalReportActionName').value = status === 'pending' ? 'review' : 'resolve';
                document.getElementById('modalReportActionButton').textContent = status === 'pending' ? 'Mark as reviewed' : 'Mark as resolved';
                const bootstrapModal = bootstrap.Modal.getOrCreateInstance(viewReportModal);
                bootstrapModal.show();
            });
        });

        viewReportModal.addEventListener('shown.bs.modal', function() {
            const lat = parseFloat(viewReportModal.querySelector('#modalReportLat').value);
            const lng = parseFloat(viewReportModal.querySelector('#modalReportLng').value);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            const risk = viewReportModal.dataset.risk || 'unavailable';
            const riskLabel = ['low', 'normal', 'medium', 'high'].includes(risk) ? risk : 'unavailable';
            const location = viewReportModal.querySelector('#modalReportLocation').textContent;

            if (!reportMapInstance) {
                reportMapInstance = L.map('reportMap', { minZoom: 12, maxZoom: 16, zoomSnap: 0.25 });

                irisanTiles('<?= e(url("assets/map-tiles/{z}/{x}/{y}.png")) ?>', {
                    attribution: 'Barangay Irisan offline map tiles',
                    maxNativeZoom: 15,
                    maxZoom: 16,
                    minZoom: 12,
                    tileSize: 256,
                    noWrap: true
                }).addTo(reportMapInstance);
            }
            reportMapInstance.invalidateSize();
            reportMapInstance.fitBounds([[16.407, 120.543], [16.435, 120.576]], {padding: [24, 24], maxZoom: 14});
            const currentMap = reportMapInstance;
            fetch('<?= e(url("assets/map/irisan.geojson")) ?>')
                .then(response => { if (!response.ok) throw new Error('Boundary unavailable'); return response.json(); })
                .then(data => {
                    if (reportMapInstance !== currentMap) return;
                    L.geoJSON(data, {interactive: false, style: {color: '#705139', weight: 3, fillOpacity: 0.08}}).addTo(currentMap);
                }).catch(() => { /* Marker and local tiles remain usable. */ });
            reportMapInstance.eachLayer(layer => {
                if (layer instanceof L.Marker) reportMapInstance.removeLayer(layer);
            });
            const riskIcon = L.divIcon({
                html: `<span class="report-risk-pin report-risk-${riskLabel}">${riskLabel.toUpperCase()}</span>`,
                className: 'report-risk-icon',
                iconSize: [112, 34],
                iconAnchor: [56, 17]
            });
            const marker = L.marker([lat, lng], { icon: riskIcon }).addTo(reportMapInstance);
            const popup = document.createElement('div');
            const address = document.createElement('div');
            address.textContent = location;
            const status = document.createElement('strong');
            status.textContent = 'Risk status: ' + riskLabel.toUpperCase();
            popup.append(address, status);
            marker.bindPopup(popup, {autoPan: false}).openPopup();
            requestAnimationFrame(() => { if (reportMapInstance) reportMapInstance.invalidateSize(); });
        });

        viewReportModal.addEventListener('hidden.bs.modal', function() {
            if (reportMapInstance) {
                reportMapInstance.remove();
                reportMapInstance = null;
            }
        });

    </script>
<footer class="container py-3 small site-footer">Weather data: <a href="https://open-meteo.com/" rel="noopener noreferrer">Open-Meteo</a> (CC BY 4.0). Map/address data where used: <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>. Manual updates; academic prototype.</footer>
</body>
</html>
