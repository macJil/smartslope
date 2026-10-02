<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/RiskAnalyzer.php';
$analyzer = new RiskAnalyzer();
foreach ([
    [[0.0, 0.0, 0.0], 'low'],
    [[10.0, 0.0, 0.0], 'normal'],
    [[0.0, 50.0, 0.0], 'medium'],
    [[0.0, 0.0, 150.0], 'high'],
    [[null, 0.0, 0.0], null],
] as [$rain, $expected]) {
    if ($analyzer->analyze(...$rain) !== $expected) {
        throw new RuntimeException('Risk boundary failed for ' . json_encode($rain));
    }
}
echo "Risk boundaries and incomplete history passed.\n";
