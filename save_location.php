<?php
require_once __DIR__ . '/app/config.php';
start_session();
require_login();

$lat = get('lat', 0);
$lng = get('lng', 0);

if (!$lat || !$lng) {
    flash('error', 'Coordinates are required');
    redirect('dashboard.php');
}

// Check if in Irisan
if (!is_in_irisan((float)$lat, (float)$lng)) {
    flash('error', 'Please select a location inside Barangay Irisan');
    redirect('dashboard.php');
}

// Get or create location
$location = get_or_create_location((float)$lat, (float)$lng);

// Fetch weather for this location
try {
    $weatherData = fetch_weather((float)$lat, (float)$lng);
    
    if (!empty($weatherData['current'])) {
        $current = $weatherData['current'];
        $hourly = $weatherData['hourly'] ?? [];
        
        $indicators = calculate_weather_indicators($hourly, $current['time']);
        $riskLevel = calculate_risk(
            $indicators['rainfall_1h'],
            $indicators['rainfall_24h'],
            $indicators['rainfall_72h']
        );
        
        create_reading([
            'location_id' => $location['id'],
            'rainfall_1h' => $indicators['rainfall_1h'],
            'rainfall_24h' => $indicators['rainfall_24h'],
            'rainfall_72h' => $indicators['rainfall_72h'],
            'rainfall_forecast_24h' => $indicators['rainfall_forecast_24h'],
            'precipitation_probability_24h' => $indicators['precipitation_probability_24h'],
            'soil_moisture_9_27cm' => $indicators['soil_moisture_9_27cm'],
            'soil_moisture_27_81cm' => $indicators['soil_moisture_27_81cm'],
            'risk_level' => $riskLevel,
            'temperature' => $current['temperature_2m'] ?? null,
            'humidity' => $current['relative_humidity_2m'] ?? null,
            'wind_speed' => $current['wind_speed_10m'] ?? null,
            'weather_code' => $current['weather_code'] ?? null,
            'observed_at' => $current['time'],
            'source' => 'openmeteo'
        ]);
    }
} catch (Exception $e) {
    // Weather fetch failed, but location was created
    error_log('Weather fetch failed: ' . $e->getMessage());
}

flash('success', 'Location saved and weather data fetched');
redirect('dashboard.php?location_id=' . $location['id']);
