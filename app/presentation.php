<?php
declare(strict_types=1);

// Shared, escaped presentation for page loads and the weather AJAX response.
function ui_navigation(string $active): string {
    $links = [
        'dashboard' => ['dashboard.php', 'Dashboard'],
        'readings' => ['readings.php', 'Readings'],
        'methodology' => ['methodology.php', 'Sources & methodology'],
    ];
    if (is_admin()) {
        $links = ['dashboard' => $links['dashboard'], 'admin' => ['admin.php', 'Admin'],
            'readings' => $links['readings'], 'methodology' => $links['methodology']];
    } else {
        $links = ['dashboard' => $links['dashboard'], 'report' => ['report.php', 'Submit report'],
            'readings' => $links['readings'], 'methodology' => $links['methodology']];
    }
    ob_start(); ?>
    <nav class="navbar navbar-expand-lg site-nav" aria-label="Main navigation">
        <div class="container-fluid px-3 px-lg-4">
            <a class="navbar-brand fw-semibold" href="<?= e(url('dashboard.php')) ?>">SmartSlope <span class="brand-place">Irisan</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNavigation"
                    aria-controls="siteNavigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="siteNavigation">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($links as $key => [$path, $label]): ?>
                        <a class="nav-link<?= $active === $key ? ' active' : '' ?>" href="<?= e(url($path)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                    <?php endforeach; ?>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="nav-logout">
                        <?= csrf_field() ?><button class="nav-link btn btn-link" type="submit">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
    <?php return (string)ob_get_clean();
}

function ui_current_category(array $assessment): ?string {
    $category = $assessment['current_category'] ?? null;
    return ($assessment['data_status'] ?? '') === 'current' &&
        in_array($category, ['low', 'normal', 'medium', 'high'], true) ? $category : null;
}

function ui_current_count(array $readings, string $category): int {
    return count(array_filter($readings, static fn(array $reading): bool =>
        ui_current_category($reading['assessment'] ?? []) === $category));
}

function ui_number($value, string $unit = ''): string {
    return $value === null || $value === '' ? 'Unavailable' : (string)$value . $unit;
}

function ui_time(?string $value): string {
    return $value ? local_date($value) . ' PHT' : 'Unavailable';
}

function ui_source(?string $source): string {
    return match ($source) {
        'openmeteo' => 'Open-Meteo weather provider (modeled data)',
        'manual' => 'Manual entry',
        default => 'Source unavailable',
    };
}

function ui_location_label(array $location): string {
    $name = trim((string)($location['name'] ?? $location['location_name'] ?? ''));
    $landmark = trim((string)($location['landmark'] ?? ''));
    $purok = trim((string)($location['purok'] ?? ''));
    // A generated coordinate label is not a street address.
    $generated = preg_match('/^Irisan\s+-?\d+(?:\.\d+)?,\s*-?\d+(?:\.\d+)?$/i', $name);
    $parts = [];
    if ($landmark !== '') {
        $parts[] = $landmark;
    } elseif ($name !== '' && !$generated && strcasecmp($name, 'Irisan') !== 0) {
        $parts[] = $name;
    }
    if ($landmark !== '' && stripos($landmark, 'Baguio') !== false) return $landmark;
    if ($purok !== '' && stripos(implode(', ', $parts), $purok) === false) $parts[] = $purok;
    $parts[] = 'Barangay Irisan, Baguio City, Benguet, Philippines';
    $address = implode(', ', $parts);
    if ($generated && $landmark === '' && isset($location['lat'], $location['lng'])) {
        $address .= sprintf(' (%.5f, %.5f)', $location['lat'], $location['lng']);
    }
    return $address;
}

function ui_category_badge(array $assessment): string {
    $category = ui_current_category($assessment);
    $color = ['low'=>'success', 'normal'=>'primary', 'medium'=>'warning text-dark', 'high'=>'danger'][$category ?? ''] ?? 'secondary';
    $html = '<span class="badge bg-' . $color . '">' . e(ucfirst($category ?? 'unavailable')) . '</span>';
    if (!$category && ($assessment['data_status'] ?? '') === 'outdated' && !empty($assessment['category'])) {
        $html .= '<small class="d-block text-muted mt-1">Last saved: ' . e(ucfirst($assessment['category'])) . '</small>';
    }
    if (!empty($assessment['adjusted'])) {
        $html .= '<small class="d-block mt-1">Administrator-adjusted</small>';
    }
    return $html;
}

