<?php
declare(strict_types=1);

/** Validates clicks against the bundled Irisan reference boundary. */
final class StudyArea
{
    public static function contains(float $latitude, float $longitude): bool
    {
        if (!is_finite($latitude) || !is_finite($longitude)) return false;
        $data = json_decode((string) file_get_contents(__DIR__ . '/../assets/map/irisan.geojson'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($data['features'] ?? [] as $feature) {
            $geometry = $feature['geometry'];
            $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']]
                : ($geometry['type'] === 'MultiPolygon' ? $geometry['coordinates'] : []);
            foreach ($polygons as $rings) {
                if (!self::inRing($latitude, $longitude, $rings[0])) continue;
                foreach (array_slice($rings, 1) as $hole) {
                    if (self::inRing($latitude, $longitude, $hole)) continue 2;
                }
                return true;
            }
        }
        return false;
    }

    private static function inRing(float $y, float $x, array $ring): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }
        return $inside;
    }
}
