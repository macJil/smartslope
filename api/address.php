<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
start_session(); header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store');
function address_json(int $status, array $body): void {
    json_response($status, $body);
}
if (!is_logged_in()) address_json(401,['error'=>'Sign in required.']);
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { header('Allow: GET'); address_json(405,['error'=>'Method not allowed.']); }
if (env_value('NOMINATIM_ENABLED','1')!=='1') address_json(200,['address'=>'']);
$lat=finite_number(get('lat'),-90,90); $lng=finite_number(get('lng'),-180,180);
if ($lat===null || $lng===null || !is_in_irisan($lat,$lng)) address_json(422,['error'=>'Select an Irisan point.']);
session_write_close();
try {
    $data=provider_cached('address',sprintf('%.5f,%.5f',$lat,$lng),static function() use ($lat,$lng): array {
        $ch=curl_init('https://nominatim.openstreetmap.org/reverse?' . http_build_query(['format'=>'jsonv2','lat'=>$lat,'lon'=>$lng,'zoom'=>18,'addressdetails'=>1]));
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>4,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_USERAGENT=>'SmartSlope/1.0 (academic Irisan prototype)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        $body=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        $decoded=$body===false ? null : json_decode($body,true);
        if ($status!==200 || !is_array($decoded)) throw new RuntimeException('Address provider unavailable.');
        return ['address'=>implode('',array_slice(preg_split('//u',(string)($decoded['display_name']??''),-1,PREG_SPLIT_NO_EMPTY) ?: [],0,255))];
    },1.1,86400,[60=>50,3600=>1000,86400=>5000]);
    address_json(200,$data);
} catch (ProviderRateLimit $e) { header('Retry-After: 2'); address_json(429,['error'=>$e->getMessage()]); }
catch (Throwable $e) { address_json(503,['error'=>'Address lookup unavailable; enter a landmark.']); }
