const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const root=path.join(__dirname,'..');
const read=(file)=>fs.readFileSync(path.join(root,file),'utf8');
const templates=['risk_area.php','weather_readings.php','report.php']
    .map((file)=>read('resident/'+file)).join('\n');

class Element {
    constructor(){this.textContent='';this.value='';this.dataset={};this.children=[];this.events={};this.hidden=false;}
    addEventListener(name,handler){this.events[name]=handler;}
    replaceChildren(){this.children=[];}
    appendChild(child){this.children.push(child);}
}
const wait=()=>new Promise((done)=>setTimeout(done,15));
const location={location_id:7,location_name:'Irisan Test',purok_zone:'Zone 1'};
const reading={
    location,rainfall:{risk_level:'medium',risk_explanation:'Provisional rainfall rules',
        rainfall_1h_mm:27,rainfall_24h_mm:55,rainfall_72h_mm:104,observed_at:'2026-09-30T01:00:00Z'},
    current:{temperature_2m:20,time:'2026-09-30T01:00:00Z',fetched_at:'2026-09-30T01:10:00Z'},
    hourly:[{time:'2026-09-30T01:00:00Z',temperature_2m:20}],
    retrieved_at:'2026-09-30T01:10:00Z',stale:false,alert:null
};
function mount(stored=reading, refreshed=reading){
    const elements=Object.fromEntries([...templates.matchAll(/id="([^"]+)"/g)].map((match)=>[match[1],new Element()]));
    const handlers={};
    const requests=[];
    elements['reading-panel'].dataset.storedUrl='/landslide/api/location_dashboard.php';
    elements['reading-panel'].dataset.weatherUrl='/landslide/api/weather.php';
    elements['weather-csrf-token'].value='token';
    vm.runInNewContext(read('assets/js/app.js'),{
        document:{
            getElementById:(id)=>elements[id] || null,
            createElement:()=>new Element(),
            addEventListener:(name,fn)=>{handlers[name]=fn;}
        },
        window:{SmartSlopeMap:{updateRisk:(id,risk,stale)=>requests.push(['marker',id,risk,stale])}},
        fetch:async(url)=>{
            requests.push(['stored',url]);
            const data=typeof stored==='function' ? await stored(url) : stored;
            return {ok:true,json:async()=>({data})};
        },
        jQuery:{ajax(options){
            requests.push(['refresh',options.url,options.data]);
            Promise.resolve().then(()=>options.success({data:refreshed}));
            return {abort(){options.error({},'abort');}};
        }},URLSearchParams,console
    });
    return {elements,handlers,requests};
}

test('map replaces both location dropdowns and keeps report tied to clicked marker',async()=>{
    assert.doesNotMatch(templates,/<select[^>]+(?:risk-location-select|report-location)/);
    assert.match(read('resident/index.php'),/location_map\.php/);
    const {elements,handlers,requests}=mount();
    assert.equal(requests.length,0);
    handlers['smartslope:location-selected']({detail:{location}});
    await wait();
    assert.equal(elements['report-location'].value,'7');
    assert.equal(elements['report-submit'].disabled,false);
    assert.equal(elements['risk-level'].textContent,'MEDIUM');
    assert.equal(elements['weather-temperature'].textContent,'20.0 °C');
    assert.equal(elements['weather-hourly-body'].children[0].children.length,14);
    assert.deepEqual(requests.find((request)=>request[0]==='stored'),
        ['stored','/landslide/api/location_dashboard.php?location_id=7']);
    assert.equal(requests.some((request)=>request[0]==='refresh'),false);
});

test('refresh sends a protected POST and updates stored risk indicator',async()=>{
    const {elements,handlers,requests}=mount();
    handlers['smartslope:location-selected']({detail:{location}});
    await wait();
    elements['weather-refresh'].events.click();
    await wait();
    const request=requests.find((entry)=>entry[0]==='refresh');
    assert.equal(request[1],'/landslide/api/weather.php');
    const body=new URLSearchParams(request[2]);
    assert.equal(body.get('location_id'),'7');
    assert.equal(body.get('csrf_token'),'token');
    assert.equal(elements['weather-refresh'].disabled,false);
    assert.match(elements['reading-message'].textContent,/saved or updated/);
});

test('missing or stale data is never shown as a fresh low-risk marker',async()=>{
    const missing={...reading,rainfall:null,current:null,hourly:[],stale:true};
    const {elements,handlers,requests}=mount(missing);
    handlers['smartslope:location-selected']({detail:{location}});
    await wait();
    assert.equal(elements['risk-level'].textContent,'UNAVAILABLE');
    assert.deepEqual(requests.find((entry)=>entry[0]==='marker'),['marker',7,null,true]);
});

test('a late stored response cannot replace the newly selected location',async()=>{
    let release;
    const older=new Promise((done)=>{release=done;});
    const second={...reading,location:{location_id:8,location_name:'Second place',purok_zone:null},
        rainfall:{...reading.rainfall,risk_level:'low'}};
    const {elements,handlers}=mount((url)=>url.endsWith('=7') ? older : second);
    handlers['smartslope:location-selected']({detail:{location}});
    handlers['smartslope:location-selected']({detail:{location:second.location}});
    await wait();
    release(reading);
    await wait();
    assert.equal(elements['risk-level'].textContent,'LOW');
    assert.equal(elements['report-location'].value,'8');
});

test('admin keeps report review and reading management',()=>{
    const page=read('admin/index.php');
    assert.match(page,/location_map\.php/);
    assert.match(page,/reports\.php/);
    assert.match(page,/weather_readings\.php/);
    assert.match(page,/admin\/readings\.php/);
    assert.doesNotMatch(page,/Manage locations/);
});
