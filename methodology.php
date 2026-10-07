<?php
require_once __DIR__ . '/functions.php';
start_session();
require_login();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SmartSlope sources and methodology</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/frontend.css')) ?>?v=<?= (int) filemtime(__DIR__ . '/assets/css/frontend.css') ?>">
</head>
<body>
<?php require_once __DIR__ . '/partials/navbar.php'; ?>

<main class="container my-4 methodology-page">
<header class="page-heading mb-4">
    <h1 class="h2 mb-1">Sources and methodology</h1>
    <p class="page-subtitle mb-0">How the SmartSlope prototype uses and explains data.</p>
</header>

<p>An API-based landslide awareness and alert prototype for Barangay Irisan. No physical sensors or AI are used. Categories are uncalibrated rainfall screening, not official warnings, landslide probabilities, or assurances of safety.</p>

<div class="alert alert-info">
    Refresh weather manually to fetch data and save a snapshot. No background monitoring runs while the website is unattended. Cached provider responses may be reused for 60 seconds; their original valid time stays unchanged.
</div>

<h2 class="h4 mt-4">Open-Meteo Weather API</h2>
<p>The prototype uses the <a href="https://open-meteo.com/">Open-Meteo Weather API</a> for weather data including rainfall, temperature, humidity, and wind speed. This is a free, open-source weather API that provides global weather data.</p>

<h2 class="h4 mt-4">Risk Assessment</h2>
<p>The risk levels are calculated based on rainfall thresholds and other environmental factors. These are screening tools only and not official warnings.</p>

<h2 class="h4 mt-4">Data Sources</h2>
<ul>
    <li><strong>Weather Data:</strong> Open-Meteo API</li>
    <li><strong>Location Data:</strong> User-submitted coordinates within Barangay Irisan</li>
    <li><strong>Report Data:</strong> User observations and reports</li>
</ul>

<h2 class="h4 mt-4">Limitations</h2>
<p>This is an academic prototype. For official landslide warnings and information, please consult local authorities and official government sources.</p>

</main>

<script src="<?= e(url('assets/js/bootstrap.bundle.js')) ?>"></script>
</body>
</html>
