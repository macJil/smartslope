<?php
declare(strict_types=1);

/** Server-side client for the Open-Meteo Forecast API. */
final class WeatherApiClient
{
    private const ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    private const VARIABLES = [
        'temperature_2m',
        'relative_humidity_2m',
        'apparent_temperature',
        'precipitation',
        'rain',
        'showers',
        'weather_code',
        'cloud_cover',
        'pressure_msl',
        'surface_pressure',
        'wind_speed_10m',
        'wind_direction_10m',
        'wind_gusts_10m',
        'soil_moisture_0_to_1cm',
        'soil_moisture_1_to_3cm',
        'soil_moisture_3_to_9cm',
        'soil_moisture_9_to_27cm',
        'soil_moisture_27_to_81cm',
    ];

    /** @return array<string, mixed> */
    public function fetch(float $latitude, float $longitude): array
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Weather coordinates are out of range.');
        }

        $variables = implode(',', self::VARIABLES);
        $query = http_build_query([
            'latitude' => number_format($latitude, 6, '.', ''),
            'longitude' => number_format($longitude, 6, '.', ''),
            'current' => $variables,
            'hourly' => $variables,
            'past_days' => 3,
            'forecast_days' => 1,
            'timezone' => 'Asia/Manila',
            'temperature_unit' => 'celsius',
            'wind_speed_unit' => 'kmh',
            'precipitation_unit' => 'mm',
        ], '', '&', PHP_QUERY_RFC3986);
        $url = self::ENDPOINT . '?' . $query;

        $response = false;
        $statusCode = 0;

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException('Could not initialize the PHP cURL client.');
            }
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'SmartSlopeAcademicMVP/1.0',
            ]);
            $response = curl_exec($handle);
            $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($response === false) {
                $error = curl_error($handle);
                curl_close($handle);
                throw new RuntimeException('Weather provider request failed: ' . $error);
            }
            curl_close($handle);
        } elseif (filter_var((string) ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 12,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nUser-Agent: SmartSlopeAcademicMVP/1.0\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            $statusLine = isset($http_response_header[0]) ? $http_response_header[0] : '';
            if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
            }
        } else {
            throw new RuntimeException('Enable the PHP cURL extension or allow_url_fopen for weather requests.');
        }

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('Weather provider returned HTTP ' . $statusCode . '.');
        }

        try {
            $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Weather provider returned invalid JSON.', 0, $exception);
        }

        if (!is_array($payload)
            || !isset($payload['current']['time'])
            || !isset($payload['hourly']['time'])
            || !is_array($payload['hourly']['time'])) {
            throw new RuntimeException('Weather provider response is missing weather readings.');
        }

        return $payload;
    }
}
