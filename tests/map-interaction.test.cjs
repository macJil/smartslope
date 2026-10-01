const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.join(__dirname,'..');
const code=fs.readFileSync(path.join(root,'assets/js/location-map.js'),'utf8');
function mount(locations) {
    const events={}; const selected=[]; const markers=[];
    const element={dataset:{tilesUrl:'/smartslope/assets/map-tiles/{z}/{x}/{y}.png',
        boundaryUrl:'/smartslope/assets/map/irisan.geojson',defaultLocationId:'0',admin:'0',
        markerIconUrl:'/smartslope/assets/vendor/leaflet/images/marker-icon.png',
        markerIconRetinaUrl:'/smartslope/assets/vendor/leaflet/images/marker-icon-2x.png',
        markerShadowUrl:'/smartslope/assets/vendor/leaflet/images/marker-shadow.png'}};
    const elements={'location-map':element,'map-locations-data':{textContent:JSON.stringify(locations)},
        'map-empty':{hidden:true},'map-tiles-missing':{hidden:true},'map-selection-message':{textContent:''}};
    const map={setView(){return this;},setMinZoom(){return this;},setMaxBounds(){return this;},on(name,fn){events[name]=fn;},
        distance(a,b){let p=Array.isArray(a)?a:[a.lat,a.lng];return Math.hypot(p[0]-b[0],p[1]-b[1]);},
        attributionControl:{addAttribution(){}}};
    const L={map(){return map;},latLngBounds(){return {contains(p){const [lat,lon]=Array.isArray(p)?p:[p.lat,p.lng];return lat>=16.407&&lat<=16.435&&lon>=120.543&&lon<=120.576;}};},
        tileLayer(){return {addTo(){return this;},on(){}};},geoJSON(){return {addTo(){}};},
        divIcon(value){return value;},icon(value){return value;},
        marker(position,options){let m={position,options,events:{},addTo(){markers.push(this);return this;},
            setLatLng(value){this.position=value;},setIcon(value){this.options.icon=value;},remove(){this.removed=true;},bindPopup(){},openPopup(){},
            on(name,fn){this.events[name]=fn;}};return m;}};
    let ready;
    const window={};
    vm.runInNewContext(code,{document:{addEventListener(name,fn){if(name==='DOMContentLoaded')ready=fn;},getElementById:id=>elements[id]||null,
        createElement(){return {textContent:'',append(){}};},dispatchEvent:e=>selected.push(e.detail)},
        window,CustomEvent:class {constructor(name,options){this.detail=options.detail;}},
        L,fetch:async()=>({ok:true,json:async()=>({features:[]})}),Map,Number,console});
    ready(); return {events,selected,markers,elements,mapApi:window.SmartSlopeMap};
}
test('map sends the clicked coordinates even when there are no registered locations',()=>{
    const {events,selected,markers}=mount([]);
    events.click({latlng:{lat:16.421,lng:120.559}});
    assert.equal(selected.at(-1).latitude,16.421);
    assert.equal(selected.at(-1).longitude,120.559);
    events.click({latlng:{lat:16.422,lng:120.560}});
    assert.equal(selected.at(-1).latitude,16.422);
    assert.equal(markers.length,1);
    assert.equal(markers[0].position.lng,120.560);
    assert.match(markers[0].options.icon.iconUrl,/marker-icon.png$/);
});
test('click coordinates are not replaced by the nearest pre-existing location',()=>{
    const {events,selected}=mount([{location_id:1,latitude:16.421,longitude:120.551,location_name:'Old',stale:true}]);
    assert.equal(selected.length,0);
    events.click({latlng:{lat:16.422,lng:120.559}});
    assert.equal(selected.at(-1).longitude,120.559);
    assert.equal(selected.at(-1).location,undefined);
});
test('saved markers use risk colors and a clicked point becomes a saved marker',()=>{
    const {events,markers,mapApi}=mount([{location_id:1,latitude:16.421,longitude:120.559,
        location_name:'Existing',risk_level:'low',stale:false}]);
    assert.match(markers[0].options.icon.html,/location-pin low/);
    for (const level of ['normal','medium','high']) {
        mapApi.updateRisk(1,level,false);
        assert.match(markers[0].options.icon.html,new RegExp(`location-pin ${level}`));
    }
    events.click({latlng:{lat:16.422,lng:120.560}});
    const temporary=markers[1];
    mapApi.registerLocation({location_id:2,latitude:16.422,longitude:120.560,location_name:'New point'});
    assert.equal(temporary.removed,true);
    assert.equal(markers.length,3);
    mapApi.updateRisk(2,'high',false);
    assert.match(markers[2].options.icon.html,/location-pin high/);
    mapApi.updateRisk(2,'low',true);
    assert.match(markers[2].options.icon.html,/location-pin low stale/);
    const css=fs.readFileSync(path.join(root,'assets/css/location-map.css'),'utf8');
    for (const [level,color] of [['low','#198754'],['normal','#0d6efd'],['medium','#ffc107'],['high','#dc3545']]) {
        assert.match(css,new RegExp(`\\.location-pin\\.${level} \\{ background: ${color.replace('#','\\#')}; \\}`));
    }
});
