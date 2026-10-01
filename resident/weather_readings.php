<section class="card" aria-labelledby="weather-history-heading">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h5 mb-0" id="weather-history-heading">Saved readings for the clicked location</h2>
        <div class="d-flex gap-2">
            <?php if ($mapIsAdmin): ?>
                <a class="btn btn-sm btn-outline-success disabled" id="readings-download"
                   data-download-base-url="<?= e(app_url('admin/download_readings.php')) ?>"
                   aria-disabled="true" tabindex="-1">Download saved readings CSV</a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-primary" id="weather-refresh" disabled>Refresh readings</button>
        </div>
    </div>
    <div class="card-body">
        <p id="weather-location-label" class="fw-semibold mb-1">Click a point inside Irisan.</p>
        <p id="weather-refresh-time" class="small text-muted" role="status">No saved reading logs yet.</p>
        <p class="small text-muted">A map click requests the latest Open-Meteo data and saves it. Refresh requests it again. Every successful click or refresh adds a saved log entry. Provider observation time and fetch time are shown separately. Times below are Philippine Standard Time.</p>
        <p id="current-reading-summary" class="small text-muted">Click a point to load its saved reading list.</p>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead><tr>
                    <th scope="col">Observed (PHT)</th>
                    <th scope="col">Temperature</th>
                    <th scope="col">Humidity</th>
                    <th scope="col">Precipitation</th>
                    <th scope="col">Rain / showers</th>
                    <th scope="col">Wind / gusts</th>
                    <th scope="col">Fetched (PHT)</th>
                    <th scope="col">Rainfall 1h / 24h / 72h</th>
                    <th scope="col">Risk at fetch</th>
                    <?php if ($mapIsAdmin): ?><th scope="col">Actions</th><?php endif; ?>
                </tr></thead>
                <tbody id="current-reading-body"><tr><td colspan="<?= $mapIsAdmin ? '10' : '9' ?>" class="text-muted">No saved readings loaded.</td></tr></tbody>
            </table>
        </div>
        <p class="small text-muted mb-0">Open-Meteo provides model estimates, not measurements from a physical device. The risk assessment uses stored hourly rainfall. Editing logged weather values does not alter that original assessment.</p>

        <?php if ($mapIsAdmin): ?>
            <hr>
            <?php if ($message = flash('reading_message')): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
            <?php if ($message = flash('reading_error')): ?><div class="alert alert-danger" role="alert"><?= e($message) ?></div><?php endif; ?>
            <div id="risk-reading-actions" data-save-url="<?= e(app_url('admin/save_reading.php')) ?>"
                 data-csrf-token="<?= e(csrf_token()) ?>"></div>
        <?php endif; ?>
    </div>
</section>
