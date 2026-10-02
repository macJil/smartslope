(function () {
    'use strict';

    const addressCache = new Map();
    let lastLookupAt = 0;

    window.lookupLocationAddress = async function (lat, lng) {
        const key = `${Number(lat).toFixed(5)},${Number(lng).toFixed(5)}`;
        if (addressCache.has(key)) return addressCache.get(key);

        const delay = Math.max(0, 1000 - (Date.now() - lastLookupAt));
        if (delay) await new Promise(resolve => setTimeout(resolve, delay));
        lastLookupAt = Date.now();

        try {
            const params = new URLSearchParams({
                format: 'jsonv2',
                lat: String(lat),
                lon: String(lng),
                zoom: '18',
                addressdetails: '1'
            });
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?${params}`);
            if (!response.ok) throw new Error('Address lookup failed');
            const result = await response.json();
            const address = result.address || {};
            const parts = [
                address.house_number,
                address.road,
                address.neighbourhood,
                address.suburb,
                address.city_district,
                address.city || address.town || address.village,
                address.county,
                address.state,
                address.country
            ].filter(Boolean);
            const fullAddress = [...new Set(parts)].join(', ') || result.display_name || '';
            const boundedAddress = Array.from(fullAddress).slice(0, 255).join('');
            addressCache.set(key, boundedAddress);
            return boundedAddress;
        } catch {
            addressCache.set(key, '');
            return '';
        }
    };
})();