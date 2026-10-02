const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = { window: {} };
vm.runInNewContext(fs.readFileSync('assets/js/irisan-boundary.js', 'utf8'), context);
context.window.IrisanBoundary.load(JSON.parse(fs.readFileSync('assets/map/irisan.geojson', 'utf8')));

test('Irisan pilot point is selectable', () => {
    assert.equal(context.window.IrisanBoundary.contains(16.421, 120.5595), true);
});
test('point within rectangular map bounds but outside Irisan polygon is rejected', () => {
    assert.equal(context.window.IrisanBoundary.contains(16.409, 120.544), false);
});
