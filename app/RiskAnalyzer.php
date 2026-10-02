<?php
declare(strict_types=1);

final class RiskAnalyzer
{
    // Prototype rainfall screening in millimetres, not an official warning.
    // Missing history must never be classified as low risk.
    public function analyze(?float $oneHour, ?float $day, ?float $threeDays): ?string
    {
        if ($oneHour === null || $day === null || $threeDays === null) {
            return null;
        }
        if ($oneHour >= 50 || $day >= 100 || $threeDays >= 150) return 'high';
        if ($oneHour >= 25 || $day >= 50 || $threeDays >= 100) return 'medium';
        if ($oneHour >= 10 || $day >= 25 || $threeDays >= 50) return 'normal';
        return 'low';
    }
}
