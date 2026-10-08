<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/risk.php';
require_once __DIR__ . '/../includes/geography.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$path=$argv[1] ?? '';
if ($path==='' || !is_file($path) || filesize($path)>20*1024*1024) throw new InvalidArgumentException('Supply a reviewed GeoJSON file smaller than 20 MiB.');
$raw=(string)file_get_contents($path); $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
validate_susceptibility_dataset($data);
$destination=__DIR__ . '/../data/irisan-susceptibility.geojson';
if (!is_dir(dirname($destination))) mkdir(dirname($destination),0755,true);
$temp=$destination . '.tmp';
if (file_put_contents($temp,$raw)===false || !rename($temp,$destination)) throw new RuntimeException('Cannot install dataset.');
echo 'Installed reviewed dataset; SHA256: ' . hash('sha256',$raw) . "\n";
