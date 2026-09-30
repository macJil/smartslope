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
        <p class="small text-muted">Click a location marker to see its saved risk status and readings. Gray markers have no fresh risk result. A pink dot indicates pending reports on the admin map. The outline is a reference boundary, not a hazard classification.</p>
        <div id="location-map" class="smartslope-map" role="region" aria-label="Select a monitored location on the Irisan map"
             data-boundary-url="<?= e(app_url('assets/map/irisan.geojson')) ?>"
             data-tiles-url="<?= e(app_url('assets/map-tiles/{z}/{x}/{y}.png')) ?>"
             data-admin="<?= $mapIsAdmin ? '1' : '0' ?>"></div>
        <p id="map-tiles-missing" class="text-warning mt-2" role="status" hidden>Local map tiles are missing. Copy the zoom 12–15 tile folders into assets/map-tiles/. The Irisan outline and markers remain available.</p>
        <p id="map-empty" class="text-warning mt-2" hidden>No active locations have coordinates inside the Irisan map area. An administrator must verify their latitude and longitude.</p>
        <script type="application/json" id="map-locations-data"><?= json_encode($mapMarkers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
        <p class="small text-muted mt-2 mb-0">The map uses local tiles only. Live weather refresh still requires an internet connection.</p>
    </div>
</section>
