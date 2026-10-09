<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/risk.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/geography.php';
require_once __DIR__ . '/../includes/http.php';
require_once __DIR__ . '/../includes/weather.php';
// Run with a disposable *_test database and the local HTTP fixture URL.
if (PHP_SAPI !== 'cli') exit;
if (!str_ends_with($config['db_name'], '_test')) throw new RuntimeException('Use a disposable *_test database.');
$providerUrl = $argv[1] ?? '';
if (parse_url($providerUrl, PHP_URL_HOST) !== '127.0.0.1') {
    throw new RuntimeException('Supply the local fixture URL; see docs/testing.md.');
}
$checks = 0;
function check_refresh($ok, $message) {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
$location = get_locations()[0];
$before = (int)db()->query("SELECT COUNT(*) FROM events WHERE type='reading'")->fetchColumn();
$first = refresh_location((int)$location['id'], $providerUrl);
$second = refresh_location((int)$location['id'], $providerUrl);
check_refresh($first['id'] !== $second['id'], 'Two refreshes request twice and save separate snapshots.');
check_refresh((float)$second['rainfall_24h'] === 24.0 && (float)$second['rainfall_72h'] === 72.0, 'Rainfall windows saved.');
check_refresh($second['assessment']['current_category'] === 'normal', 'Same risk threshold applied.');
check_refresh(count(json_decode($second['provider_payload'], true)['response']['hourly']['time']) === 97, 'Provider inputs saved.');
check_refresh(json_decode($second['provider_payload'], true)['response']['request_parameters']['past_hours'] === 73 && json_decode($second['provider_payload'], true)['response']['request_parameters']['forecast_hours'] === 25, 'Provider request windows unchanged.');
$providerUrl = str_replace('/weather', '/failure', $providerUrl);
try {
    refresh_location((int)$location['id'], $providerUrl);
    throw new LogicException('Provider failure accepted.');
} catch (RuntimeException $error) {
    check_refresh($error->getMessage() === 'Weather provider is unavailable.', 'Provider failure handled.');
}
check_refresh((int)db()->query("SELECT COUNT(*) FROM events WHERE type='reading'")->fetchColumn() === $before + 2, 'Failed provider call saves nothing and preserves history.');
db()->prepare('DELETE FROM events WHERE id IN (?, ?)')->execute([$first['id'], $second['id']]);
echo "$checks refresh checks passed (fixture provider, real database).\n";
