# SmartSlope - Simplified Version

A simplified PHP + MySQL + Leaflet web application for Barangay Irisan landslide risk monitoring.

## Quick Start

1. **Create Database:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE smartslope;"
   mysql -u root -p smartslope < db_ultra_simple.sql
   ```

2. **Configure:**
   Create `.env` file:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=smartslope
   DB_USERNAME=root
   DB_PASSWORD=
   APP_BASE_PATH=
   ```

3. **Access:**
   - Visit `index.php`
   - Default admin: username=`admin`, password=`admin123`

## File Structure

```
smartslope/
├── index.php           # Login + Registration entry point
├── app/
│   └── config.php      # Database + Functions + Helpers
├── pages/
│   └── login.php       # Login + Registration implementation
├── dashboard.php       # Main Dashboard with Map
├── admin.php           # Admin Dashboard
├── report.php          # Submit Reports
├── readings.php        # View All Readings
├── save_reading.php     # Edit Readings
├── delete_reading.php   # Delete Readings
├── save_location.php   # Handle Map Clicks
├── logout.php          # Logout
├── db_ultra_simple.sql # Database Schema (3 tables)
├── .env                # Configuration
└── assets/
    └── map/            # GeoJSON boundary
        └── irisan.geojson
```

## Features

- User registration and login
- Interactive map with Leaflet
- Weather data from Open-Meteo API
- Rainfall-based preliminary risk screening, plus forecast precipitation and modeled soil-moisture context
- Location management
- Community report submission
- Admin dashboard for review
- CSV export

Existing databases need the additive reading-fields migration in `db_migration_weather_indicators.sql` before the updated app can save readings.

## Security

- Password hashing (PHP password_hash)
- Prepared statements for all SQL
- Secure session management

## Default Admin

- Username: `admin`
- Password: `admin123`

## Database Tables

1. **users** - User accounts
2. **locations** - Monitoring locations
3. **events** - Weather readings + reports
