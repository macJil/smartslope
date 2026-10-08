<?php

// RiskAnalyzer: the small OOP example

/** Prototype rainfall screening. Thresholds are not calibrated for Irisan. */
final class RiskAnalyzer
{
    public const VERSION = 'prototype-1';
    public const THRESHOLDS = [
        'high' => [50, 100, 150],
        'medium' => [25, 50, 100],
        'normal' => [10, 25, 50],
    ];

    public function analyze(?float $oneHour, ?float $day, ?float $threeDays): ?string
    {
        return $this->explain($oneHour, $day, $threeDays)['category'];
    }

    public function explain(?float $oneHour, ?float $day, ?float $threeDays): array
    {
        $values = [$oneHour, $day, $threeDays];
        foreach ($values as $value) {
            if ($value === null || !is_finite($value) || $value < 0) {
                return ['category' => null, 'reasons' => ['Rainfall history is incomplete or invalid.'], 'rule_version' => self::VERSION];
            }
        }
        $hours = [1, 24, 72];
        foreach (self::THRESHOLDS as $level => $limits) {
            $reasons = [];
            foreach ($limits as $i => $limit) {
                if ($values[$i] >= $limit) {
                    $reasons[] = "{$hours[$i]}-hour rainfall ({$values[$i]} mm) reached the prototype {$level} threshold ({$limit} mm).";
                }
            }
            if ($reasons) {
                return ['category' => $level, 'reasons' => $reasons, 'rule_version' => self::VERSION];
            }
        }
        return ['category' => 'low', 'reasons' => ['Rainfall is below all prototype thresholds. Low does not mean the slope is safe.'], 'rule_version' => self::VERSION];
    }
}

// Reading assessment

function utc_timestamp(?string $value): ?int
{
    if (!$value) {
        return null;
    }
    foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
        if ($date && $date->format($format) === $value) {
            return $date->getTimestamp();
        }
    }
    return null;
}

function finite_number($value, ?float $minimum = null, ?float $maximum = null): ?float
{
    if (!is_numeric($value)) {
        return null;
    }
    $number = (float)$value;
    if (
        !is_finite($number) || ($minimum !== null && $number < $minimum) ||
        ($maximum !== null && $number > $maximum)
    ) {
        return null;
    }
    return $number;
}

function calculate_risk(?float $r1, ?float $r24, ?float $r72): string
{
    $level = (new RiskAnalyzer())->analyze($r1, $r24, $r72);
    if ($level === null) {
        throw new RuntimeException('Rainfall history is incomplete or invalid.');
    }
    return $level;
}

/** Request-time freshness; the legacy stale column is not used as a clock. */
function reading_assessment(?array $reading, array $location = [], ?int $now = null): array
{
    global $config;
    $now = $now ?? time();
    $result = [
        'category' => null, 'calculated_category' => null, 'current_category' => null,
        'basis' => 'rainfall', 'data_status' => 'unavailable', 'adjusted' => false,
        'reasons' => ['No saved reading is available.'], 'rule_version' => RiskAnalyzer::VERSION,
        'susceptibility' => $location['susceptibility'] ?? 'unknown',
        'observed_at' => $reading['observed_at'] ?? null,
        'retrieved_at' => $reading['created_at'] ?? null,
        'rainfall_window_end' => null, 'provenance_status' => 'unavailable', 'classification' => 'CALCULATED',
    ];
    if (!$reading) {
        return $result;
    }
    $analysis = (new RiskAnalyzer())->explain(
        finite_number($reading['rainfall_1h'] ?? null, 0),
        finite_number($reading['rainfall_24h'] ?? null, 0),
        finite_number($reading['rainfall_72h'] ?? null, 0)
    );
    $values = [];
    foreach (['rainfall_1h', 'rainfall_24h', 'rainfall_72h'] as $key) {
        $values[] = finite_number($reading[$key] ?? null, 0);
    }
    if (!in_array(null, $values, true) && ($values[0] > $values[1] || $values[1] > $values[2])) {
        $analysis = ['category' => null, 'reasons' => ['Rainfall totals are inconsistent: 1h must not exceed 24h, and 24h must not exceed 72h.']];
    }
    if (!empty($reading['rule_version']) && $reading['rule_version'] !== RiskAnalyzer::VERSION) {
        $analysis = ['category' => null, 'reasons' => ['This record uses a different rule version; a current assessment is unavailable.']];
    }
    $observedTimestamp = utc_timestamp($reading['observed_at'] ?? null);
    $result['rainfall_window_end'] = $reading['rainfall_window_end'] ?? ($observedTimestamp === null ? null : gmdate('Y-m-d H:i:s', intdiv($observedTimestamp, 3600) * 3600));
    $result['provenance_status'] = empty($reading['provider_payload']) ? 'legacy_without_hourly_inputs' : 'saved_provider_inputs';
    $result['classification'] = 'CALCULATED';
    $result['calculated_category'] = $analysis['category'];
    $result['reasons'] = $analysis['reasons'];
    $stored = $reading['risk_level'] ?? null;
    $validStored = in_array($stored, ['low', 'normal', 'medium', 'high'], true);
    $observed = utc_timestamp($reading['observed_at'] ?? null);
    if (
        $analysis['category'] === null || !$validStored || $observed === null ||
        $observed > $now + ($config['future_tolerance_seconds'] ?? 300)
    ) {
        $result['data_status'] = 'incomplete';
        $result['reasons'][] = 'A current assessment is unavailable because saved values or the observation time are invalid.';
        return $result;
    }
    $result['category'] = $stored;
    $result['adjusted'] = $stored !== $analysis['category'] || !empty($reading['adjustment_log']);
    if ($result['adjusted']) {
        $result['reasons'][] = 'Administrator-adjusted category; calculated rainfall category: ' . $analysis['category'] . '.';
    }
    $result['data_status'] = $now - $observed > ($config['freshness_seconds'] ?? 10800) ? 'outdated' : 'current';
    if ($result['data_status'] === 'current') {
        $result['current_category'] = $stored;
    } else {
        $result['reasons'][] = 'This is the last known category; refresh to obtain current data.';
    }
    return $result;
}

function assess_reading(array $reading): array
{
    $reading['assessment'] = reading_assessment($reading);
    // Compatibility for existing templates. Never write this derived value back.
    $reading['stale'] = $reading['assessment']['data_status'] === 'current' ? 0 : 1;
    return $reading;
}
