<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/config.php';
start_session();
require_login();
require_post_csrf();

try {
    $locationId = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
    if ($locationId) {
        $location = get_location($locationId);
        if (!$location || !$location['active']) throw new InvalidArgumentException('Unknown location.');
    } else {
        $lat = filter_var($_POST['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_POST['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!is_float($lat) || !is_float($lng) || !is_in_irisan($lat, $lng)) {
            throw new InvalidArgumentException('Select a point inside Barangay Irisan.');
        }
        $location = get_or_create_location($lat, $lng);
    }
    refresh_location((int)$location['id']);
    flash('success', 'Weather reading saved.');
    redirect('dashboard.php?location_id=' . $location['id']);
} catch (Throwable $error) {
    error_log('SmartSlope weather refresh: ' . $error->getMessage());
    flash('error', 'The location was selected, but current weather could not be saved. Try Refresh later.');
    if (isset($location)) redirect('dashboard.php?location_id=' . $location['id']);
    redirect('dashboard.php');
}
