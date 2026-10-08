<?php
final class RiskAnalyzer {
    public const VERSION = 'prototype-1';
    public const THRESHOLDS = [
        'high' => [50, 100, 150],
        'medium' => [25, 50, 100],
        'normal' => [10, 25, 50],
    ];

    public function analyze(?float $oneHour, ?float $day, ?float $threeDays): ?string {
        return $this->explain($oneHour, $day, $threeDays)['category'];
    }

    public function explain(?float $oneHour, ?float $day, ?float $threeDays): array {
        $values = [$oneHour, $day, $threeDays];
        foreach ($values as $value) {
            if ($value === null || !is_finite($value) || $value < 0) {
                return [
                    'category' => null,
                    'reasons' => ['Rainfall history is incomplete or invalid.'],
                    'rule_version' => self::VERSION
                ];
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
                return [
                    'category' => $level,
                    'reasons' => $reasons,
                    'rule_version' => self::VERSION
                ];
            }
        }
        return [
            'category' => 'low',
            'reasons' => ['Rainfall is below all prototype thresholds. Low does not mean the slope is safe.'],
            'rule_version' => self::VERSION
        ];
    }
}