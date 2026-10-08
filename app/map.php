<?php
function is_in_irisan(float $lat, float $lng): bool {
    $geo = json_decode((string)file_get_contents(__DIR__ . '/../assets/map/irisan.geojson'), true);
    if (!is_array($geo)) return false;

    $geometry = $geo['type'] === 'FeatureCollection'
        ? ($geo['features'][0]['geometry'] ?? null)
        : ($geo['geometry'] ?? $geo);

    $rings = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : ($geometry['coordinates'] ?? []);

    foreach ($rings as $polygon) {
        $inside = false;
        foreach ($polygon as $ringIndex => $ring) {
            $crosses = false;
            for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
                [$x1, $y1] = $ring[$i];
                [$x2, $y2] = $ring[$j];
                if (
                    ($y1 > $lat) !== ($y2 > $lat) &&
                    $lng < ($x2 - $x1) * ($lat - $y1) / ($y2 - $y1) + $x1
                ) {
                    $crosses = !$crosses;
                }
            }
            if ($ringIndex === 0) {
                $inside = $crosses;
            } elseif ($crosses) {
                $inside = false;
            }
        }
        if ($inside) return true;
    }
    return false;
}