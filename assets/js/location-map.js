(() => {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const element=document.getElementById('location-map');
        const dataElement=document.getElementById('map-locations-data');
        if (!element || !dataElement || typeof L === 'undefined') return;
        let locations=[];
        try { locations=JSON.parse(dataElement.textContent); } catch { return; }
        // The database already restricts the barangay; also reject misplaced coordinates.
        // The local raster tiles cover the full Irisan outline at native zoom 15.
        const bounds=L.latLngBounds([[16.407,120.543],[16.435,120.576]]);
        const valid=locations.filter((place) => Number.isFinite(Number(place.latitude)) &&
            Number.isFinite(Number(place.longitude)) && place.latitude !== null && place.longitude !== null &&
            Number(place.latitude)>=-90 && Number(place.latitude)<=90 &&
            Number(place.longitude)>=-180 && Number(place.longitude)<=180 &&
            bounds.contains([Number(place.latitude),Number(place.longitude)]));
        const map=L.map(element, { scrollWheelZoom: false });
        // Native zoom 15 fills the map without empty edges from smaller tile sets.
        map.setView([16.421,120.5595],15);
        map.setMinZoom(15);
        map.setMaxBounds(L.latLngBounds([[16.403,120.542],[16.436,120.580]]));
        const tiles=L.tileLayer(element.dataset.tilesUrl, {
            minZoom:15,
            maxNativeZoom:15,
            maxZoom:18,
            attribution:'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
        }).addTo(map);
        tiles.on('tileerror', () => {
            const note=document.getElementById('map-tiles-missing');
            if (note) note.hidden=false;
        });
        map.attributionControl.addAttribution('Irisan outline: weather reference data; verify provenance');

        fetch(element.dataset.boundaryUrl)
            .then((response) => { if (!response.ok) throw new Error('boundary unavailable'); return response.json(); })
            .then((shape) => { L.geoJSON(shape, { style: { color:'#2f6752', weight:2, fillColor:'#d1e8cf', fillOpacity:.08 }, interactive:false }).addTo(map); })
            .catch(() => { /* Markers remain available if the outline cannot load. */ });

        const markers=new Map();
        let selected=null;
        function icon(place, active) {
            const risk=place.stale ? '' : (['low','normal','medium','high'].includes(place.risk_level) ? place.risk_level : '');
            const reports=Number(place.pending_count)>0 ? 'has-reports' : '';
            return L.divIcon({ className:'', html:`<span class="location-pin ${risk} ${reports} ${active ? 'selected' : ''}" aria-hidden="true"></span>`, iconSize:[25,25], iconAnchor:[12,12] });
        }
        function select(place) {
            if (selected && markers.has(selected.location_id)) markers.get(selected.location_id).setIcon(icon(selected,false));
            selected=place;
            markers.get(place.location_id).setIcon(icon(place,true));
            document.dispatchEvent(new CustomEvent('smartslope:location-selected', { detail:{ location:place } }));
        }
        valid.forEach((place) => {
            place.location_id=Number(place.location_id);
            const marker=L.marker([Number(place.latitude),Number(place.longitude)], {
                icon:icon(place,false), keyboard:true, title:place.location_name, bubblingMouseEvents:false
            }).addTo(map);
            const label=document.createElement('div');
            const name=document.createElement('strong'); name.textContent=place.location_name;
            const note=document.createElement('div');
            note.textContent=(place.stale ? 'Risk unavailable or stale' : `Latest risk: ${place.risk_level || 'unavailable'}`)
                +(element.dataset.admin==='1' ? ` · ${Number(place.pending_count)||0} pending reports` : '');
            label.append(name,note);
            marker.bindPopup(label);
            marker.on('click', () => select(place));
            markers.set(place.location_id,marker);
        });
        // The application saves weather against registered locations, not arbitrary
        // pixels. A click on the map selects the nearest registered point.
        map.on('click', (event) => {
            if (!valid.length || !bounds.contains(event.latlng)) return;
            const nearest=valid.reduce((best,place) =>
                map.distance(event.latlng,[place.latitude,place.longitude])
                    < map.distance(event.latlng,[best.latitude,best.longitude]) ? place : best);
            select(nearest);
            markers.get(nearest.location_id).openPopup();
        });
        if (valid.length) {
            select(valid.find((place) => place.location_id===Number(element.dataset.defaultLocationId)) || valid[0]);
        } else {
            const note=document.getElementById('map-empty');
            if (note) note.hidden=false;
        }
        window.SmartSlopeMap={updateRisk(locationId, risk, stale) {
            const place=valid.find((item) => item.location_id===Number(locationId));
            if (!place) return;
            place.risk_level=risk; place.stale=stale;
            markers.get(place.location_id).setIcon(icon(place,selected===place));
        }};
    });
})();
