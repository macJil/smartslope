(function () {
    'use strict';
    const script = document.currentScript;
    const endpoint = new URL('../../api/address.php', script.src).href;
    const cache = new Map();
    window.lookupLocationAddress = async function (lat, lng) {
        const key = `${Number(lat).toFixed(5)},${Number(lng).toFixed(5)}`;
        if (cache.has(key)) return cache.get(key);
        try {
            const response = await fetch(`${endpoint}?${new URLSearchParams({lat, lng})}`, {signal: AbortSignal.timeout(5000)});
            if (!response.ok) return '';
            const data = await response.json();
            const address = typeof data.address === 'string' ? data.address : '';
            if (address) cache.set(key, address);
            return address;
        } catch (_) { return ''; }
    };
})();
