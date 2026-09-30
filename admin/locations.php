<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
$locations = new LocationRepository($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        flash('location_message', 'Your session expired. Reload the page and try again.');
        redirect_to('admin/locations.php');
    }

    $action = post_string('action') ?: 'save';
    $locationId = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
    $invalidLocationId = array_key_exists('location_id', $_POST)
        && (!$locationId || $locationId < 1);

    if (in_array($action, ['archive', 'restore'], true) && $locationId && $locationId > 0) {
        if ($locations->setActive($locationId, $action === 'restore')) {
            flash('location_message', 'Location status updated.');
        } else {
            flash('location_message', 'The location could not be updated.');
        }
        redirect_to('admin/locations.php');
    }

    $name = trim(post_string('location_name'));
    $zone = trim(post_string('purok_zone'));
    $landmark = trim(post_string('landmark'));
    $latitudeRaw = trim(post_string('latitude'));
    $longitudeRaw = trim(post_string('longitude'));
    $nameLength = preg_match_all('/./us', $name);
    $zoneLength = $zone === '' ? 0 : preg_match_all('/./us', $zone);
    $landmarkLength = $landmark === '' ? 0 : preg_match_all('/./us', $landmark);
    $latitudeValid = $latitudeRaw === '' || preg_match('/\A-?\d{1,3}(?:\.\d{1,6})?\z/', $latitudeRaw) === 1;
    $longitudeValid = $longitudeRaw === '' || preg_match('/\A-?\d{1,3}(?:\.\d{1,6})?\z/', $longitudeRaw) === 1;
    $latitude = $latitudeRaw === '' ? null : (float) $latitudeRaw;
    $longitude = $longitudeRaw === '' ? null : (float) $longitudeRaw;

    if ($invalidLocationId || $nameLength === false || $nameLength < 1 || $nameLength > 150
        || $zoneLength === false || $zoneLength > 100
        || $landmarkLength === false || $landmarkLength > 255
        || !$latitudeValid || !$longitudeValid
        || (($latitude === null) !== ($longitude === null))
        || ($latitude !== null && ($latitude < -90 || $latitude > 90))
        || ($longitude !== null && ($longitude < -180 || $longitude > 180))) {
        flash('location_message', 'Check the location name, optional coordinates, and landmark.');
        redirect_to('admin/locations.php');
    }

    $existing = $locationId ? $locations->find((int) $locationId) : null;
    $data = [
        'location_name' => $name,
        'purok_zone' => $zone === '' ? null : $zone,
        'landmark' => $landmark === '' ? null : $landmark,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'susceptibility_class' => $existing['susceptibility_class'] ?? 'unknown',
        'hazard_source_name' => $existing['hazard_source_name'] ?? null,
        'hazard_source_url' => $existing['hazard_source_url'] ?? null,
        'hazard_source_date' => $existing['hazard_source_date'] ?? null,
    ];

    try {
        if ($locationId && $locationId > 0) {
            $saved = $locations->update((int) $locationId, $data);
            flash('location_message', $saved ? 'Location updated.' : 'The location was not found.');
        } else {
            $locations->create($data);
            flash('location_message', 'Location created.');
        }
    } catch (PDOException $exception) {
        error_log('SmartSlope location save failed: ' . $exception->getMessage());
        flash('location_message', $exception->getCode() === '23000'
            ? 'A location with those identifying details already exists.'
            : 'The location could not be saved.');
    } catch (RuntimeException $exception) {
        error_log('SmartSlope study area is unavailable: ' . $exception->getMessage());
        flash('location_message', 'The study barangay is missing or inactive. Check the database seed.');
    }
    redirect_to('admin/locations.php');
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editingLocation = $editId && $editId > 0 ? $locations->find((int) $editId) : null;
$locationRows = $locations->adminList();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage locations | SmartSlope</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
</head>
<body>
<header class="container py-3 d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0">Manage study locations</h1>
    <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/index.php')) ?>">Back to admin</a>
</header>
<main class="container pb-4">
    <?php if ($message = flash('location_message')): ?>
        <div class="alert alert-info" role="status"><?= e($message) ?></div>
    <?php endif; ?>
    <section class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0"><?= $editingLocation ? 'Edit location' : 'Add location' ?> — Barangay Irisan</h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(app_url('admin/locations.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <?php if ($editingLocation): ?><input type="hidden" name="location_id" value="<?= (int) $editingLocation['location_id'] ?>"><?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="location-name">Location name</label><input class="form-control" id="location-name" name="location_name" maxlength="150" value="<?= e($editingLocation['location_name'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label" for="purok-zone">Purok / zone</label><input class="form-control" id="purok-zone" name="purok_zone" maxlength="100" value="<?= e($editingLocation['purok_zone'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="landmark">Landmark</label><input class="form-control" id="landmark" name="landmark" maxlength="255" value="<?= e($editingLocation['landmark'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label" for="latitude">Latitude</label><input class="form-control" id="latitude" name="latitude" inputmode="decimal" value="<?= e($editingLocation['latitude'] ?? '') ?>"></div>
                    <div class="col-md-3"><label class="form-label" for="longitude">Longitude</label><input class="form-control" id="longitude" name="longitude" inputmode="decimal" value="<?= e($editingLocation['longitude'] ?? '') ?>"></div>
                </div>
                <div class="mt-3 d-flex gap-2"><button class="btn btn-primary" type="submit" name="action" value="save"><?= $editingLocation ? 'Save changes' : 'Add location' ?></button>
                    <?php if ($editingLocation): ?><a class="btn btn-outline-secondary" href="<?= e(app_url('admin/locations.php')) ?>">Cancel</a><?php endif; ?></div>
            </form>
        </div>
    </section>
    <section class="card">
        <div class="card-header"><h2 class="h5 mb-0">Locations</h2></div>
        <div class="table-responsive"><table class="table table-striped align-middle mb-0">
            <thead><tr><th>Location</th><th>Coordinates</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($locationRows as $location): ?>
                <tr>
                    <td><?= e($location['location_name']) ?><?= $location['is_active'] ? '' : ' (archived)' ?><?php if ($location['purok_zone']): ?><br><small><?= e($location['purok_zone']) ?></small><?php endif; ?></td>
                    <td><?= $location['latitude'] !== null ? e($location['latitude']) . ', ' . e($location['longitude']) : 'Not set' ?></td>
                    <td>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(app_url('admin/locations.php?edit=' . ((int) $location['location_id']))) ?>">Update</a>
                            <form method="post" action="<?= e(app_url('admin/locations.php')) ?>" >
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="location_id" value="<?= (int) $location['location_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="<?= $location['is_active'] ? 'archive' : 'restore' ?>"><?= $location['is_active'] ? 'Archive' : 'Restore' ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$locationRows): ?><tr><td colspan="3" class="text-center">No locations are set up yet.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</main>
</body>
</html>