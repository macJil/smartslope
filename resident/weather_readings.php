<section class="card" aria-labelledby="weather-history-heading">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h5 mb-0" id="weather-history-heading">Current readings dashboard</h2>
        <button type="button" class="btn btn-sm btn-primary" id="weather-refresh" disabled>Refresh readings</button>
    </div>
    <div class="card-body">
        <p id="weather-location-label" class="fw-semibold">Select a marker on the map.</p>
        <p id="weather-refresh-time" class="small text-muted" role="status">Not refreshed yet.</p>
        <p class="small text-muted">All times use Philippine Standard Time (Asia/Manila, UTC+08:00). Weather data time is the provider’s observation time; refreshed time is when SmartSlope fetched it. Hourly rows retain their original hour.</p>
            <div>
                <dl class="row mb-0">
                    <dt class="col-sm-6">Temperature</dt><dd class="col-sm-6" id="weather-temperature">—</dd>
                    <dt class="col-sm-6">Feels like</dt><dd class="col-sm-6" id="weather-apparent-temperature">—</dd>
                    <dt class="col-sm-6">Relative humidity</dt><dd class="col-sm-6" id="weather-humidity">—</dd>
                    <dt class="col-sm-6">Precipitation (API interval)</dt><dd class="col-sm-6" id="weather-precipitation">—</dd>
                    <dt class="col-sm-6">Rain / showers</dt><dd class="col-sm-6" id="weather-rain-showers">—</dd>
                    <dt class="col-sm-6">Wind speed / gusts</dt><dd class="col-sm-6" id="weather-wind">—</dd>
                    <dt class="col-sm-6">Wind direction</dt><dd class="col-sm-6" id="weather-wind-direction">—</dd>
                    <dt class="col-sm-6">Cloud cover</dt><dd class="col-sm-6" id="weather-cloud-cover">—</dd>
                    <dt class="col-sm-6">Weather code (WMO)</dt><dd class="col-sm-6" id="weather-code">—</dd>
                    <dt class="col-sm-6">Weather data time</dt><dd class="col-sm-6" id="weather-current-time">—</dd>
                </dl>
            </div>
        <p class="small text-muted mt-2">Weather data: <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">Open-Meteo</a>, CC BY 4.0. These are model estimates rather than local hardware measurements.</p>

        <hr>
        <h3 class="h6">Saved hourly observations</h3>
        <p id="weather-hourly-summary" class="text-muted">Choose a map marker to load up to 72 saved hourly observations.</p>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Time (Baguio)</th>
                        <th scope="col">Temp (°C)</th>
                        <th scope="col">Humidity (%)</th>
                        <th scope="col">Feels like (°C)</th>
                        <th scope="col">Precipitation (mm)</th>
                        <th scope="col">Rain (mm)</th>
                        <th scope="col">Showers (mm)</th>
                        <th scope="col">Wind (km/h)</th>
                        <th scope="col">Wind direction (°)</th>
                        <th scope="col">Gusts (km/h)</th>
                        <th scope="col">Cloud cover (%)</th>
                        <th scope="col">Pressure MSL (hPa)</th>
                        <th scope="col">Surface pressure (hPa)</th>
                        <th scope="col">WMO code</th>
                    </tr>
                </thead>
                <tbody id="weather-hourly-body">
                    <tr><td colspan="14" class="text-center text-muted">No weather readings loaded.</td></tr>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mt-3 mb-0">The history contains provider hourly model values for the last 72 hours. Missing values are shown as an em dash. Open-Meteo current conditions are model-based estimates and may differ from ground measurements.</p>
    </div>
</section>
