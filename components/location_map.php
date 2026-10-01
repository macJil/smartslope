<?php
// Parent page supplies $mapLocations and $mapIsAdmin after authorization.
$mapReadings=new ReadingRepository($pdo);
$pendingCounts=$mapIsAdmin ? (new ReportRepository($pdo))->pendingCountsByLocation() : [];
$mapMarkers=[];
foreach ($mapLocations as $place) {
    if ($place['latitude']===null || $place['longitude']===null) continue;
    $latest=$mapReadings->latestForActiveLocation((int)$place['location_id']);
    $age=$latest ? time()-(new DateTimeImmutable($latest['observed_at'],new DateTimeZone('UTC')))->getTimestamp() : null;
    $mapMarkers[]=[
        'location_id'=>(int)$place['location_id'],
        'location_name'=>$place['location_name'],
        'purok_zone'=>$place['purok_zone'],
        'latitude'=>(float)$place['latitude'],
        'longitude'=>(float)$place['longitude'],
        'risk_level'=>$latest['risk_level'] ?? null,
        'stale'=>$age===null || $age>7200 || $age< -600,
        'pending_count'=>$pendingCounts[(int)$place['location_id']] ?? 0,
    ];
}
?>
<section class="card" aria-labelledby="map-heading">
    <div class="card-header"><h2 class="h5 mb-0" id="map-heading">Barangay Irisan location map</h2></div>
    <div class="card-body">
        <?php if ($mapIsAdmin): ?>
            <?php if ($notice = flash('map_message')): ?><p class="alert alert-info" role="status"><?= e($notice) ?></p><?php endif; ?>
            <form action="<?= e(app_url('admin/remove_location.php')) ?>" method="post" class="d-flex flex-wrap gap-2 mb-3">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="remove-map-location" class="align-self-center">Saved map point</label>
                <select id="remove-map-location" name="location_id" class="form-select w-auto" required>
                    <option value="">Choose a saved point</option>
                    <?php foreach ($mapLocations as $savedPoint): ?>
                        <option value="<?= (int)$savedPoint['location_id'] ?>"><?= e($savedPoint['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-danger" type="submit">Remove from map</button>
            </form>
            <p class="small text-muted">Removing a point hides its marker and retains its saved readings and reports. Clicking the same coordinates again reactivates it.</p>
        <?php endif; ?>
        <p class="small text-muted">Click inside the Irisan outline to save and load weather for that coordinate. The blue pin marks the point used for the weather request. The outline is a reference boundary, not a hazard classification.</p>
        <div id="location-map" class="smartslope-map" role="region" aria-label="Select a monitored location on the Irisan map"
             data-boundary-url="<?= e(app_url('assets/map/irisan.geojson')) ?>"
             data-tiles-url="<?= e(app_url('assets/map-tiles/{z}/{x}/{y}.png')) ?>"
             data-marker-icon-url="<?= e(app_url('assets/vendor/leaflet/images/marker-icon.png')) ?>"
             data-marker-icon-retina-url="<?= e(app_url('assets/vendor/leaflet/images/marker-icon-2x.png')) ?>"
             data-marker-shadow-url="<?= e(app_url('assets/vendor/leaflet/images/marker-shadow.png')) ?>"
             data-default-location-id="<?= (int)($selectedLocationId ?? 0) ?>"
             data-admin="<?= $mapIsAdmin ? '1' : '0' ?>"></div>
        <p id="map-selection-message" class="small text-muted mt-2" role="status" aria-live="polite">Click the map to mark a point.</p>
        <p id="map-tiles-missing" class="text-warning mt-2" role="status" hidden>Local map tiles are missing. Copy the zoom 12–15 tile folders into assets/map-tiles/. The Irisan outline and markers remain available.</p>
        <p id="map-empty" class="small text-muted mt-2" hidden>No saved map points yet. Click inside Irisan to start.</p>
        <script type="application/json" id="map-locations-data"><?= json_encode($mapMarkers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
        <p class="small text-muted mt-2 mb-0">The map uses local tiles only. Live weather refresh still requires an internet connection.</p>
    </div>
</section>
