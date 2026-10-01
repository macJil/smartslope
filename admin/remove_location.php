<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST'); http_response_code(405); exit('Method not allowed.');
}
$id = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
if (!csrf_is_valid($_POST['csrf_token'] ?? null) || !$id || $id < 1) {
    flash('map_message', 'Reload the page and choose a saved map point.');
    redirect_to('admin/index.php');
}
try {
    $saved = (new LocationRepository($pdo))->setActive((int)$id, false);
    flash('map_message', $saved ? 'Point removed from the map. Saved history is retained.' : 'Point not found.');
} catch (PDOException $exception) {
    error_log('SmartSlope map removal failed: ' . $exception->getMessage());
    flash('map_message', 'The map point could not be removed.');
}
redirect_to('admin/index.php');
