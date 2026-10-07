<?php

declare(strict_types=1);

// Local includes use APP_ROOT / __DIR__; browser links use APP_BASE_PATH.
define('APP_ROOT', dirname(__DIR__));
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
if (in_array(basename($scriptDirectory), ['actions', 'api', 'pages', 'scripts', 'tests'], true)) {
    $scriptDirectory = dirname($scriptDirectory);
}
if ($config['base_path'] !== '') {
    $basePath = $config['base_path'];
} elseif (in_array($scriptDirectory, ['/', '.'], true)) {
    $basePath = '';
} else {
    $basePath = $scriptDirectory;
}
define('APP_BASE_PATH', $basePath);
