/* GeoJSON uses [longitude, latitude]; map and server check the same Irisan polygon. */
window.IrisanBoundary = (function () {
    let polygons = [];
    function insideRing(lat, lng, ring) {
        let inside = false;
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const [x1, y1] = ring[i];
            const [x2, y2] = ring[j];
            if ((y1 > lat) !== (y2 > lat) && lng < (x2 - x1) * (lat - y1) / (y2 - y1) + x1) {
                inside = !inside;
            }
        }
        return inside;
    }
    return {
        load(data) {
            const geometry = data.type === 'FeatureCollection'
                ? data.features[0].geometry : (data.geometry || data);
            polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
        },
        contains(lat, lng) {
            return polygons.some(rings => insideRing(lat, lng, rings[0]) &&
                !rings.slice(1).some(hole => insideRing(lat, lng, hole)));
        }
    };
})();
