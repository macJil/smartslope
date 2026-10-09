<?php
// Used only by the local PHP server started with scripts/start-local.sh.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$pages = [
    '/', '/index.php', '/dashboard.php', '/admin.php', '/report.php',
    '/readings.php', '/logout.php', '/methodology.php',
    '/api/readings.php', '/api/address.php',
    '/actions/save_location.php', '/actions/save_reading.php',
    '/actions/delete_reading.php', '/pages/login.php',
    '/pages/dashboard.php', '/pages/report.php',
];

if (in_array($path, $pages, true)) {
    return false; // PHP serves the existing page; its login/role checks still apply.
}

// Only public asset files may be downloaded. Internal PHP, SQL and docs stay private.
if (str_starts_with($path, '/assets/') && !str_contains($path, "\0")) {
    $file = realpath(__DIR__ . $path);
    $assets = realpath(__DIR__ . '/assets') . DIRECTORY_SEPARATOR;
    if ($file && str_starts_with($file, $assets) && is_file($file)) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($extension, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'geojson'], true)) {
            return false;
        }
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Not found.';
