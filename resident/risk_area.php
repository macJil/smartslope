<section class="card h-100 w-100" aria-labelledby="risk-heading">
    <div class="card-header"><h2 class="h5 mb-0" id="risk-heading">Latest location reading</h2></div>
    <div class="card-body">
        <label class="form-label" for="risk-location-select">Study area location</label>
        <select class="form-select mb-3" id="risk-location-select" data-weather-url="<?= e(app_url('api/weather.php')) ?>">
            <option value="" <?= $defaultWeatherLocationId === '' ? 'selected' : '' ?>>Choose a location</option>
            <?php foreach ($riskLocations as $location): ?>
                <?php
                $weatherReady = is_numeric($location['latitude']) && is_numeric($location['longitude'])
                    && (float) $location['latitude'] >= -90 && (float) $location['latitude'] <= 90
                    && (float) $location['longitude'] >= -180 && (float) $location['longitude'] <= 180;
                $selected = $weatherReady && (string) $location['location_id'] === $defaultWeatherLocationId;
                ?>
                <option value="<?= (int) $location['location_id'] ?>" <?= $selected ? 'selected' : '' ?> <?= $weatherReady ? '' : 'disabled' ?>>
                    <?= e($location['location_name']) ?><?= $location['purok_zone'] ? ' — ' . e($location['purok_zone']) : '' ?><?= $weatherReady ? '' : ' (coordinates needed)' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" id="weather-csrf-token" value="<?= e(csrf_token()) ?>">
        <p id="reading-message" class="small text-muted" role="status" aria-live="polite">
            <?php if (!$riskLocations): ?>
                No active Irisan locations are in the database yet. Add one under Manage locations.
            <?php elseif ($defaultWeatherLocationId === ''): ?>
                No active location has valid coordinates. An admin must enter latitude and longitude before weather can load.
            <?php else: ?>
                Loading the latest weather readings for the selected location…
            <?php endif; ?>
        </p>
        <noscript>
            <p class="small text-danger">JavaScript is required to request and display live weather readings.</p>
        </noscript>
        <button class="btn btn-sm btn-outline-primary mb-3" type="button" id="weather-retry" hidden>Retry weather request</button>

        <div id="reading-panel">
            <section class="border rounded p-3 mb-3" aria-labelledby="risk-analysis-heading">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h3 class="h6 mb-0" id="risk-analysis-heading">Current area landslide-risk status</h3>
                    <span class="badge text-bg-secondary" id="risk-level" role="status" aria-live="polite">Not available</span>
                </div>
                <p id="risk-explanation" class="small">Waiting for complete rainfall data.</p>
                <dl class="row mb-2">
                    <dt class="col-sm-6">Rainfall, latest hour</dt><dd class="col-sm-6" id="rainfall-1h">—</dd>
                    <dt class="col-sm-6">Rainfall, 24 hours</dt><dd class="col-sm-6" id="rainfall-24h">—</dd>
                    <dt class="col-sm-6">Rainfall, 72 hours</dt><dd class="col-sm-6" id="rainfall-72h">—</dd>
                    <dt class="col-sm-6">Latest hourly timestamp</dt><dd class="col-sm-6" id="reading-observed-at">—</dd>
                </dl>
                <p class="small text-muted mb-0">This is a prototype indicator, not an official landslide warning.</p>
            </section>


        </div>
        <?php if (!$riskLocations): ?>
            <p class="mb-0">No active locations are available in the study area.</p>
        <?php elseif (!array_filter($riskLocations, static fn(array $location): bool => $location['latitude'] !== null && $location['longitude'] !== null)): ?>
            <p class="small mb-0">No active location has coordinates yet. An admin must add verified latitude and longitude under Manage locations before weather readings and risk analysis can load.</p>
        <?php elseif (array_filter($riskLocations, static fn(array $location): bool => $location['latitude'] === null || $location['longitude'] === null)): ?>
            <p class="small mb-0">Locations marked “coordinates needed” cannot load weather readings until an admin adds verified latitude and longitude.</p>
        <?php endif; ?>
    </div>
</section>
