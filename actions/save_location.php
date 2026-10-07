<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/RiskAnalyzer.php';
require_once __DIR__ . '/../app/assessment.php';
require_once __DIR__ . '/../app/repositories.php';
require_once __DIR__ . '/../app/weather.php';
start_session();
require_login();

try {
    $locationId = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
    $address = trim((string)($_POST['address'] ?? ''));
    if (strlen($address) > 255) {
        throw new InvalidArgumentException('The selected address is too long.');
    }
    if ($locationId) {
        $location = get_location($locationId);
        if (!$location || !$location['active']) {
            throw new InvalidArgumentException('Unknown location.');
        }
        if ($address !== '' && $location['lat'] !== null && $location['lng'] !== null) {
            $location = get_or_create_location((float)$location['lat'], (float)$location['lng'], $address);
        }
    } else {
        $lat = filter_var($_POST['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_POST['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!is_float($lat) || !is_float($lng) || !is_in_irisan($lat, $lng)) {
            throw new InvalidArgumentException('Select a point inside Barangay Irisan.');
        }
        $location = get_or_create_location($lat, $lng, $address);
    }
    refresh_location((int)$location['id']);
    flash('success', 'Weather reading saved.');
    redirect('dashboard.php?location_id=' . $location['id']);
} catch (Throwable $error) {
    error_log('SmartSlope weather refresh: ' . $error->getMessage());
    flash('error', 'The location was selected, but current weather could not be saved. Try Refresh later.');
    if (isset($location)) {
        redirect('dashboard.php?location_id=' . $location['id']);
    }
    redirect('dashboard.php');
}
