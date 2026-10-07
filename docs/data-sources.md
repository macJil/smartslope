# Data sources and provenance

## Weather provider

The application requests hourly forecast data from Open-Meteo's `/v1/forecast` endpoint using the selected Irisan coordinates. The request asks for current temperature, relative humidity, precipitation, WMO weather code and wind speed, plus hourly precipitation, precipitation probability and model soil moisture. It requests 73 past hours and 25 forecast hours in UTC. PHP derives completed 1, 24 and 72 hour rainfall windows and a separate next-24-hour outlook.

These are provider model estimates for a grid/cell, not a physical sensor measurement at a home or slope. The app stores source, provider observation time, retrieval time, derived rainfall and a normalized provider payload for new records. Model inputs and API availability can change or fail. The code validates fields, expected units, time freshness, ranges and hourly continuity before creating a new assessment.

Open-Meteo lists its API data terms and attribution at [Terms](https://open-meteo.com/en/terms), and distinguishes free non-commercial access from paid commercial service in [Pricing](https://open-meteo.com/en/pricing). Keep visible attribution in the application and review current terms before changing use or deploying commercially.

## Offline map

The repository contains a local Leaflet copy, local map tiles under `assets/map-tiles/`, and `assets/map/irisan.geojson`. This allows the interface and basemap images to work without an active tile request. The code identifies the boundary data used by the browser and server; the repository notes do not establish the map-tile provider's original source or redistribution rights. Before public redistribution, identify and document the tile source, its terms and required attribution. Map tile availability does not validate the risk rules or susceptibility.

Leaflet is a bundled JavaScript map library. Its license is retained under `assets/vendor/leaflet/LICENSE`.

## Susceptibility / hazard evidence

No verified Irisan susceptibility subset or historical event catalog is bundled. Existing location susceptibility values default to `unknown`, and hand-entered enum values alone are not treated as verified. Official MGB links on the methodology page are reference links, not a live feed, imported polygon set, or confirmed local classification. `data/README.md` describes the evidence required before importing a reviewed polygon dataset.

`docs/local-validation.csv` currently contains only a header row. No threshold calibration or measured model accuracy can be claimed. Any future validation needs sourced landslide events and non-event periods, location/time uncertainty, comparable rainfall observations, rule version and separate false-alarm and missed-event evaluation.

## Optional reverse geocoding

Nominatim is disabled by default. If enabled, the server sends selected coordinates to OpenStreetMap Foundation's public Nominatim service. The current policy caps use at one request per second and imposes additional requirements; this simplified branch does not implement spacing or caching. Review the [Nominatim Usage Policy](https://operations.osmfoundation.org/policies/nominatim/) before enabling. Landmark/coordinate labels work without geocoding.

## What SmartSlope does not consume

The current branch does not read a live MGB/PAGASA warning feed, does not query an IoT device, and does not use an AI model. Official advisory links are informational references only.
