<section class="card h-100 w-100" aria-labelledby="risk-heading">
    <div class="card-header"><h2 class="h5 mb-0" id="risk-heading">Latest location reading</h2></div>
    <div class="card-body">
        <p id="selected-location-name" class="fw-semibold">Select a marker on the map.</p>
        <input type="hidden" id="weather-csrf-token" value="<?= e(csrf_token()) ?>">
        <p id="reading-message" class="small text-muted" role="status" aria-live="polite">
            Select a location marker to load its latest saved reading.
        </p>
        <noscript>
            <p class="small text-danger">JavaScript is required to request and display live weather readings.</p>
        </noscript>
        <button class="btn btn-sm btn-outline-primary mb-3" type="button" id="weather-retry" hidden>Retry weather request</button>

        <div id="reading-panel" data-weather-url="<?= e(app_url('api/weather.php')) ?>"
             data-stored-url="<?= e(app_url('api/location_dashboard.php')) ?>">
            <section class="border rounded p-3 mb-3" aria-labelledby="risk-analysis-heading">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h3 class="h6 mb-0" id="risk-analysis-heading">Current area landslide-risk status</h3>
                    <span class="badge text-bg-secondary" id="risk-level" role="status" aria-live="polite">Not available</span>
                </div>
                <p id="active-alert" class="alert alert-warning" hidden role="alert"></p>
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
    </div>
</section>
