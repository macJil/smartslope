/* Local tiles only. Missing detailed tiles use a cropped lower-zoom tile. */
(function () {
    'use strict';
    const OfflineTiles = L.TileLayer.extend({
        createTile: function (coords, done) {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 256;
            const context = canvas.getContext('2d');
            const template = this._url;
            function load(zoom) {
                const scale = Math.pow(2, coords.z - zoom);
                const x = Math.floor(coords.x / scale);
                const y = Math.floor(coords.y / scale);
                const img = new Image();
                img.onload = function () {
                    const size = img.width / scale;
                    context.drawImage(img, (coords.x - x * scale) * size,
                        (coords.y - y * scale) * size, size, size, 0, 0, 256, 256);
                    done(null, canvas);
                };
                img.onerror = function () {
                    if (zoom > 12) load(zoom - 1);
                    else done(new Error('Outside bundled offline tile coverage'), canvas);
                };
                img.src = L.Util.template(template, {z: zoom, x: x, y: y});
            }
            load(coords.z);
            return canvas;
        }
    });
    window.irisanTiles = function (url, options) { return new OfflineTiles(url, options); };
}());
