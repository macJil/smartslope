<?php

// Read API JSON using PHP streams instead of the native cURL library.
function http_json(string $url, int $timeout = 10): array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'follow_location' => 0,
            'header' => "User-Agent: SmartSlope/1.0 (academic Irisan prototype)\r\nConnection: close\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = $http_response_header[0] ?? '';
    if ($body === false || !str_contains($status, ' 200 ')) {
        throw new RuntimeException('API request failed.');
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('API returned invalid JSON.');
    }
    return $data;
}
