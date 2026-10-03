const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const geojson = JSON.parse(fs.readFileSync(path.join(__dirname, '../assets/map/irisan.geojson'), 'utf8'));
const rings = geojson.features.flatMap((feature) => {
    const geometry = feature.geometry;
    if (geometry.type === 'Polygon') return geometry.coordinates;
    if (geometry.type === 'MultiPolygon') return geometry.coordinates.flat();
    throw new Error(`Unsupported boundary geometry: ${geometry.type}`);
});
function pointInRing(lat, lng, ring) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [xi, yi] = ring[i];
        const [xj, yj] = ring[j];
        const crosses = (yi > lat) !== (yj > lat) && lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi;
        if (crosses) inside = !inside;
    }
    return inside;
}
function insideIrisan(lat, lng) {
    return rings.some((ring) => pointInRing(lat, lng, ring));
}

test('Irisan pilot point is selectable', () => {
    assert.equal(insideIrisan(16.421, 120.5595), true);
});
test('point outside Irisan polygon is rejected', () => {
    assert.equal(insideIrisan(16.42, 120.50), false);
});
test('coordinate bounds reject invalid latitude and longitude', () => {
    assert.equal(Number.isFinite(91) && insideIrisan(91, 120.5595), false);
    assert.equal(Number.isFinite(16.42) && insideIrisan(16.42, 181), false);
});
