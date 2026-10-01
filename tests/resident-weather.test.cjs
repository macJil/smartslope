const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.join(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
class Element {
    constructor() { this.textContent=''; this.value=''; this.dataset={}; this.children=[]; this.events={}; this.hidden=false; this.classList={remove(){},add(){}}; }
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
function mount(admin=false, stored=data, live=data, skipInitial=false, resolvePoint=place, allRows=[{...current,location_id:7,location_name:'Irisan test'}]) {
    const source = read('resident/weather_readings.php')+read('resident/risk_area.php')+read('resident/report.php');
    const elements=Object.fromEntries([...source.matchAll(/id="([^"]+)"/g)].map(match=>[match[1],new Element()]));
    if (!admin) { delete elements['risk-reading-actions']; delete elements['risk-reading-body']; delete elements['readings-download']; }
    const handlers={}; const requests=[];
    elements['reading-panel'].dataset.storedUrl='/smartslope/api/location_dashboard.php';
    elements['reading-panel'].dataset.allReadingsUrl='/smartslope/api/saved_readings.php';
    elements['reading-panel'].dataset.mapLocationUrl='/smartslope/api/map_location.php';
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
        fetch:async (url,options={})=>{if(options.method==='POST'){requests.push(['resolve',options.body]);return {ok:true,json:async()=>({data:await (typeof resolvePoint==='function'?resolvePoint(options):resolvePoint)})};}if(url.includes('api/saved_readings.php')){requests.push(['all',url]);return {ok:true,json:async()=>({data:{current_readings:allRows}})};}requests.push(['stored',url]);return {ok:true,json:async()=>({data:typeof stored==='function'?await stored(url):stored})};},
        jQuery:{ajax(options){requests.push(['weather',options.data]);
            Promise.resolve().then(()=>options.success({data:typeof live==='function'?live(options):live}));
            return {abort(){options.error({},'abort');}};}},
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
    assert.equal(elements['current-reading-body'].children[0].children.length,10);
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
    assert.equal(elements['current-reading-body'].children[0].children.length,11);
    assert.equal(elements['readings-download'].href,'/smartslope/admin/download_readings.php?location_id=7');
    const actions=elements['current-reading-body'].children[0].children[10];
    assert.equal(actions.children[0].children[0].textContent,'Edit');
    assert.equal(actions.children[1].children.at(-1).textContent,'Delete');
    assert.equal(elements['risk-reading-body'],undefined);
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
test('both dashboards switch saved rows by selected location and refresh the current location',async()=>{
    const second={...data,location:{location_id:8,location_name:'Second'},
        current_readings:[{...current,observation_id:9,temperature_2m:24}],risk_readings:[]};
    for (const admin of [false,true]) {
        let temperature=24;
        const {elements,requests,handlers}=mount(admin,
            url=>url.endsWith('=7')?data:second,
            options=>{
                const id=new URLSearchParams(options.data).get('location_id');
                return id==='7'?data:{...second,current_readings:[{...second.current_readings[0],temperature_2m:temperature}]};
            });
        handlers['smartslope:location-selected']({detail:{location:place}}); await pause();
        assert.match(elements['current-reading-body'].children[0].children[2].textContent,/20\.0/);
        handlers['smartslope:location-selected']({detail:{location:second.location}}); await pause();
        assert.match(elements['current-reading-body'].children[0].children[2].textContent,/24\.0/);
        temperature=26;
        elements['weather-refresh'].events.click(); await pause();
        assert.match(elements['current-reading-body'].children[0].children[2].textContent,/26\.0/);
        assert.equal(new URLSearchParams(requests.filter(item=>item[0]==='weather').at(-1)[1]).get('location_id'),'8');
    }
});
test('both dashboards show all saved readings on opening and only show empty for an empty database',async()=>{
    for (const admin of [false,true]) {
        const other={...current,observation_id:11,location_id:8,location_name:'Second point'};
        const initial=mount(admin,data,data,false,place,[{...current,location_id:7,location_name:'First point'},other]);
        await pause();
        assert.equal(initial.elements['current-reading-body'].children.length,2);
        assert.equal(initial.elements['current-reading-body'].children[1].children[0].textContent,'Second point');
        assert.equal(initial.requests.filter(row=>row[0]==='weather').length,0);
        assert.equal(initial.requests.filter(row=>row[0]==='all').length,1);
        assert.equal(initial.elements['weather-refresh'].disabled,true);
        if (admin) {
            assert.equal(initial.elements['readings-download'].href,'/smartslope/admin/download_readings.php');
            const action=initial.elements['current-reading-body'].children[1].children[10];
            const form=action.children[0].children[1];
            assert.equal(form.children.find(input=>input.name==='location_id').value,'8');
        }
        const emptyDatabase=mount(admin,data,data,false,place,[]);
        await pause();
        assert.match(emptyDatabase.elements['current-reading-body'].children[0].children[0].textContent,/No saved/);
    }
});
test('an unread point keeps other saved rows visible until its first weather fetch succeeds',async()=>{
    const other={...current,location_id:8,location_name:'Second point'};
    const {elements,handlers}=mount(false,{...data,current_readings:[]},{...data,current_readings:[]},true,place,[other]);
    await pause();
    handlers['smartslope:location-selected']({detail:{location:place}});
    await pause();
    assert.equal(elements['current-reading-body'].children[0].children[0].textContent,'Second point');
    assert.match(elements['current-reading-summary'].textContent,/No saved readings for this point/);
});
test('Show all restores persisted rows after filtering to a map point',async()=>{
    const second={...current,location_id:8,location_name:'Second point'};
    const {elements,handlers}=mount(false,data,data,false,place,[{...current,location_id:7,location_name:'First point'},second]);
    await pause();
    handlers['smartslope:location-selected']({detail:{location:place}});
    await pause();
    assert.equal(elements['current-reading-body'].children.length,1);
    elements['show-all-readings'].events.click();
    await pause();
    assert.equal(elements['current-reading-body'].children.length,2);
    assert.equal(elements['weather-refresh'].disabled,true);
});
test('admin CSV tracks the displayed saved-reading list',async()=>{
    const {elements,handlers}=mount(true);
    await pause();
    assert.equal(elements['readings-download'].href,'/smartslope/admin/download_readings.php');
    handlers['smartslope:location-selected']({detail:{location:place}});
    await pause();
    assert.equal(elements['readings-download'].href,'/smartslope/admin/download_readings.php?location_id=7');
    elements['show-all-readings'].events.click();
    await pause();
    assert.equal(elements['readings-download'].href,'/smartslope/admin/download_readings.php');
    assert.match(read('admin/download_readings.php'),/allCurrentForStudyArea\(\)/);
});
test('offline map assets and single admin dashboard routes exist',()=>{
    assert.ok(fs.existsSync(path.join(root,'assets/map-tiles/15/27356/14867.png')));
    assert.match(read('assets/js/location-map.js'),/dataset\.tilesUrl/);
    assert.match(read('assets/js/location-map.js'),/map\.on\('click'/);
    assert.match(read('db.sql'),/Irisan pilot point/);
    assert.match(read('admin/index.php'),/weather_readings\.php/);
    assert.ok(!fs.existsSync(path.join(root,'admin/readings.php')));
    assert.match(read('app/UserRepository.php'),/INSERT INTO users \(full_name,username,email,contact_number,password_hash,role\)/);
});

test('actual coordinate click resolves its own ID then loads and saves readings on both dashboards',async()=>{
    const location={location_id:42,location_name:'Irisan 16.42200, 120.55900'};
    const result={...data,location,current_readings:[current]};
    for (const admin of [false,true]) {
        const {elements,handlers,requests}=mount(admin,result,result,false,location);
        handlers['smartslope:point-selected']({detail:{latitude:16.422,longitude:120.559}});
        await pause();
        const sent=new URLSearchParams(requests.find(row=>row[0]==='resolve')[1]);
        assert.equal(sent.get('latitude'),'16.422');
        assert.equal(sent.get('longitude'),'120.559');
        assert.equal(sent.get('csrf_token'),'token');
        assert.equal(elements['report-location'].value,'42');
        const weather=new URLSearchParams(requests.find(row=>row[0]==='weather')[1]);
        assert.equal(weather.get('location_id'),'42');
        assert.equal(elements['risk-level'].textContent,'MEDIUM');
        assert.equal(elements['current-reading-body'].children.length,1);
    }
});

test('admin reading edit exposes only risk at fetch',async()=>{
    const {elements,handlers}=mount(true);
    handlers['smartslope:location-selected']({detail:{location:place}}); await pause();
    const form=elements['current-reading-body'].children[0].children[10].children[0].children[1];
    const names=[];
    function walk(node){if(node.name) names.push(node.name);node.children.forEach(walk);}
    walk(form);
    assert.ok(names.includes('risk_level'));
    assert.ok(!names.includes('target_location_id'));
    for(const field of ['temperature','humidity','precipitation','rain','showers','wind','gusts','rainfall_1h_mm']) {
        assert.ok(!names.includes(field));
    }
});
