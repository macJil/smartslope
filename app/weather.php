<?php

// Weather requests and rainfall windows

function fetch_weather(float $lat, float $lng): array
{
    $url = "https://api.open-meteo.com/v1/forecast?";
    $params = [
        'latitude' => $lat,
        'longitude' => $lng,
        'current' => 'temperature_2m,relative_humidity_2m,precipitation,weather_code,wind_speed_10m',
        'hourly' => 'precipitation,precipitation_probability,soil_moisture_9_to_27cm,soil_moisture_27_to_81cm',
        'past_hours' => 73,
        'forecast_hours' => 25,
        'timezone' => 'UTC',
        'temperature_unit' => 'celsius',
        'wind_speed_unit' => 'kmh',
        'precipitation_unit' => 'mm'
    ];

    $ch = curl_init($url . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $status !== 200) {
        throw new RuntimeException('Weather provider is unavailable.');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['current']['time'])) {
        throw new RuntimeException('Weather provider returned incomplete data.');
    }
    $decoded['provider_retrieved_at_utc'] = gmdate('Y-m-d H:i:s');
    $decoded['request_parameters'] = $params;
    foreach (['precipitation_probability' => '%', 'soil_moisture_9_to_27cm' => 'm³/m³', 'soil_moisture_27_to_81cm' => 'm³/m³'] as $field => $unit) {
        if (($decoded['hourly_units'][$field] ?? null) !== $unit) {
            unset($decoded['hourly'][$field]);
        }
    }
    validate_weather_payload($decoded);
    return $decoded;
}

// Calculate rainfall from hourly data

function rainfall_total(array $samples, array $times): ?float
{
    $sum = 0.0;
    foreach ($times as $timestamp) {
        $value = $samples[$timestamp]['rain'] ?? null;
        if ($value === null) {
            return null;
        }
        $sum += $value;
    }
    if ($sum > 99999.99) {
        return null;
    }
    return round($sum, 2);
}

/** Hourly precipitation is the sum of the preceding hour, labelled by its end. */
function calculate_weather_indicators(array $hourly, string $currentTime): array
{
    $end = utc_timestamp($currentTime);
    if (
        $end === null || !isset($hourly['time'], $hourly['precipitation']) ||
        !is_array($hourly['time']) || !is_array($hourly['precipitation'])
    ) {
        throw new RuntimeException('Missing hourly rainfall data.');
    }
    $end = intdiv($end, 3600) * 3600; // Last completed hourly interval.
    $samples = [];
    foreach ($hourly['time'] as $i => $time) {
        $timestamp = is_string($time) ? utc_timestamp($time) : null;
        if ($timestamp === null || $timestamp % 3600 !== 0 || array_key_exists($timestamp, $samples)) {
            throw new RuntimeException('Hourly timestamps are invalid or duplicated.');
        }
        $samples[$timestamp] = ['index' => $i, 'rain' => finite_number($hourly['precipitation'][$i] ?? null, 0, 99999.99)];
    }
    $result = [];
    foreach ([1, 24, 72] as $hours) {
        $times = [];
        for ($i = 0; $i < $hours; $i++) {
            $times[] = $end - $i * 3600;
        }
        $result['rainfall_' . $hours . 'h'] = rainfall_total($samples, $times);
    }
    $future = [];
    for ($i = 1; $i <= 24; $i++) {
        $future[] = $end + $i * 3600;
    }
    $result['rainfall_forecast_24h'] = rainfall_total($samples, $future);
    $probabilities = [];
    foreach ($future as $timestamp) {
        $index = $samples[$timestamp]['index'] ?? null;
        $probability = $index === null ? null : finite_number($hourly['precipitation_probability'][$index] ?? null, 0, 100);
        if ($probability === null) {
            $probabilities = [];
            break;
        }
        $probabilities[] = $probability;
    }
    $result['precipitation_probability_24h'] = count($probabilities) === 24 ? (int)round(max($probabilities)) : null;
    foreach (['9_to_27cm' => '9_27cm', '27_to_81cm' => '27_81cm'] as $apiDepth => $dbDepth) {
        $index = $samples[$end]['index'] ?? null;
        $value = $index === null ? null : finite_number($hourly['soil_moisture_' . $apiDepth][$index] ?? null, 0, 1);
        $result['soil_moisture_' . $dbDepth] = $value === null ? null : round($value, 4);
    }
    return $result;
}

