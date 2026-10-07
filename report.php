<?php
// Simple Report Submission Page
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Location.php';
require_once __DIR__ . '/ReportModel.php';

start_session();
require_login();

// Initialize database and models
$database = new Database();
$pdo = $database->connect();

$location = new Location($pdo);
$report = new Report($pdo);

$locations = $location->getAllActive();

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    
    $data = [
        'location_id' => (int)post('location_id'),
        'user_id' => $_SESSION['user_id'],
        'message' => trim(post('message')),
        'contact_phone' => trim(post('contact_phone')),
        'contact_email' => trim(post('contact_email')),
        'house_landmark' => trim(post('house_landmark')),
        'report_type' => post('report_type', 'other'),
        'occurred_at' => post('occurred_at')
    ];
    
    // Simple validation
    if ($data['location_id'] <= 0) {
        flash('error', 'Please select a location.');
    } elseif ($data['message'] === '') {
        flash('error', 'Please enter a message.');
    } else {
        try {
            $report->create($data);
            flash('success', 'Your report has been submitted successfully!');
            redirect('report.php');
        } catch (PDOException $e) {
            error_log('Report submission failed: ' . $e->getMessage());
            flash('error', 'Failed to submit report. Please try again.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Report - SmartSlope</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/bootstrap.min.css')) ?>">
</head>
<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2 mb-0">Submit Observation Report</h1>
                </div>
                
                <?php if ($msg = flash('success')): ?>
                    <div class="alert alert-success"><?= e($msg) ?></div>
                <?php endif; ?>
                
                <?php if ($msg = flash('error')): ?>
                    <div class="alert alert-danger"><?= e($msg) ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="submit_report" value="1">
                            
                            <div class="mb-3">
                                <label for="location_id" class="form-label">Location *</label>
                                <select class="form-select" id="location_id" name="location_id" required>
                                    <option value="">Select a location...</option>
                                    <?php foreach ($locations as $loc): ?>
                                    <option value="<?= e($loc['id']) ?>">
                                        <?= e($loc['name']) ?> <?= e($loc['purok'] ? '(' . $loc['purok'] . ')' : '') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="report_type">
                                    <option value="landslide">Landslide Signs</option>
                                    <option value="erosion">Erosion</option>
                                    <option value="crack">Ground Cracks</option>
                                    <option value="water">Water Saturation</option>
                                    <option value="other" selected>Other</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Description *</label>
                                <textarea class="form-control" id="message" name="message" rows="4" 
                                          placeholder="Describe what you observed..." required></textarea>
                                <div class="form-text">Please provide as much detail as possible.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="contact_phone" class="form-label">Contact Phone *</label>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone"
                                           pattern="\+?[0-9]{10,15}" placeholder="+639123456789" 
                                           value="<?= e($_SESSION['phone'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="contact_email" class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email"
                                           placeholder="your@email.com"
                                           value="<?= e($_SESSION['email'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="house_landmark" class="form-label">House/Landmark</label>
                                <input type="text" class="form-control" id="house_landmark" name="house_landmark"
                                       placeholder="Near the church, beside the river, etc.">
                            </div>
                            
                            <div class="mb-3">
                                <label for="occurred_at" class="form-label">When did this occur?</label>
                                <input type="datetime-local" class="form-control" id="occurred_at" name="occurred_at">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    Submit Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="mt-4 text-muted small">
                    <p><strong>Note:</strong> All reports are reviewed by administrators. 
                       For immediate emergencies, contact local authorities directly.</p>
                </div>
            </div>
        </div>
    </main>
    
    <script src="<?= e(url('assets/js/bootstrap.bundle.js')) ?>"></script>
</body>
</html>