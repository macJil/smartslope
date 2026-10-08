<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/risk.php';
require_once __DIR__ . '/../includes/geography.php';



session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
function address_json(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (empty($_SESSION['user_id'])) {
    address_json(401, ['error' => 'Sign in required.']);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    address_json(405, ['error' => 'Method not allowed.']);
}
if (!NOMINATIM_ENABLED) {
    address_json(200, ['address' => '']);
}
$lat = finite_number(get('lat'), -90, 90);
$lng = finite_number(get('lng'), -180, 180);
if ($lat === null || $lng === null || !is_in_irisan($lat, $lng)) {
    address_json(422, ['error' => 'Select an Irisan point.']);
}
session_write_close();
try {
        $ch = curl_init('https://nominatim.openstreetmap.org/reverse?' . http_build_query(['format' => 'jsonv2','lat' => $lat,'lon' => $lng,'zoom' => 18,'addressdetails' => 1]));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,CURLOPT_TIMEOUT => 4,CURLOPT_CONNECTTIMEOUT => 3,CURLOPT_USERAGENT => 'SmartSlope/1.0 (academic Irisan prototype)',CURLOPT_SSL_VERIFYPEER => true,CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
        $decoded = $body === false ? null : json_decode($body, true);
    if ($status !== 200 || !is_array($decoded)) {
        throw new RuntimeException('Address provider unavailable.');
    }
    $data = ['address' => implode('', array_slice(preg_split('//u', (string)($decoded['display_name'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 255))];
    address_json(200, $data);
} catch (Throwable $e) {
    address_json(503, ['error' => 'Address lookup unavailable; enter a landmark.']);
}
