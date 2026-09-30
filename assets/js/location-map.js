(() => {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const element=document.getElementById('location-map');
        const dataElement=document.getElementById('map-locations-data');
        if (!element || !dataElement || typeof L === 'undefined') return;
        let locations=[];
        try { locations=JSON.parse(dataElement.textContent); } catch { return; }
        const valid=locations.filter((place) => Number.isFinite(Number(place.latitude)) &&
            Number.isFinite(Number(place.longitude)) && place.latitude !== null && place.longitude !== null &&
            Number(place.latitude)>=-90 && Number(place.latitude)<=90 &&
            Number(place.longitude)>=-180 && Number(place.longitude)<=180);
        const map=L.map(element, { scrollWheelZoom: false });
        const bounds=L.latLngBounds([[16.38,120.50],[16.46,120.62]]);
        map.fitBounds(bounds);
        map.setMaxBounds(bounds.pad(.4));
        map.setMinZoom(Math.max(11, map.getZoom()-1));
        map.attributionControl.addAttribution('Irisan outline: weather reference data; verify boundary before operational use');

        fetch(element.dataset.boundaryUrl)
            .then((response) => { if (!response.ok) throw new Error('boundary unavailable'); return response.json(); })
            .then((shape) => { L.geoJSON(shape, { style: { color:'#2f6752', weight:2, fillColor:'#d1e8cf', fillOpacity:.8 }, interactive:false }).addTo(map); })
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
            const marker=L.marker([Number(place.latitude),Number(place.longitude)], {icon:icon(place,false), keyboard:true, title:place.location_name}).addTo(map);
            const label=document.createElement('div');
            const name=document.createElement('strong'); name.textContent=place.location_name;
            const note=document.createElement('div');
            note.textContent=(place.stale ? 'Risk unavailable or stale' : `Latest risk: ${place.risk_level || 'unavailable'}`)
                +(element.dataset.admin==='1' ? ` Â· ${Number(place.pending_count)||0} pending reports` : '');
            label.append(name,note);
            marker.bindPopup(label);
            marker.on('click', () => select(place));
            markers.set(place.location_id,marker);
        });
        if (valid.length) {
            map.fitBounds(L.latLngBounds(valid.map((place) => [Number(place.latitude),Number(place.longitude)]))
                .pad(.35), { maxZoom:15 });
            select(valid[0]);
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