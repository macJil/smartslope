# Irisan local map tiles

For local testing, copy **the four directories `12`, `13`, `14`, `15`** from
`weather/assets/map-tiles/` into this folder. Preserve their nested
`zoom/x/y.png` structure, such as `assets/map-tiles/15/27356/14867.png`.
The SmartSlope map uses `maxNativeZoom: 15` to enlarge these existing tiles
at higher zoom levels. The `weather` reference has missing Irisan tiles at
zoom levels 16â€“18, so those directories are not needed for this setup.

The four directories contain 110 image tiles (about 7.7 MB). Confirm the
images' original source and redistribution terms before committing or
publishing them. Map data attribution is shown in the map.