function ui_weather_details(array $reading): string {
    ob_start(); ?>
    <dl class="weather-details mb-0">
        <?php foreach ([
            'Forecast rainfall (24h from last whole hour)' => ui_number($reading['rainfall_forecast_24h'] ?? null, ' mm'),
            'Maximum hourly rain chance in that period' => ui_number($reading['precipitation_probability_24h'] ?? null, '%'),
            'Modeled soil moisture, 9–27 cm' => ui_number($reading['soil_moisture_9_27cm'] ?? null, ' m³/m³'),
            'Modeled soil moisture, 27–81 cm' => ui_number($reading['soil_moisture_27_81cm'] ?? null, ' m³/m³'),
            'Temperature' => ui_number($reading['temperature'] ?? null, ' °C'),
            'Humidity' => ui_number($reading['humidity'] ?? null, '%'),
            'Wind speed' => ui_number($reading['wind_speed'] ?? null, ' km/h'),
            'Retrieved' => ui_time($reading['created_at'] ?? null),
            'Source' => ui_source($reading['source'] ?? null),
        ] as $label => $value): ?>
            <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
        <?php endforeach; ?>
    </dl>
    <?php return (string)ob_get_clean();
}

function ui_assessment_panel(?array $reading, array $location, array $assessment): string {
    $status = $assessment['data_status'] ?? 'unavailable';
    $baseline = susceptibility_lookup($location);
    ob_start(); ?>
    <?php foreach (awareness_notices($assessment,$baseline) as $notice): ?>
        <div class="alert alert-<?= e($notice['tone']) ?>" role="status"><strong><?= e($notice['title']) ?></strong><p class="mb-0"><?= e($notice['text']) ?></p></div>
    <?php endforeach; ?>
    <p class="small text-muted">Academic prototype, not an official warning. Updates follow manual weather refresh. No automatic evacuation instructions.</p>
    <h3 class="h6 text-muted">Prototype rainfall-screening category</h3>
    <div class="assessment-category mb-2"><?= ui_category_badge($assessment) ?></div>
    <p class="mb-2"><strong>Data status:</strong> <?= e(ucfirst($status)) ?></p>
    <?php if ($status !== 'current'): ?>
        <p class="alert alert-secondary py-2">A current assessment is unavailable. <?= $location ? 'Refresh weather to request an updated reading.' : 'Select an Irisan location to view its assessment.' ?></p>
    <?php endif; ?>
    <p><strong>Location:</strong> <?= e($location ? ui_location_label($location) : 'No location selected') ?></p>
    <div class="row g-2 mb-3">
        <?php foreach (['1h'=>'rainfall_1h', '24h'=>'rainfall_24h', '72h'=>'rainfall_72h'] as $period=>$field): ?>
        <div class="col-4"><div class="border rounded p-2 h-100"><small class="d-block text-muted"><?= e($period) ?> rainfall</small><strong><?= e(ui_number($reading[$field] ?? null, ' mm')) ?></strong></div></div>
        <?php endforeach; ?>
    </div>
    <p class="small mb-2"><strong>Provider valid time:</strong> <?= e(ui_time($reading['observed_at'] ?? null)) ?><br>
        <strong>Rainfall window end:</strong> <?= e(ui_time($assessment['rainfall_window_end'] ?? null)) ?><br>
        <strong>Retrieved:</strong> <?= e(ui_time($reading['created_at'] ?? null)) ?><br>
        <strong>Source:</strong> <?= e(ui_source($reading['source'] ?? null)) ?></p>
    <div class="border rounded p-3 mb-3">
        <h4 class="h6">Why this category? <span class="badge bg-secondary">CALCULATED</span></h4>
        <ul><?php foreach ($assessment['reasons'] as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul>
        <p class="small mb-0">Rule: <?= e($reading['rule_version'] ?? 'prototype-1 (legacy version not recorded)') ?>. Historical inputs: <?= ($assessment['provenance_status'] ?? '') !== 'saved_provider_inputs' ? 'not retained for this legacy record' : 'retained with provider grid metadata' ?>.</p>
    </div>
    <div class="border rounded p-3 mb-3"><h4 class="h6">Baseline susceptibility: <?= e(ucwords(str_replace('_',' ',$baseline['category']))) ?></h4>
        <p class="small mb-1"><?= e($baseline['reason']) ?></p>
        <?php if ($baseline['source']): ?><p class="small mb-0"><span class="badge bg-secondary">VERIFIED</span> Source: <a href="<?= e($baseline['source']['source_url']) ?>" target="_blank" rel="noopener noreferrer">MGB</a>; edition <?= e($baseline['source']['edition']) ?>; scale <?= e($baseline['source']['scale']) ?>.</p><?php endif; ?>
    </div>
    <?php if ($reading): ?>
        <button type="button" class="btn btn-outline-primary btn-sm" data-weather-dialog
                data-template-id="assessment-weather-details" data-details-title="Weather details for <?= e($location['name'] ?? 'selected location') ?>">More weather details</button>
        <template id="assessment-weather-details">
            <p><strong>Location:</strong> <?= e(ui_location_label($location)) ?></p>
            <?= ui_weather_details($reading) ?>
        </template>
    <?php endif; ?>
    <?php return (string)ob_get_clean();
}

function ui_readings_head(bool $admin, string $group): string {
    ob_start(); ?>
    <thead class="table-light"><tr>
        <?php if ($admin): ?><th scope="col"><input type="checkbox" data-select-all="<?= e($group) ?>" aria-label="Select all displayed readings"></th><?php endif; ?>
        <th scope="col">Location</th><th scope="col">Current assessment</th>
        <th scope="col">Rainfall mm<br><small>1h / 24h / 72h</small></th>
        <th scope="col">Provider time (PHT)</th><th scope="col">Data status</th><th scope="col">Details</th>
        <?php if ($admin): ?><th scope="col">Actions</th><?php endif; ?>
    </tr></thead>
    <?php return (string)ob_get_clean();
}

function ui_readings_rows(array $readings, bool $admin, string $group, string $formId, array $locations = []): string {
    ob_start();
    foreach ($readings as $reading):
        $assessment = $reading['assessment'] ?? reading_assessment($reading);
        $location = $locations[(int)$reading['location_id']] ?? [];
        if (!$location) $location = $reading;
        $name = ui_location_label($location);
        $templateId = 'weather-details-' . $group . '-' . (int)$reading['id']; ?>
        <tr>
            <?php if ($admin): ?><td><input type="checkbox" form="<?= e($formId) ?>" name="reading_ids[]" value="<?= (int)$reading['id'] ?>" data-bulk-item="<?= e($group) ?>" aria-label="Select reading <?= (int)$reading['id'] ?>"></td><?php endif; ?>
            <td class="reading-location"><?= e($name) ?></td>
            <td><?= ui_category_badge($assessment) ?></td>
            <td><?= e(ui_number($reading['rainfall_1h'] ?? null)) ?> / <?= e(ui_number($reading['rainfall_24h'] ?? null)) ?> / <?= e(ui_number($reading['rainfall_72h'] ?? null)) ?></td>
            <td><?= e($reading['observed_at'] ? local_date($reading['observed_at']) : 'Unavailable') ?></td>
            <td><?= e(ucfirst($assessment['data_status'] ?? 'unavailable')) ?></td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary" data-weather-dialog
                        data-template-id="<?= e($templateId) ?>" data-details-title="Weather details for reading <?= (int)$reading['id'] ?>">View details</button>
                <template id="<?= e($templateId) ?>">
                    <p><strong>Location:</strong> <?= e($name) ?></p>
                    <p><strong>Provider valid time:</strong> <?= e(ui_time($reading['observed_at'] ?? null)) ?></p>
                    <?= ui_weather_details($reading) ?>
                </template>
            </td>
            <?php if ($admin): ?><td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('actions/save_reading.php?reading_id=' . (int)$reading['id'])) ?>">Edit</a></td><?php endif; ?>
        </tr>
    <?php endforeach;
    if (!$readings): ?>
        <tr><td colspan="<?= $admin ? 8 : 6 ?>" class="text-center text-muted">No saved readings. Select a location and refresh weather from the dashboard.</td></tr>
    <?php endif;
    return (string)ob_get_clean();
}

