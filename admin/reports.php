<section class="card h-100 w-100">
    <div class="card-header"><h3 class="h5 mb-0">Resident reports</h3></div>
    <div class="card-body">
        <a class="btn btn-outline-success mb-3" href="<?= e(app_url('admin/download_reports.php')) ?>">Download all reports CSV</a>
        <?php if ($message = flash('admin_report_message')): ?>
            <div class="alert alert-info" role="status"><?= e($message) ?></div>
        <?php endif; ?>
        <form method="get" action="<?= e(app_url('admin/index.php')) ?>" class="row g-2 mb-3">
            <div class="col-auto">
                <label class="form-label" for="report-status-filter">Filter by status</label>
                <select class="form-select" id="report-status-filter" name="status">
                    <option value="" <?= $adminReportFilter === '' ? 'selected' : '' ?>>All reports</option>
                    <option value="pending" <?= $adminReportFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="reviewed" <?= $adminReportFilter === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                    <option value="resolved" <?= $adminReportFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                </select>
            </div>
            <div class="col-auto align-self-end"><button class="btn btn-secondary" type="submit">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Reporter</th><th>Location</th><th>Observation</th>
                        <th>Status</th><th>Submitted</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adminReports as $report): ?>
                        <tr>
                            <td>
                                <?= e($report['reporter_name'] ?: 'Anonymous') ?>
                                <?php if ($report['reporter_email']): ?><br><small>Email: <?= e($report['reporter_email']) ?></small><?php endif; ?>
                                <?php if ($report['reporter_contact_number']): ?><br><small>Phone: <?= e($report['reporter_contact_number']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?= e($report['location_name']) ?>
                                <?php if ($report['purok_zone']): ?><br><small><?= e($report['purok_zone']) ?></small><?php endif; ?>
                                <?php if ($report['house_landmark']): ?><br><small>Near: <?= e($report['house_landmark']) ?></small><?php endif; ?>
                            </td>
                            <td><?= nl2br(e($report['message'])) ?></td>
                            <td><?= e(ucfirst($report['status'])) ?></td>
                            <td><?= e(display_local_datetime($report['created_at'])) ?></td>
                            <td>
                                <?php if ($report['status'] !== 'resolved'): ?>
                                    <form action="<?= e(app_url('admin/update_report.php')) ?>" method="post" class="d-flex gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="report_id" value="<?= (int) $report['report_id'] ?>">
                                        <select name="status" class="form-select" aria-label="New report status">
                                            <option value="reviewed" <?= $report['status'] === 'reviewed' ? 'selected' : '' ?>>Reviewed</option>
                                            <option value="resolved">Resolved</option>
                                        </select>
                                        <button class="btn btn-primary" type="submit">Save</button>
                                    </form>
                                <?php else: ?>
                                    <?= $report['reviewed_at'] ? 'Reviewed ' . e(display_local_datetime($report['reviewed_at'])) : 'Resolved' ?>
                                <?php endif; ?>
                                <form action="<?= e(app_url('admin/delete_report.php')) ?>" method="post" class="mt-2">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="report_id" value="<?= (int)$report['report_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete report</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$adminReports): ?>
                        <tr><td colspan="6" class="text-center">No reports have been submitted.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
