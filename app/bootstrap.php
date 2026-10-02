<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/RiskAnalyzer.php';
require_once __DIR__ . '/assessment.php';
require_once __DIR__ . '/presentation.php';
require_once __DIR__ . '/repositories.php';
require_once __DIR__ . '/weather.php';

// User-facing failures stay generic; details go only to the PHP error log.
set_exception_handler(static function (Throwable $error): void {
    error_log('SmartSlope: ' . $error->getMessage());
    http_response_code(500);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "SmartSlope operation failed; check the PHP error log.\n");
        exit(1);
    }
    echo 'SmartSlope could not complete this request. Please try again or check the server configuration.';
});