function ui_weather_modal(): string {
    return '<div class="modal fade" id="readingWeatherModal" tabindex="-1" aria-labelledby="readingWeatherTitle" aria-hidden="true">'
        . '<div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">'
        . '<div class="modal-header"><h2 class="modal-title h5" id="readingWeatherTitle">Weather details</h2>'
        . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close weather details"></button></div>'
        . '<div class="modal-body" id="readingWeatherBody"></div>'
        . '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>'
        . '</div></div></div>';
}

/** Snapshot history: repeated timestamps are collapsed only for this display. */
function ui_history(array $readings): string {
    $seen=[]; $rows=[];
    foreach ($readings as $r) {
        $key=$r['observed_at'] ?? '';
        if ($key==='' || isset($seen[$key])) continue;
        $seen[$key]=true; $rows[]=$r;
    }
    ob_start(); ?>
    <p class="small text-muted">Recent saved snapshots, newest first; repeated provider times are shown once. These are accumulated totals, not individual hourly rainfall. Never add these rows together.</p>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Scrollable rainfall history"><table class="table table-sm"><thead><tr><th>Provider time (PHT)</th><th>24h total (mm)</th><th>Change from previous saved snapshot (mm)</th><th>Calculated category</th></tr></thead><tbody>
    <?php foreach ($rows as $i=>$r):
        $previous=$rows[$i+1]??null;
        $now=finite_number($r['rainfall_24h']??null,0); $then=finite_number($previous['rainfall_24h']??null,0);
        $change=$now!==null && $then!==null ? round($now-$then,2) : null;
        $a=$r['assessment']??reading_assessment($r); ?>
        <tr><td><?= e(ui_time($r['observed_at'])) ?></td><td><?= e(ui_number($now)) ?></td><td><?= e(ui_number($change)) ?></td><td><?= e($a['calculated_category'] ?? 'Unavailable') ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="4">No saved history for this location.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php return (string)ob_get_clean();
}
