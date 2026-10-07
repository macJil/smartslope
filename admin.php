<?php
// Simple Admin Page
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ReportModel.php';
require_once __DIR__ . '/Location.php';

start_session();
require_admin();

// Initialize database and models
$database = new Database();
$pdo = $database->connect();

$report = new Report($pdo);
$location = new Location($pdo);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['update_report_status'])) {
        $reportId = (int)post('report_id');
        $status = post('status');
        $adminId = $_SESSION['user_id'];
        
        if ($report->updateStatus($reportId, $status, $adminId)) {
            flash('success', 'Report status updated successfully.');
        } else {
            flash('error', 'Failed to update report status.');
        }
        redirect('admin.php');
    }
    
    if (isset($_POST['delete_report'])) {
        $reportId = (int)post('report_id');
        
        if ($report->delete($reportId)) {
            flash('success', 'Report deleted successfully.');
        } else {
            flash('error', 'Failed to delete report.');
        }
        redirect('admin.php');
    }
    
    if (isset($_POST['deactivate_location'])) {
        $locationId = (int)post('location_id');
        
        if ($location->deactivate($locationId)) {
            flash('success', 'Location deactivated successfully.');
        } else {
            flash('error', 'Failed to deactivate location.');
        }
        redirect('admin.php');
    }
}

// Get data for admin dashboard
$reports = $report->getAll();
$locations = $location->getAllActive();
$pendingCounts = $report->getPendingCounts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Admin Dashboard</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/bootstrap.min.css')) ?>">
</head>
<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <main class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <aside class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mb-1">
                        Admin Menu
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="admin.php">Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php?view=locations">Locations</a>
                        </li>
                    </ul>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Admin Dashboard</h1>
                </div>
                
                <?php if ($msg = flash('success')): ?>
                    <div class="alert alert-success"><?= e($msg) ?></div>
                <?php endif; ?>
                
                <?php if ($msg = flash('error')): ?>
                    <div class="alert alert-danger"><?= e($msg) ?></div>
                <?php endif; ?>
                
                <!-- Reports Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Recent Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Location</th>
                                        <th>Reporter</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $r): 
                                        $locName = 'Unknown';
                                        foreach ($locations as $loc) {
                                            if ($loc['id'] == $r['location_id']) {
                                                $locName = $loc['name'];
                                                break;
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= e($r['event_id']) ?></td>
                                        <td><?= e($locName) ?></td>
                                        <td><?= e($r['reporter_name'] ?? 'Anonymous') ?></td>
                                        <td><?= e(substr($r['message'], 0, 50)) ?>...</td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $r['status'] === 'pending' ? 'warning' : 
                                                ($r['status'] === 'reviewed' ? 'info' : 'success') 
                                            ?>">
                                                <?= e(ucfirst($r['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($r['status'] === 'pending'): ?>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="report_id" value="<?= e($r['event_id']) ?>">
                                                <input type="hidden" name="update_report_status" value="1">
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="pending" selected>Pending</option>
                                                    <option value="reviewed">Reviewed</option>
                                                    <option value="resolved">Resolved</option>
                                                </select>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Locations Section -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Locations</h5>
                        <a href="#" class="btn btn-sm btn-outline-primary">Add Location</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Purok</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($locations as $loc): ?>
                                    <tr>
                                        <td><?= e($loc['id']) ?></td>
                                        <td><?= e($loc['name']) ?></td>
                                        <td><?= e($loc['purok'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $loc['active'] ? 'success' : 'secondary' ?>">
                                                <?= $loc['active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($loc['active']): ?>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="location_id" value="<?= e($loc['id']) ?>">
                                                <input type="hidden" name="deactivate_location" value="1">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                        onclick="return confirm('Deactivate this location?')">
                                                    Deactivate
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </main>
    
    <script src="<?= e(url('assets/js/bootstrap.bundle.js')) ?>"></script>
</body>
</html>
