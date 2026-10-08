<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$analyzer = new RiskAnalyzer();
$checks = 0;
foreach (RiskAnalyzer::THRESHOLDS as $level => $thresholds) {
    foreach ($thresholds as $i => $threshold) {
        $values = [0.0, 0.0, 0.0];
        $values[$i] = (float)$threshold;
        if ($analyzer->analyze(...$values) !== $level) throw new RuntimeException('Threshold boundary failed.');
        $values[$i] -= 0.01;
        if ($analyzer->analyze(...$values) === $level) throw new RuntimeException('Below-threshold boundary failed.');
        $checks += 2;
    }
}
foreach ([null, -1.0, INF, NAN] as $bad) {
    if ($analyzer->analyze($bad, 0.0, 0.0) !== null) throw new RuntimeException('Invalid value accepted.');
    $checks++;
}
if ($analyzer->analyze(0, 0, 0) !== 'low') throw new RuntimeException('Zero rainfall failed.');
if ($analyzer->analyze(50, 25, 50) !== 'high') throw new RuntimeException('Highest category must win.');
if (!$analyzer->explain(25, 0, 0)['reasons']) throw new RuntimeException('Missing explanation.');
echo ($checks + 3) . " risk checks passed.\n";
