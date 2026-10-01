<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST'); http_response_code(405); exit('Method not allowed.');
}
$locationId = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
$id = filter_var($_POST['observation_id'] ?? null, FILTER_VALIDATE_INT);
$returnPath = 'admin/index.php';
if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    flash('reading_error', 'Reload the dashboard and try again.'); redirect_to($returnPath);
}
$action = post_string('action');
if (!$locationId || $locationId < 1 || !$id || $id < 1
    || !in_array($action, ['current_update','current_delete'], true)) {
    flash('reading_error', 'Invalid saved reading action.'); redirect_to($returnPath);
}
try {
    $repo = new ReadingRepository($pdo);
    $pdo->beginTransaction();
    if ($action === 'current_update') {
        $risk = post_string('risk_level');
        $saved = $repo->updateCurrent((int)$locationId, (int)$id, $risk);
    } else {
        $saved = $repo->deleteCurrent((int)$locationId, (int)$id);
    }
    if (!$saved) throw new InvalidArgumentException('Invalid location, risk or saved row.');
    $pdo->commit();
    flash('reading_message', $action === 'current_update'
        ? 'Saved risk at fetch. Original API measurements and live analysis are retained.'
        : 'Reading removed from the saved list.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('SmartSlope reading review failed: ' . $exception->getMessage());
    flash('reading_error', 'Could not save the reading. Choose a valid risk level for an existing saved reading.');
}
redirect_to($returnPath);
