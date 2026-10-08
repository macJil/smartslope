<?php

// Test whether a coordinate is inside the Irisan polygon.

function is_in_irisan(float $lat, float $lng): bool
{
    $geo = json_decode((string)file_get_contents(__DIR__ . '/../assets/map/irisan.geojson'), true);
    if (!is_array($geo)) {
        return false;
    }
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
        if ($inside) {
            return true;
        }
    }
    return false;
}

// Optional reviewed susceptibility dataset

/** A source review is a documented human check, not scientific validation. */
function validate_susceptibility_dataset(array $data): void
{
    $m = $data['metadata'] ?? [];
    $host = parse_url($m['source_url'] ?? '', PHP_URL_HOST);
    if (
        ($data['type'] ?? '') !== 'FeatureCollection' || ($m['reviewed'] ?? false) !== true ||
        parse_url($m['source_url'] ?? '', PHP_URL_SCHEME) !== 'https' || !is_string($host) || !($host === 'mgb.gov.ph' || str_ends_with($host, '.mgb.gov.ph')) ||
        ($m['crs'] ?? '') !== 'EPSG:4326' || empty($data['features'])
    ) {
        throw new InvalidArgumentException('A reviewed MGB WGS84 polygon dataset is required.');
    }
    foreach (['edition','scale','reuse_terms','coverage_review','reviewed_by','reviewed_at'] as $field) {
        if (!is_string($m[$field] ?? null) || trim($m[$field]) === '') {
            throw new InvalidArgumentException('Missing source review: ' . $field);
        }
    }
    foreach ($data['features'] as $feature) {
        if (
            !in_array($feature['properties']['LndslideSusc'] ?? '', ['VHL','HL','ML','LL','DF'], true) ||
            !isset($feature['properties']['OBJECTID'])
        ) {
            throw new InvalidArgumentException('Invalid original MGB class or feature ID.');
        }
        $g = $feature['geometry'] ?? [];
        if (!in_array($g['type'] ?? '', ['Polygon','MultiPolygon'], true)) {
            throw new InvalidArgumentException('Polygon geometry required.');
        }
        $polygons = $g['type'] === 'Polygon' ? [$g['coordinates'] ?? []] : ($g['coordinates'] ?? []);
        if (!$polygons) {
            throw new InvalidArgumentException('Empty geometry.');
        }
        foreach ($polygons as $polygon) {
            if (!$polygon) {
                throw new InvalidArgumentException('Empty polygon.');
            }
            foreach ($polygon as $ring) {
                if (count($ring) < 4 || $ring[0] !== $ring[count($ring) - 1]) {
                    throw new InvalidArgumentException('Closed ring required.');
                }
                foreach ($ring as $point) {
                    if (count($point) < 2 || finite_number($point[0], -180, 180) === null || finite_number($point[1], -90, 90) === null) {
                        throw new InvalidArgumentException('Invalid WGS84 coordinate.');
                    }
                }
            }
        }
    }
}

// 0 outside, 1 inside, 2 on boundary. Boundary/overlap ambiguity stays unknown.
function susceptibility_ring(float $lat, float $lng, array $ring): int
{
    $inside = false;
    for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
        [$x1,$y1] = $ring[$j];
        [$x2,$y2] = $ring[$i];
        $cross = ($lng - $x1) * ($y2 - $y1) - ($lat - $y1) * ($x2 - $x1);
        if (abs($cross) < 1e-12 && $lng >= min($x1, $x2) - 1e-10 && $lng <= max($x1, $x2) + 1e-10 && $lat >= min($y1, $y2) - 1e-10 && $lat <= max($y1, $y2) + 1e-10) {
            return 2;
        }
        if (($y1 > $lat) !== ($y2 > $lat) && $lng < ($x2 - $x1) * ($lat - $y1) / ($y2 - $y1) + $x1) {
            $inside = !$inside;
        }
    }
    return $inside ? 1 : 0;
}

function susceptibility_lookup(array $location, ?array $dataset = null): array
{
    $unknown = ['category' => 'unknown','classification' => null,'source' => null,'reason' => 'No reviewed susceptibility dataset is installed.'];
    if (finite_number($location['lat'] ?? null) === null || finite_number($location['lng'] ?? null) === null) {
        return $unknown;
    }
    if ($dataset === null) {
        static $loaded = false, $local = null;
        if (!$loaded) {
            $loaded = true;
            $path = __DIR__ . '/../data/irisan-susceptibility.geojson';
            if (is_file($path)) {
                $local = json_decode((string)file_get_contents($path), true);
            }
        }
        $dataset = $local;
    }
    if (!is_array($dataset)) {
        return $unknown;
    }
    try {
        validate_susceptibility_dataset($dataset);
    } catch (Throwable $e) {
        return $unknown;
    }
    $matches = [];
    foreach ($dataset['features'] as $feature) {
        $g = $feature['geometry'];
        foreach ($g['type'] === 'Polygon' ? [$g['coordinates']] : $g['coordinates'] as $polygon) {
            $inside = false;
            foreach ($polygon as $i => $ring) {
                $state = susceptibility_ring((float)$location['lat'], (float)$location['lng'], $ring);
                if ($state === 2) {
                    return array_merge($unknown, ['reason' => 'Point is on an approximate polygon boundary.']);
                }
                if ($i === 0) {
                    $inside = $state === 1;
                } elseif ($state === 1) {
                    $inside = false;
                }
            }
            if ($inside) {
                $matches[] = $feature['properties'];
            }
        }
    }
    $classes = array_unique(array_column($matches, 'LndslideSusc'));
    if (count($classes) !== 1) {
        return array_merge($unknown, ['reason' => $matches ? 'Conflicting polygon classes.' : 'No reviewed polygon covers this point.']);
    }
    $map = ['VHL' => 'very_high','HL' => 'high','ML' => 'moderate','LL' => 'low','DF' => 'debris_flow'];
    return ['category' => $map[reset($classes)],'classification' => 'VERIFIED','source' => $dataset['metadata'],
        'feature_ids' => array_column($matches, 'OBJECTID'),'reason' => 'Reviewed MGB dataset; boundaries are approximate.'];
}
