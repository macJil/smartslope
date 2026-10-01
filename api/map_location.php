<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../app/json.php';
define('SMARTSLOPE_JSON_REQUEST', true);
require_once __DIR__ . '/../app/bootstrap.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['user','admin'], true)) {
    respond_json(401, ['error'=>'authentication_required']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond_json(405, ['error'=>'method_not_allowed']);
}
if (!csrf_is_valid($_POST['csrf_token'] ?? null)) respond_json(403, ['error'=>'invalid_csrf_token']);
$latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
if ($latitude === false || $longitude === false || !is_finite($latitude) || !is_finite($longitude)) {
    respond_json(422, ['error'=>'invalid_coordinates']);
}
try {
    $location = (new LocationRepository($pdo))->forMapPoint($latitude, $longitude);
    respond_json(200, ['data'=>$location]);
} catch (InvalidArgumentException $exception) {
    respond_json(422, ['error'=>'outside_study_area']);
} catch (DomainException $exception) {
    respond_json(409, ['error'=>'location_not_found']);
} catch (Throwable $exception) {
    error_log('SmartSlope map selection failed: ' . $exception->getMessage());
    respond_json(503, ['error'=>'database_unavailable']);
}
