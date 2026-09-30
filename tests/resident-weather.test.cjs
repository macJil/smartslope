const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.join(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
class Element {
    constructor() { this.textContent=''; this.value=''; this.dataset={}; this.children=[]; this.events={}; this.hidden=false; this.classList={remove(){}}; }
    addEventListener(name, fn) { this.events[name]=fn; }
    replaceChildren() { this.children=[]; }
    appendChild(child) { this.children.push(child); }
    setAttribute() {} removeAttribute() {}
}
const pause = () => new Promise(resolve => setTimeout(resolve, 25));
const place = {location_id:7, location_name:'Irisan test', purok_zone:'Zone 1'};
const current = {observation_id:4,time:'2026-09-30T01:00:00Z',fetched_at:'2026-09-30T01:10:00Z',
    temperature_2m:20,relative_humidity_2m:70,precipitation:1,rain:1,showers:0,wind_speed_10m:4,wind_gusts_10m:7};
const data = {location:place, rainfall:{risk_level:'medium',risk_explanation:'Test',rainfall_1h_mm:27,
    rainfall_24h_mm:55,rainfall_72h_mm:104,observed_at:'2026-09-30T01:00:00Z'},
    current_readings:[current],risk_readings:[{reading_id:6,observed_at:current.time,risk_level:'medium',
        rainfall_1h_mm:27,rainfall_24h_mm:55,rainfall_72h_mm:104}],stale:false,alert:null,saved_observations:73};
function mount(admin=false, stored=data, live=data, skipInitial=false) {
    const source = read('resident/weather_readings.php')+read('resident/risk_area.php')+read('resident/report.php');
    const elements=Object.fromEntries([...source.matchAll(/id="([^"]+)"/g)].map(match=>[match[1],new Element()]));
    if (!admin) { delete elements['risk-reading-actions']; delete elements['risk-reading-body']; delete elements['readings-download']; }
    const handlers={}; const requests=[];
    elements['reading-panel'].dataset.storedUrl='/smartslope/api/location_dashboard.php';
    elements['reading-panel'].dataset.weatherUrl='/smartslope/api/weather.php';
    elements['reading-panel'].dataset.skipInitialRefresh=skipInitial ? '1' : '0';
    elements['weather-csrf-token'].value='token';
    if (admin) {
        elements['risk-reading-actions'].dataset.saveUrl='/smartslope/admin/save_reading.php';
        elements['risk-reading-actions'].dataset.csrfToken='token';
        elements['readings-download'].dataset.downloadBaseUrl='/smartslope/admin/download_readings.php';
    }
    vm.runInNewContext(read('assets/js/app.js'), {
        document:{getElementById:id=>elements[id]||null,createElement:()=>new Element(),addEventListener:(name,fn)=>handlers[name]=fn},
        window:{SmartSlopeMap:{updateRisk:(...args)=>requests.push(['risk',...args])}},
        fetch:async url=>{requests.push(['stored',url]);return {ok:true,json:async()=>({data:typeof stored==='function'?await stored(url):stored})};},
        jQuery:{ajax(options){requests.push(['weather',options.data]);Promise.resolve().then(()=>options.success({data:live}));return {abort(){options.error({},'abort');}};}},
        URLSearchParams,console
    });
    return {elements,requests,handlers};
}
test('resident map click loads saved observations, fetches provider data and permits refresh',async()=>{
    const {elements,requests,handlers}=mount();
    handlers['smartslope:location-selected']({detail:{location:place}});
    await pause();
    assert.equal(elements['report-location'].value,'7');
    assert.equal(elements['risk-level'].textContent,'MEDIUM');
    assert.equal(elements['current-reading-body'].children.length,1);
    assert.equal(elements['current-reading-body'].children[0].children.length,7);
    assert.deepEqual(requests.filter(item=>item[0]==='stored').map(item=>item[1]),
        ['/smartslope/api/location_dashboard.php?location_id=7']);
    assert.equal(requests.filter(item=>item[0]==='weather').length,1);
    assert.equal(new URLSearchParams(requests.find(item=>item[0]==='weather')[1]).get('csrf_token'),'token');
    elements['weather-refresh'].events.click(); await pause();
    assert.equal(requests.filter(item=>item[0]==='weather').length,2);
});
test('administrator sees edit and delete controls in saved observation rows and a location CSV',async()=>{
    const {elements,handlers}=mount(true);
    handlers['smartslope:location-selected']({detail:{location:place}}); await pause();
    assert.equal(elements['current-reading-body'].children[0].children.length,8);
    assert.equal(elements['readings-download'].href,'/smartslope/admin/download_readings.php?location_id=7');
    const actions=elements['current-reading-body'].children[0].children[7];
    assert.equal(actions.children[0].children[0].textContent,'Edit');
    assert.equal(actions.children[1].children.at(-1).textContent,'Delete');
    assert.equal(elements['risk-reading-body'].children.length,1);
});
test('admin redirect displays an edit or deletion without immediately importing the same provider row',async()=>{
    const {elements,requests,handlers}=mount(true,data,data,true);
    handlers['smartslope:location-selected']({detail:{location:place}}); await pause();
    assert.equal(requests.filter(item=>item[0]==='weather').length,0);
    elements['weather-refresh'].events.click(); await pause();
    assert.equal(requests.filter(item=>item[0]==='weather').length,1);
});
test('stale and missing risk never appear fresh',async()=>{
    const {elements,requests,handlers}=mount(false,{...data,rainfall:null,current_readings:[],stale:true},
        {...data,rainfall:null,current_readings:[],stale:true});
    handlers['smartslope:location-selected']({detail:{location:place}}); await pause();
    assert.equal(elements['risk-level'].textContent,'UNAVAILABLE');
    assert.deepEqual(requests.filter(item=>item[0]==='risk').at(-1),['risk',7,null,true]);
});
test('late response from first marker cannot overwrite second marker',async()=>{
    let release; const older=new Promise(resolve=>release=resolve);
    const second={...data, location:{...place,location_id:8,location_name:'Second'},rainfall:{...data.rainfall,risk_level:'low'}};
    const {elements,handlers}=mount(false,url=>url.endsWith('=7')?older:second,second);
    handlers['smartslope:location-selected']({detail:{location:place}});
    handlers['smartslope:location-selected']({detail:{location:second.location}});
    await pause(); release(data); await pause();
    assert.equal(elements['risk-level'].textContent,'LOW');
    assert.equal(elements['report-location'].value,'8');
});
test('offline map assets and single admin dashboard routes exist',()=>{
    assert.ok(fs.existsSync(path.join(root,'assets/map-tiles/15/27356/14867.png')));
    assert.match(read('assets/js/location-map.js'),/dataset\.tilesUrl/);
    assert.match(read('admin/index.php'),/weather_readings\.php/);
    assert.ok(!fs.existsSync(path.join(root,'admin/readings.php')));
    assert.match(read('app/UserRepository.php'),/INSERT INTO users \(full_name,username,email,contact_number,password_hash,role\)/);
});
