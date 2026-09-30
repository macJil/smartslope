<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$readingRepository = new ReadingRepository($pdo);
$locationRepository = new LocationRepository($pdo);
$readingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $readingId = filter_var($_POST['reading_id'] ?? null, FILTER_VALIDATE_INT);
}

if ($readingId === false || ($readingId !== null && $readingId < 0)) {
    flash('reading_message', 'Invalid reading ID.');
    redirect_to('admin/readings.php');
}
$isNew = !$readingId;
$reading = $isNew ? [
    'location_id' => 0, 'source_name' => '', 'source_url' => '',
    'observed_at' => gmdate('Y-m-d H:i:s'), 'is_archived' => 0,
] : $readingRepository->find((int) $readingId);
if ($reading === null || (int) $reading['is_archived'] === 1) {
    flash('reading_message', 'Choose an active reading to edit.');
    redirect_to('admin/readings.php');
}

$locations = $locationRepository->activeForStudyArea();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $message = 'Your session expired. Reload the page and try again.';
    } else {
        $locationId = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
        $activeLocationIds = array_map(
            static fn(array $location): int => (int) $location['location_id'],
            $locations
        );
        $locationAllowed = $locationId && $locationId > 0
            && in_array((int) $locationId, $activeLocationIds, true);

        $rainfall = [];
        $rainfallValid = true;
        $hasRainfall = false;
        foreach (['rainfall_1h_mm', 'rainfall_24h_mm', 'rainfall_72h_mm'] as $field) {
            $raw = trim(post_string($field));
            if ($raw === '') {
                $rainfall[$field] = null;
            } elseif (preg_match('/\A\d{1,5}(?:\.\d{1,2})?\z/', $raw) === 1
                && (float) $raw <= 99999.99) {
                $rainfall[$field] = (float) $raw;
                $hasRainfall = true;
            } else {
                $rainfallValid = false;
                $rainfall[$field] = null;
            }
        }

        $observedInput = trim(post_string('observed_at'));
        $observedLocal = DateTimeImmutable::createFromFormat(
            '!Y-m-d\\TH:i:s',
            $observedInput,
            new DateTimeZone('Asia/Manila')
        );
        $dateErrors = DateTimeImmutable::getLastErrors();
        $observedValid = $observedLocal !== false
            && ($dateErrors === false
                || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
            && $observedLocal->format('Y-m-d\\TH:i:s') === $observedInput;

        $sourceName = $isNew ? trim(post_string('source_name')) : $reading['source_name'];
        $sourceUrl = $isNew ? trim(post_string('source_url')) : ($reading['source_url'] ?? '');
        $sourceValid = $sourceName !== '' && strlen($sourceName) <= 150
            && strlen($sourceUrl) <= 500 && ($sourceUrl === '' ||
                (filter_var($sourceUrl, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($sourceUrl, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)));
        if (!$sourceValid || !$locationAllowed || !$rainfallValid || in_array(null, $rainfall, true) || !$hasRainfall || !$observedValid) {
            $message = 'Check the source, location, all three rainfall totals, and observation time.';
        } else {
            $readingData = $rainfall + [
                'location_id' => (int) $locationId,
                'source_name' => $sourceName,
                'source_url' => $sourceUrl === '' ? null : $sourceUrl,
                'observed_at' => $observedLocal
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s'),
            ];

            try {
                if ($isNew) {
                    $readingRepository->create($readingData, (int) $_SESSION['user_id']);
                    flash('reading_message', 'Reading created.');
                    redirect_to('admin/readings.php');
                }
                if ($readingRepository->update((int) $readingId, $readingData)) {
                    flash('reading_message', 'Reading updated.');
                    redirect_to('admin/readings.php');
                }
                $message = 'The reading could not be updated.';
            } catch (PDOException $exception) {
                error_log('SmartSlope reading save failed: ' . $exception->getMessage());
                $message = $exception->getCode() === '23000'
                    ? 'A reading with this location, observation time, and source already exists.'
                    : 'The reading could not be updated.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['location_id', 'rainfall_1h_mm', 'rainfall_24h_mm', 'rainfall_72h_mm'] as $field) {
        $reading[$field] = post_string($field);
    }
}
$observedAtLocal = (new DateTimeImmutable($reading['observed_at'], new DateTimeZone('UTC')))
    ->setTimezone(new DateTimeZone('Asia/Manila'))
    ->format('Y-m-d\\TH:i:s');
if ($_SERVER['REQUEST_METHOD'] === 'POST') $observedAtLocal = post_string('observed_at');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reading | SmartSlope</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/bootstrap.min.css')) ?>">
</head>
<body>
<header class="container py-3 d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0"><?= $isNew ? 'Add reading' : 'Edit reading' ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/readings.php')) ?>">Back to readings</a>
</header>
<main class="container pb-4">
    <?php if ($message !== null): ?>
        <div class="alert alert-danger" role="alert"><?= e($message) ?></div>
    <?php endif; ?>
    <section class="card">
        <div class="card-body">
            <p><strong>Source:</strong> <?= e($reading['source_name']) ?><?php if ($reading['source_url']): ?><br><span class="small text-muted">Source URL: <?= e($reading['source_url']) ?></span><?php endif; ?></p>
            <form method="post" action="<?= e(app_url('admin/reading.php?id=' . ((int) $readingId))) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="reading_id" value="<?= (int) $readingId ?>">
                <?php if ($isNew): ?>
                <div class="mb-3"><label for="source-name" class="form-label">Source name</label><input id="source-name" class="form-control" name="source_name" maxlength="150" required value="<?= e(post_string('source_name')) ?>"></div>
                <div class="mb-3"><label for="source-url" class="form-label">Source URL (optional)</label><input id="source-url" class="form-control" name="source_url" type="url" maxlength="500" value="<?= e(post_string('source_url')) ?>"></div>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="reading-location">Location</label>
                        <select class="form-select" id="reading-location" name="location_id" required>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= (int) $location['location_id'] ?>" <?= (int) $reading['location_id'] === (int) $location['location_id'] ? 'selected' : '' ?>>
                                    <?= e($location['location_name']) ?><?= $location['purok_zone'] ? ' — ' . e($location['purok_zone']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="observed-at">Observed at (Baguio local time)</label>
                        <input class="form-control" type="datetime-local" step="1" id="observed-at" name="observed_at" value="<?= e($observedAtLocal) ?>" required>
                    </div>
                    <?php foreach ([
                        'rainfall_1h_mm' => ['Rainfall, 1 hour (mm)', 'rainfall-1h'],
                        'rainfall_24h_mm' => ['Rainfall, 24 hours (mm)', 'rainfall-24h'],
                        'rainfall_72h_mm' => ['Rainfall, 72 hours (mm)', 'rainfall-72h'],
                    ] as $field => [$label, $id]): ?>
                        <div class="col-md-4">
                            <label class="form-label" for="<?= e($id) ?>"><?= e($label) ?></label>
                            <input class="form-control" type="number" min="0" max="99999.99" step="0.01" required id="<?= e($id) ?>" name="<?= e($field) ?>" value="<?= e($reading[$field] ?? '') ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save changes</button>
                    <a class="btn btn-outline-secondary" href="<?= e(app_url('admin/readings.php')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>