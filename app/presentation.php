<?php
declare(strict_types=1);

// Shared, escaped presentation for page loads and the weather AJAX response.
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
    $baseline = str_replace('_', ' ', (string)($assessment['susceptibility'] ?? 'unknown'));
    ob_start(); ?>
    <h3 class="h6 text-muted">Current rainfall category</h3>
    <div class="assessment-category mb-2"><?= ui_category_badge($assessment) ?></div>
    <p class="mb-2"><strong>Data status:</strong> <?= e(ucfirst($status)) ?></p>
    <?php if ($status !== 'current'): ?>
        <p class="alert alert-secondary py-2">A current assessment is unavailable. <?= $location ? 'Refresh weather to request an updated reading.' : 'Select an Irisan location to view its assessment.' ?></p>
    <?php endif; ?>
    <p><strong>Location:</strong> <?= e($location['name'] ?? 'No location selected') ?></p>
    <h3 class="h6">Why this category?</h3>
    <ul class="assessment-reasons">
        <?php foreach ($assessment['reasons'] ?? ['No saved reading is available.'] as $reason): ?>
            <li><?= e($reason) ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="row g-2 mb-3">
        <?php foreach (['1h'=>'rainfall_1h', '24h'=>'rainfall_24h', '72h'=>'rainfall_72h'] as $period=>$field): ?>
        <div class="col-4"><div class="border rounded p-2 h-100"><small class="d-block text-muted"><?= e($period) ?> rainfall</small><strong><?= e(ui_number($reading[$field] ?? null, ' mm')) ?></strong></div></div>
        <?php endforeach; ?>
    </div>
    <p><strong>Baseline susceptibility:</strong> <?= e(ucfirst($baseline)) ?><br>
        <small class="text-muted"><?= $baseline === 'unknown' ? 'No baseline susceptibility is recorded for this point.' : 'Stored location baseline; assess its source separately.' ?> This is separate from the rainfall category.</small>
    </p>
    <section class="border-top pt-3">
        <h3 class="h6">24-hour forecast outlook</h3>
        <p><?= e(ui_number($reading['rainfall_forecast_24h'] ?? null, ' mm')) ?> forecast rainfall;<br>
            <?= e(ui_number($reading['precipitation_probability_24h'] ?? null, '%')) ?> maximum hourly rain chance.</p>
        <p class="small text-muted">Period starts at the last whole hour of the saved weather request. Forecast and modeled soil moisture are context; the category uses the 1h, 24h and 72h rainfall history. This outlook belongs to the saved reading and may be outdated.</p>
    </section>
    <p class="small mb-2"><strong>Observed:</strong> <?= e(ui_time($reading['observed_at'] ?? null)) ?><br>
        <strong>Retrieved:</strong> <?= e(ui_time($reading['created_at'] ?? null)) ?><br>
        <strong>Source:</strong> <?= e(ui_source($reading['source'] ?? null)) ?></p>
    <details><summary>More weather details</summary><div class="pt-2"><?= ui_weather_details($reading ?? []) ?></div></details>
    <p class="small text-muted border-top pt-3 mt-3 mb-0">SmartSlope is an academic prototype. These assessments are not official warnings or validated landslide predictions.</p>
    <?php return (string)ob_get_clean();
}

function ui_readings_head(bool $admin, string $group): string {
    ob_start(); ?>
    <thead class="table-light"><tr>
        <?php if ($admin): ?><th scope="col"><input type="checkbox" data-select-all="<?= e($group) ?>" aria-label="Select all displayed readings"></th><?php endif; ?>
        <th scope="col">Location</th><th scope="col">Current assessment</th>
        <th scope="col">Rainfall mm<br><small>1h / 24h / 72h</small></th>
        <th scope="col">Observed (PHT)</th><th scope="col">Data status</th><th scope="col">Details</th>
        <?php if ($admin): ?><th scope="col">Actions</th><?php endif; ?>
    </tr></thead>
    <?php return (string)ob_get_clean();
}

function ui_readings_rows(array $readings, bool $admin, string $group, string $formId, array $locations = []): string {
    ob_start();
    foreach ($readings as $reading):
        $assessment = $reading['assessment'] ?? reading_assessment($reading);
        $location = $locations[(int)$reading['location_id']] ?? [];
        $name = $location['name'] ?? $reading['location_name'] ?? 'Unknown'; ?>
        <tr>
            <?php if ($admin): ?><td><input type="checkbox" form="<?= e($formId) ?>" name="reading_ids[]" value="<?= (int)$reading['id'] ?>" data-bulk-item="<?= e($group) ?>" aria-label="Select reading <?= (int)$reading['id'] ?>"></td><?php endif; ?>
            <td class="reading-location"><?= e($name) ?></td>
            <td><?= ui_category_badge($assessment) ?></td>
            <td><?= e(ui_number($reading['rainfall_1h'] ?? null)) ?> / <?= e(ui_number($reading['rainfall_24h'] ?? null)) ?> / <?= e(ui_number($reading['rainfall_72h'] ?? null)) ?></td>
            <td><?= e($reading['observed_at'] ? local_date($reading['observed_at']) : 'Unavailable') ?></td>
            <td><?= e(ucfirst($assessment['data_status'] ?? 'unavailable')) ?></td>
            <td><details class="reading-details"><summary aria-label="Weather details for reading <?= (int)$reading['id'] ?>">View details</summary>
                <div class="pt-2"><?= ui_weather_details($reading) ?>
                    <?php if (!empty($location['landmark'])): ?><p><strong>Street/landmark:</strong> <?= e($location['landmark']) ?></p><?php endif; ?>
                    <?php if (isset($location['lat'], $location['lng'])): ?><p><strong>Coordinates:</strong> <?= e($location['lat']) ?>, <?= e($location['lng']) ?></p><?php endif; ?>
                    <ul><?php foreach ($assessment['reasons'] ?? [] as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul>
                </div>
            </details></td>
            <?php if ($admin): ?><td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('actions/save_reading.php?reading_id=' . (int)$reading['id'])) ?>">Edit</a></td><?php endif; ?>
        </tr>
    <?php endforeach;
    if (!$readings): ?>
        <tr><td colspan="<?= $admin ? 8 : 6 ?>" class="text-center text-muted">No saved readings. Select a location and refresh weather from the dashboard.</td></tr>
    <?php endif;
    return (string)ob_get_clean();
}
