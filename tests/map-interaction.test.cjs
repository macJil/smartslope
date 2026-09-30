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
            setLatLng(value){this.position=value;},setIcon(){},bindPopup(){},openPopup(){},
            on(name,fn){this.events[name]=fn;}};return m;}};
    let ready;
    vm.runInNewContext(code,{document:{addEventListener(name,fn){if(name==='DOMContentLoaded')ready=fn;},getElementById:id=>elements[id]||null,
        createElement(){return {textContent:'',append(){}};},dispatchEvent:e=>selected.push(e.detail.location.location_id)},
        window:{},CustomEvent:class {constructor(name,options){this.detail=options.detail;}},
        L,fetch:async()=>({ok:true,json:async()=>({features:[]})}),Map,Number,console});
    ready(); return {events,selected,markers,elements};
}
test('map click draws and moves a local image pin, selecting nearest registered location',()=>{
    const one={location_id:1,location_name:'West',latitude:16.421,longitude:120.551,stale:true};
    const two={location_id:2,location_name:'East',latitude:16.421,longitude:120.568,stale:true};
    const {events,selected,markers,elements}=mount([one,two]);
    assert.equal(selected[0],1);
    events.click({latlng:{lat:16.421,lng:120.567}});
    assert.equal(selected.at(-1),2);
    assert.equal(markers.length,3);
    assert.equal(markers[2].options.icon.iconUrl,'/smartslope/assets/vendor/leaflet/images/marker-icon.png');
    assert.match(elements['map-selection-message'].textContent,/nearest registered location: East/);
    // This point used to be silently ignored by the narrow rectangle guard.
    events.click({latlng:{lat:16.421,lng:120.577}});
    assert.equal(markers.length,3);
    assert.equal(markers[2].position.lng,120.577);
    markers[0].events.click(); assert.equal(selected.at(-1),1);
});
test('without registered locations the map explains why no reading can be requested',()=>{
    const {events,selected,elements}=mount([]);
    events.click({latlng:{lat:16.421,lng:120.559}});
    assert.deepEqual(selected,[]);
    assert.equal(elements['map-empty'].hidden,false);
    assert.equal(elements['map-selection-message'].textContent,
        'No registered location with coordinates is available. Ask an administrator to add one.');
    assert.equal(fs.existsSync(path.join(root,'assets/vendor/leaflet/images/marker-icon.png')),true);
});
test('root test seed contains coordinates so its location appears on the map',()=>{
    const sql=fs.readFileSync(path.join(root,'db_test_seed.sql'),'utf8');
    assert.match(sql,/latitude,\s*longitude,/);
    assert.match(sql,/ON DUPLICATE KEY UPDATE is_active = 1, latitude = 16\.421000/);
});
