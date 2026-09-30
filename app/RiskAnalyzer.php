<?php
declare(strict_types=1);

/**
 * Simple rainfall-only classroom indicator. These provisional thresholds are
 * not official landslide warning criteria and must be validated with local
 * PAGASA/MGB guidance before being used for public safety decisions.
 */
final class RiskAnalyzer
{
    public static function analyze(?float $rainfall1h, ?float $rainfall24h, ?float $rainfall72h): string
    {
        if ($rainfall1h === null || $rainfall24h === null || $rainfall72h === null
            || !is_finite($rainfall1h) || !is_finite($rainfall24h) || !is_finite($rainfall72h)
            || min($rainfall1h, $rainfall24h, $rainfall72h) < 0) {
            throw new InvalidArgumentException('Complete nonnegative rainfall totals are required.');
        }
        $values = [
            '1h' => $rainfall1h,
            '24h' => $rainfall24h,
            '72h' => $rainfall72h,
        ];

        $thresholds = [
            'high' => ['1h' => 50.0, '24h' => 100.0, '72h' => 150.0],
            'medium' => ['1h' => 25.0, '24h' => 50.0, '72h' => 100.0],
            'normal' => ['1h' => 10.0, '24h' => 25.0, '72h' => 50.0],
        ];

        foreach ($thresholds as $riskLevel => $limits) {
            foreach ($values as $period => $value) {
                if ($value !== null && $value >= $limits[$period]) {
                    return $riskLevel;
                }
            }
        }

        return 'low';
    }

    public static function description(): string
    {
        return 'Prototype thresholds (mm): high at 50/100/150, medium at 25/50/100, '
            . 'normal at 10/25/50 for 1h/24h/72h. Highest matching level wins; lower values are low.';
    }
}
