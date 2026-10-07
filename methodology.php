<?php
/**
 * SmartSlope - Methodology
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

start_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartSlope - Methodology</title>
    <link rel="stylesheet" href="<?php echo url('assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/frontend.css'); ?>">
</head>
<body>
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>
    
    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <article class="card">
                    <div class="card-header page-heading">
                        <h2>Methodology & Data Sources</h2>
                    </div>
                    <div class="card-body">
                        
                        <section class="mb-5">
                            <h4>Rainfall Assessment</h4>
                            <p>
                                SmartSlope uses rainfall thresholds to assess risk levels for landslide and flood 
                                susceptibility. The assessment is based on rainfall measurements over three time periods:
                            </p>
                            
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Risk Category</th>
                                            <th>1 Hour Rainfall</th>
                                            <th>24 Hour Rainfall</th>
                                            <th>72 Hour Rainfall</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Low</strong></td>
                                            <td>&lt; 10 mm</td>
                                            <td>&lt; 25 mm</td>
                                            <td>&lt; 50 mm</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Normal</strong></td>
                                            <td>10 - 24.99 mm</td>
                                            <td>25 - 49.99 mm</td>
                                            <td>50 - 99.99 mm</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Medium</strong></td>
                                            <td>25 - 49.99 mm</td>
                                            <td>50 - 99.99 mm</td>
                                            <td>100 - 149.99 mm</td>
                                        </tr>
                                        <tr>
                                            <td><strong>High</strong></td>
                                            <td>&ge; 50 mm</td>
                                            <td>&ge; 100 mm</td>
                                            <td>&ge; 150 mm</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                <strong>Note:</strong> The strongest reached threshold determines the category. 
                                For example, if 1-hour rainfall is 60mm but 24-hour is only 80mm, the category is High 
                                because 1-hour exceeds the High threshold.
                            </div>
                            
                            <p>
                                Data is considered <strong>current</strong> if observed within the last 3 hours 
                                (configurable via <code>READING_MAX_AGE_SECONDS</code>). Data older than this is marked 
                                as <strong>outdated</strong> but still displays the last saved risk category.
                            </p>
                        </section>
                        
                        <section class="mb-5">
                            <h4>Data Sources</h4>
                            
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Weather Data: Open-Meteo</h5>
                                </div>
                                <div class="card-body">
                                    <p>
                                        <a href="https://open-meteo.com/" target="_blank">Open-Meteo</a> provides free weather 
                                        forecast data with global coverage. SmartSlope requests:
                                    </p>
                                    <ul>
                                        <li><strong>Hourly precipitation</strong> - Rainfall amounts in millimeters</li>
                                        <li><strong>Soil moisture</strong> - At 9-27cm and 27-81cm depths</li>
                                        <li><strong>Temperature</strong> - At 2 meters above ground</li>
                                        <li><strong>Relative humidity</strong> - At 2 meters above ground</li>
                                        <li><strong>Wind speed</strong> - At 10 meters above ground</li>
                                        <li><strong>Weather code</strong> - WMO weather condition codes</li>
                                    </ul>
                                    <p class="mb-0">
                                        <small class="text-muted">
                                            Open-Meteo uses various weather models including ECMWF, NOAA, and DWD. 
                                            Data is provided under the <a href="https://open-meteo.com/en/terms" target="_blank">Open-Meteo Terms</a>.
                                        </small>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Address Lookup: Nominatim (OpenStreetMap)</h5>
                                </div>
                                <div class="card-body">
                                    <p>
                                        <a href="https://nominatim.org/" target="_blank">Nominatim</a> is OpenStreetMap's 
                                        geocoding service. It provides address lookup for coordinates.
                                    </p>
                                    <p>
                                        Address lookups are:
                                    </p>
                                    <ul>
                                        <li>User-triggered (no automatic geocoding)</li>
                                        <li>Cached for 30 days to reduce API calls</li>
                                        <li>Subject to <a href="https://operations.osmfoundation.org/policies/nominatim/" target="_blank">Nominatim Usage Policy</a></li>
                                    </ul>
                                    <p class="mb-0">
                                        <small class="text-muted">
                                            SmartSlope appends a custom User-Agent header and respects rate limits.
                                        </small>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Map Data: Local Tiles & GeoJSON</h5>
                                </div>
                                <div class="card-body">
                                    <p>
                                        SmartSlope includes:
                                    </p>
                                    <ul>
                                        <li><strong>Local map tiles</strong> - Pre-rendered map images for offline use</li>
                                        <li><strong>Irisan boundary</strong> - GeoJSON polygon of Barangay Irisan, Baguio City</li>
                                        <li><strong>Leaflet.js</strong> - Open-source mapping library</li>
                                    </ul>
                                    <p>
                                        The local tiles allow the map to work without internet connection, 
                                        while still providing geographic context for location selection.
                                    </p>
                                </div>
                            </div>
                        </section>
                        
                        <section class="mb-5">
                            <h4>Limitations & Disclaimers</h4>
                            
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <strong>Important Disclaimers:</strong>
                            </div>
                            
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <strong>Not Official Alerts:</strong> Risk categories are demonstration rules, 
                                    not official landslide alerts or validated predictions.
                                </li>
                                <li class="list-group-item">
                                    <strong>Model Estimates:</strong> Open-Meteo provides model estimates, 
                                    not physical sensor readings at specific locations.
                                </li>
                                <li class="list-group-item">
                                    <strong>No Guarantee:</strong> A "low" category does not establish safety. 
                                    Always follow official advisories.
                                </li>
                                <li class="list-group-item">
                                    <strong>No Physical Sensors:</strong> SmartSlope has no physical sensors, 
                                    continuous monitoring, or AI/ML prediction capabilities.
                                </li>
                                <li class="list-group-item">
                                    <strong>Educational Use:</strong> This is an academic prototype for demonstration 
                                    and educational purposes only.
                                </li>
                            </ul>
                            
                            <div class="mt-4 p-3 bg-light rounded">
                                <p class="mb-0">
                                    <strong>For real safety decisions:</strong> Follow official advisories from local 
                                    authorities, PAGASA (Philippine Atmospheric, Geophysical and Astronomical Services 
                                    Administration), and the Baguio City Disaster Risk Reduction and Management Office.
                                </p>
                            </div>
                        </section>
                        
                        <section>
                            <h4>Technical Information</h4>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">Database Structure</h6>
                                        </div>
                                        <div class="card-body">
                                            <p>SmartSlope uses a normalized database schema with:</p>
                                            <ul>
                                                <li><strong>users</strong> - Account information</li>
                                                <li><strong>locations</strong> - Saved map points in Irisan</li>
                                                <li><strong>events</strong> - Shared table for readings and reports</li>
                                                <li><strong>readings</strong> - Weather data snapshots</li>
                                                <li><strong>reports</strong> - Resident submissions</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">Security Features</h6>
                                        </div>
                                        <div class="card-body">
                                            <p>SmartSlope implements:</p>
                                            <ul>
                                                <li>Password hashing (PHP <code>password_hash()</code>)</li>
                                                <li>PDO prepared statements for SQL queries</li>
                                                <li>HTML output escaping</li>
                                                <li>Session-based authentication</li>
                                                <li>Role-based access control</li>
                                                <li>CSRF protection on forms</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                    </div>
                    <div class="card-footer text-center text-muted">
                        <p class="mb-0">
                            SmartSlope v<?php echo APP_VERSION; ?> | 
                            An academic prototype by Student Team
                        </p>
                    </div>
                </article>
                
                <div class="mt-3 text-center">
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