function validate_weather_payload(array $weather, ?int $now = null): void
{
    global $config;
    $current = $weather['current'] ?? null;
    if (
        !is_array($current) || !is_array($weather['hourly'] ?? null) ||
        !is_string($current['time'] ?? null)
    ) {
        throw new RuntimeException('Incomplete weather response.');
    }
    $observed = utc_timestamp($current['time']);
    $now = $now ?? time();
    if ($observed === null) {
        throw new RuntimeException('Invalid provider observation time.');
    }
    if ($observed > $now + ($config['future_tolerance_seconds'] ?? 300)) {
        throw new RuntimeException('Provider observation is in the future.');
    }
    if ($now - $observed > ($config['freshness_seconds'] ?? 10800)) {
        throw new RuntimeException('Provider observation is outdated.');
    }
    if (($weather['utc_offset_seconds'] ?? null) !== 0) {
        throw new RuntimeException('Expected UTC provider timestamps.');
    }
    foreach (['precipitation' => 'mm'] as $field => $unit) {
        if (($weather['hourly_units'][$field] ?? null) !== $unit) {
            throw new RuntimeException('Unexpected hourly units.');
        }
    }
    foreach (['temperature_2m' => '°C', 'relative_humidity_2m' => '%', 'wind_speed_10m' => 'km/h', 'precipitation' => 'mm', 'weather_code' => 'wmo code'] as $field => $unit) {
        if (($weather['current_units'][$field] ?? null) !== $unit) {
            throw new RuntimeException('Unexpected current units.');
        }
    }
    foreach (
        ['temperature_2m' => [-100, 100], 'relative_humidity_2m' => [0, 100],
              'wind_speed_10m' => [0, 9999.99], 'weather_code' => [0, 99], 'precipitation' => [0, 99999.99]] as $field => $range
    ) {
        if (finite_number($current[$field] ?? null, $range[0], $range[1]) === null) {
            throw new RuntimeException('Invalid current weather value.');
        }
    }
}

function refresh_location(int $locationId): array
{
    $location = get_location($locationId);
    if (
        !$location || !$location['active'] || !$location['lat'] || !$location['lng'] ||
        !is_in_irisan((float)$location['lat'], (float)$location['lng'])
    ) {
        throw new InvalidArgumentException('Select an active Irisan location.');
    }
    $weather = fetch_weather((float)$location['lat'], (float)$location['lng']);
    $current = $weather['current'];
    $indicators = calculate_weather_indicators($weather['hourly'] ?? [], $current['time']);
    $risk = calculate_risk($indicators['rainfall_1h'], $indicators['rainfall_24h'], $indicators['rainfall_72h']);
    $observed = utc_timestamp($current['time']);
    create_reading(array_merge($indicators, [
        'location_id' => $locationId, 'risk_level' => $risk,
        'temperature' => $current['temperature_2m'] ?? null,
        'humidity' => $current['relative_humidity_2m'] ?? null,
        'wind_speed' => $current['wind_speed_10m'] ?? null,
        'weather_code' => $current['weather_code'] ?? null,
         'observed_at' => gmdate('Y-m-d H:i:s', $observed),
        'rule_version' => RiskAnalyzer::VERSION,
        'rainfall_window_end' => gmdate('Y-m-d H:i:s', intdiv($observed, 3600) * 3600),
        'provider_payload' => json_encode(['classification' => 'API','request_coordinates' => [$location['lat'],$location['lng']],
            'request_policy' => '73 past hours, 25 forecast hours, UTC; optional arrays discarded if units differ',
            'response' => $weather], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]));
    return get_latest_reading($locationId);
}