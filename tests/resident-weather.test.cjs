const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.join(__dirname, '..');
const read = p => fs.readFileSync(path.join(root,p),'utf8');
const templates = ['risk_area.php','weather_readings.php'].map(p => read('resident/'+p)).join('\n');
class Element {
  constructor(){this.textContent='';this.value='';this.dataset={};this.children=[];this.events={};}
  addEventListener(name, fn){this.events[name]=fn;}
  replaceChildren(){this.children=[];}
  appendChild(child){this.children.push(child);}
}
function mount(fetcher, base='', selected='1'){
  const elements = Object.fromEntries([...templates.matchAll(/id="([^"]+)"/g)].map(m=>[m[1],new Element()]));
  elements['risk-location-select'].value=selected;
  elements['risk-location-select'].dataset.weatherUrl=base+'/api/weather.php';
  elements['weather-csrf-token'].value='test-token';
  vm.runInNewContext(read('assets/js/app.js'),{
    document:{getElementById:id=>elements[id]||null,createElement:()=>new Element()},
    window:{setTimeout:fn=>setTimeout(fn,0),clearTimeout},
    jQuery: {ajax(options) {
      let aborted = false;
      Promise.resolve().then(() => fetcher(options.url, {
        method: options.method, body: new URLSearchParams(options.data)
      })).then(async response => {
        if (aborted) return;
        const data = await response.json();
        if (response.ok) options.success(data);
        else options.error({responseJSON:data}, 'error');
      }).catch(() => {if (!aborted) options.error({}, 'parsererror');});
      return {abort() {aborted = true; options.error({}, 'abort');}};
    }}, URLSearchParams,console
  });
  return elements;
}
const settle=()=>new Promise(resolve=>setTimeout(resolve,20));
const payload=(name='Location one',risk='high',warning=false)=>({data:{
  location:{location_name:name,purok_zone:'Zone 1'},
  rainfall:{risk_level:risk,risk_explanation:'Documented rainfall rules',rainfall_1h_mm:31,rainfall_24h_mm:101,rainfall_72h_mm:201,observed_at:'2026-09-30T01:00:00Z'},
  current:{temperature_2m:22.5,time:'2026-09-30T09:00'},
  hourly:[{time:'2026-09-30T09:00',temperature_2m:22.5}],
  retrieved_at:'2026-09-30T01:00:00Z',persistence_warning:warning
}});
const response=p=>({ok:true,json:async()=>p});
test('user page includes the hourly template below both cards',()=>{
  const page=read('resident/index.php');
  assert.ok(page.indexOf("'/weather_readings.php'")>page.indexOf("'/report.php'"));
  assert.match(templates,/data-weather-url="<\?= e\(app_url\('api\/weather.php'\)\)/);
});
for(const base of ['', '/landslide'])test('loads real response shape using configured path '+(base||'Herd root'),async()=>{
  const e=mount(async(url,opts)=>{
    assert.equal(url,base+'/api/weather.php');assert.equal(opts.method,'POST');
    assert.equal(opts.body.get('csrf_token'),'test-token');assert.equal(opts.body.get('location_id'),'1');
    return response(payload());
  },base);
  await settle();assert.equal(e['risk-level'].textContent,'HIGH');
  assert.equal(e['weather-temperature'].textContent,'22.5 °C');
  assert.equal(e['weather-hourly-body'].children[0].children.length,19);
  assert.match(e['reading-message'].textContent,/Location one/);
});
test('provider errors clear risk and show retry',async()=>{
  const e=mount(async()=>({ok:false,json:async()=>({error:'weather_provider_unavailable'})}));
  await settle();assert.equal(e['risk-level'].textContent,'UNAVAILABLE');assert.equal(e['weather-retry'].hidden,false);
});
test('no coordinate-ready selection makes no request',async()=>{
  let calls=0;const e=mount(async()=>{calls++;},'','');await settle();
  assert.equal(calls,0);assert.equal(e['risk-level'].textContent,'UNAVAILABLE');
});
test('persistence failure still displays provider data',async()=>{
  const e=mount(async()=>response(payload('Location one','high',true)));await settle();
  assert.equal(e['weather-temperature'].textContent,'22.5 °C');assert.match(e['reading-message'].textContent,/could not be saved/);
});
test('incomplete rainfall cannot show a low risk',async()=>{
  const e=mount(async()=>response(payload('Location one',null)));await settle();assert.equal(e['risk-level'].textContent,'UNAVAILABLE');
});
test('late response cannot overwrite newly selected location',async()=>{
  let finishOld;
  const e=mount((url,opts)=>opts.body.get('location_id')==='1'?new Promise(resolve=>{finishOld=resolve;}):Promise.resolve(response(payload('Location two','low'))));
  await settle();
  e['risk-location-select'].value='2';e['risk-location-select'].events.change();await settle();
  finishOld(response(payload('Location one','high')));await settle();
  assert.equal(e['risk-level'].textContent,'LOW');assert.match(e['reading-message'].textContent,/Location two/);
});

test('refresh reloads the same selected location and unlocks button',async()=>{
  let calls=0;const e=mount(async()=>{calls++;return response(payload());});await settle();
  e['weather-refresh'].events.click();assert.equal(e['weather-refresh'].disabled,true);await settle();
  assert.equal(calls,2);assert.equal(e['weather-refresh'].disabled,false);
  assert.match(e['weather-refresh-time'].textContent,/PHT/);
});
test('UTC risk timestamp and Manila provider timestamp show the same instant',async()=>{
  const e=mount(async()=>response(payload()));await settle();
  assert.equal(e['reading-observed-at'].textContent,e['weather-current-time'].textContent);
  assert.match(e['weather-current-time'].textContent,/09:00:00/);
});
test('risk card excludes current conditions and both upper cards stretch',()=>{
  assert.doesNotMatch(read('resident/risk_area.php'),/id="weather-temperature"/);
  for(const file of ['risk_area.php','report.php'])assert.match(read('resident/'+file),/h-100 w-100/);
  const page=read('resident/index.php');
  assert.ok(page.indexOf("'/weather_readings.php'")>page.indexOf("'/report.php'"));
});

test('one dashboard owns current values, history and refresh; no deleted component reference',()=>{
 const page=read('resident/index.php');
 assert.doesNotMatch(page,/current_weather.php/);
 const dashboard=read('resident/weather_readings.php');
 for(const id of ['weather-refresh','weather-temperature','weather-hourly-body'])assert.match(dashboard,new RegExp('id="'+id+'"'));
 assert.equal(fs.existsSync(path.join(root,'resident/current_weather.php')),false);
});
