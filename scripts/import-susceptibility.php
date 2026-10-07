<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/assessment.php';
require_once __DIR__ . '/../app/susceptibility.php';
$path=$argv[1] ?? '';
if ($path==='' || !is_file($path) || filesize($path)>20*1024*1024) throw new InvalidArgumentException('Supply a reviewed GeoJSON file smaller than 20 MiB.');
$raw=(string)file_get_contents($path); $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
validate_susceptibility_dataset($data);
$destination=APP_ROOT . '/data/irisan-susceptibility.geojson';
if (!is_dir(dirname($destination))) mkdir(dirname($destination),0755,true);
$temp=$destination . '.tmp';
if (file_put_contents($temp,$raw)===false || !rename($temp,$destination)) throw new RuntimeException('Cannot install dataset.');
echo 'Installed reviewed dataset; SHA256: ' . hash('sha256',$raw) . "\n";
