(() => {
    'use strict';

    const panel = document.getElementById('reading-panel');
    const message = document.getElementById('reading-message');
    const currentBody = document.getElementById('current-reading-body');
    const refreshButton = document.getElementById('weather-refresh');
    if (!panel || !message || !currentBody || !refreshButton) return;

    const adminActions = document.getElementById('risk-reading-actions');
    const download = document.getElementById('readings-download');
    const retryButton = document.getElementById('weather-retry');
    let selectedLocation = null;
    let activeRequest = null;
    let requestVersion = 0;

    const present = (value) => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value));
    const numeric = (value, decimals = 1) => present(value) ? Number(value).toFixed(decimals) : '—';
    function timeLabel(value) {
        if (!value) return '—';
        const input = String(value);
        const parsed = new Date(/(?:Z|[+-]\d{2}:?\d{2})$/i.test(input) ? input : `${input}Z`);
        return Number.isNaN(parsed.getTime()) ? '—' :
            `${parsed.toLocaleString('en-PH', {timeZone:'Asia/Manila', year:'numeric', month:'short', day:'2-digit',
                hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true})} PHT`;
    }
    function status(text, error = false) {
        message.textContent = text;
        message.className = error ? 'small text-danger' : 'small text-muted';
    }
    function addCell(row, value) {
        const cell = document.createElement('td');
        cell.textContent = String(value ?? '—');
        row.appendChild(cell);
        return cell;
    }
    function empty(body, cols, text) {
        if (!body) return;
        body.replaceChildren();
        const row = document.createElement('tr');
        const cell = addCell(row, text);
        cell.colSpan = cols;
        cell.className = 'text-muted';
        body.appendChild(row);
    }
    function renderRisk(rainfall, stale, alert) {
        const values = rainfall || {};
        const level = values.risk_level;
        const badge = document.getElementById('risk-level');
        const colors = {low:'text-bg-success', normal:'text-bg-primary', medium:'text-bg-warning', high:'text-bg-danger'};
        badge.className = `badge ${stale && level ? 'text-bg-warning' : (colors[level] || 'text-bg-secondary')}`;
        badge.textContent = level ? `${stale ? 'STALE — ' : ''}${level.toUpperCase()}` : 'UNAVAILABLE';
        document.getElementById('risk-explanation').textContent = values.risk_explanation ||
            'Complete saved rainfall data are needed for a risk assessment.';
        for (const [key, id] of [['rainfall_1h_mm','rainfall-1h'],['rainfall_24h_mm','rainfall-24h'],
            ['rainfall_72h_mm','rainfall-72h']]) {
            document.getElementById(id).textContent = present(values[key]) ? `${numeric(values[key],2)} mm` : 'Unavailable';
        }
        document.getElementById('reading-observed-at').textContent = timeLabel(values.observed_at);
        const alertBox = document.getElementById('active-alert');
        alertBox.hidden = !(!stale && alert);
        alertBox.textContent = !stale && alert ? `Prototype ${alert.risk_level.toUpperCase()} alert for this location.` : '';
        window.SmartSlopeMap?.updateRisk(selectedLocation?.location_id, level || null, stale);
    }
    function renderCurrent(rows) {
        currentBody.replaceChildren();
        const saved = Array.isArray(rows) ? rows : [];
        document.getElementById('current-reading-summary').textContent = saved.length
            ? `${saved.length} most recent saved reading logs for this location.`
            : 'No reading logs have been saved for this location.';
        if (!saved.length) return empty(currentBody, adminActions ? 10 : 9, 'No saved reading logs yet.');
        saved.forEach((item) => {
            const tr = document.createElement('tr');
            addCell(tr, timeLabel(item.time));
            addCell(tr, present(item.temperature_2m) ? `${numeric(item.temperature_2m)} °C` : '—');
            addCell(tr, present(item.relative_humidity_2m) ? `${numeric(item.relative_humidity_2m,0)}%` : '—');
            addCell(tr, present(item.precipitation) ? `${numeric(item.precipitation,2)} mm` : '—');
            addCell(tr, `${numeric(item.rain,2)} / ${numeric(item.showers,2)} mm`);
            addCell(tr, `${numeric(item.wind_speed_10m)} / ${numeric(item.wind_gusts_10m)} km/h`);
            addCell(tr, timeLabel(item.fetched_at));
            addCell(tr, `${numeric(item.rainfall_1h_mm,2)} / ${numeric(item.rainfall_24h_mm,2)} / ${numeric(item.rainfall_72h_mm,2)} mm`);
            addCell(tr, item.risk_level ? `${Number(item.is_stale) ? 'STALE — ' : ''}${item.risk_level.toUpperCase()}` : 'UNAVAILABLE');
            if (adminActions) {
                const actions = addCell(tr, '');
                const details = document.createElement('details');
                const summary = document.createElement('summary'); summary.textContent = 'Edit';
                details.appendChild(summary);
                const form = createForm(item, 'current_update');
                for (const [field,label,source] of [
                    ['temperature','Temperature (°C)','temperature_2m'],
                    ['humidity','Humidity (%)','relative_humidity_2m'],
                    ['precipitation','Precipitation (mm)','precipitation'],
                    ['rain','Rain (mm)','rain'],['showers','Showers (mm)','showers'],
                    ['wind','Wind (km/h)','wind_speed_10m'],['gusts','Gusts (km/h)','wind_gusts_10m']
                ]) {
                    const group = document.createElement('label'); group.className = 'd-block small mt-2';
                    group.textContent = label;
                    const input = document.createElement('input');
                    input.type = 'number'; input.name = field; input.step = '0.01'; input.required = true;
                    input.className = 'form-control form-control-sm'; input.value = item[source] ?? '';
                    if (field !== 'temperature') input.min = '0';
                    group.appendChild(input); form.appendChild(group);
                }
                const save = document.createElement('button'); save.type = 'submit';
                save.className = 'btn btn-sm btn-primary mt-2'; save.textContent = 'Save';
                form.appendChild(save); details.appendChild(form); actions.appendChild(details);
                const removeForm = createForm(item, 'current_delete'); removeForm.className = 'mt-2';
                const remove = document.createElement('button'); remove.type = 'submit';
                remove.className = 'btn btn-sm btn-outline-danger'; remove.textContent = 'Delete';
                removeForm.appendChild(remove); actions.appendChild(removeForm);
            }
            currentBody.appendChild(tr);
        });
    }
    function hidden(form, name, value) {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = name; input.value = String(value);
        form.appendChild(input);
    }
    function createForm(reading, action) {
        const form = document.createElement('form');
        form.method = 'post'; form.action = adminActions.dataset.saveUrl;
        hidden(form, 'csrf_token', adminActions.dataset.csrfToken);
        hidden(form, 'location_id', selectedLocation.location_id);
        hidden(form, action.startsWith('current_') ? 'observation_id' : 'reading_id',
            action.startsWith('current_') ? reading.observation_id : reading.reading_id);
        hidden(form, 'action', action);
        return form;
    }
    function showData(data) {
        document.getElementById('weather-location-label').textContent = data.location.location_name +
            (data.location.purok_zone ? ` — ${data.location.purok_zone}` : '');
        const latest = Array.isArray(data.current_readings) ? data.current_readings[0] : null;
        document.getElementById('weather-refresh-time').textContent = latest
            ? `Latest saved observation: ${timeLabel(latest.time)}. Fetched: ${timeLabel(latest.fetched_at)}.`
            : 'No saved reading logs yet.';
        renderRisk(data.rainfall, data.stale === true, data.alert);
        renderCurrent(data.current_readings);

    }
    function errorMessage(code) {
        return ({authentication_required:'Your session expired. Sign in again.', invalid_csrf_token:'Reload the page and try again.',
            location_not_found:'That location is no longer active.', location_coordinates_missing:'This location needs valid coordinates.',
            database_unavailable:'Saved readings are temporarily unavailable.',
            outside_study_area:'Click inside the Irisan outline.',
            invalid_coordinates:'Select a valid map point.',
            weather_save_failed:'The reading could not be saved. Check the weather_fetches migration and PHP error log.',
            weather_history_unavailable:'The provider returned insufficient recent history.',
            weather_provider_unavailable:'Open-Meteo is temporarily unavailable. Saved readings remain displayed.'})[code] ||
            'Could not load new weather data. Saved readings remain displayed.';
    }
    async function loadStored(autoRefresh = true) {
        const version = ++requestVersion;
        const id = selectedLocation?.location_id;
        if (activeRequest) activeRequest.abort();
        if (!id) return;
        status('Loading saved readings…');
        try {
            const response = await fetch(`${panel.dataset.storedUrl}?location_id=${encodeURIComponent(id)}`, {credentials:'same-origin'});
            const payload = await response.json();
            if (version !== requestVersion) return;
            if (!response.ok || !payload.data) throw new Error(payload.error || 'invalid_response');
            showData(payload.data);
            status('Saved readings loaded. Requesting the latest provider data…');
        } catch (error) {
            if (version === requestVersion) status(errorMessage(error.message), true);
        }
        if (version === requestVersion && selectedLocation?.location_id === id) {
            if (autoRefresh) loadWeather();
            else status('Saved readings loaded. Use Refresh to request Open-Meteo again.');
        }
    }
    async function loadWeather() {
        const version = ++requestVersion;
        const id = selectedLocation?.location_id;
        if (!id) return;
        if (activeRequest) activeRequest.abort();
        refreshButton.disabled = true;
        refreshButton.textContent = 'Refreshing…';
        if (retryButton) retryButton.hidden = true;
        status('Requesting Open-Meteo and saving a new reading log…');
        try {
            const payload = await new Promise((resolve,reject) => {
                activeRequest = jQuery.ajax({
                    url: panel.dataset.weatherUrl, method:'POST', dataType:'json', timeout:30000,
                    data: new URLSearchParams({location_id:String(id), csrf_token:document.getElementById('weather-csrf-token').value}).toString(),
                    success: resolve,
                    error: (xhr,kind) => {
                        const error = new Error(xhr.responseJSON?.error || 'weather_provider_unavailable');
                        if (kind === 'abort') error.name = 'AbortError';
                        reject(error);
                    }
                });
            });
            if (version !== requestVersion) return;
            if (!payload.data) throw new Error(payload.error || 'invalid_response');
            const data = payload.data;
            showData(data);
            if (data.persistence_warning) status('Provider data was returned but could not be saved. Showing stored readings.',true);
            else if (data.stale) status('The provider observation is stale. Check its observation time before using the risk status.',true);
            else status(`New reading log saved for ${data.location.location_name}.`);
        } catch (error) {
            if (error.name === 'AbortError' || version !== requestVersion) return;
            status(errorMessage(error.message),true);
            if (retryButton) retryButton.hidden = false;
        } finally {
            if (version === requestVersion) {
                refreshButton.disabled = false;
                refreshButton.textContent = 'Refresh readings';
            }
        }
    }
    function selectLocation(location) {
        selectedLocation = location;
        document.getElementById('selected-location-name').textContent = selectedLocation.location_name;
        const reportId = document.getElementById('report-location');
        if (reportId) {
            reportId.value = String(selectedLocation.location_id);
            document.getElementById('report-location-name').value = selectedLocation.location_name;
            document.getElementById('report-submit').disabled = false;
        }
        if (download) {
            download.href = `${download.dataset.downloadBaseUrl}?location_id=${encodeURIComponent(selectedLocation.location_id)}`;
            download.classList.remove('disabled');
            download.setAttribute('aria-disabled','false');
            download.removeAttribute('tabindex');
        }
        refreshButton.disabled = false;
        empty(currentBody,adminActions ? 10 : 9,'Loading selected location…');
        renderRisk(null,true,null);
        const autoRefresh = panel.dataset.skipInitialRefresh !== '1';
        panel.dataset.skipInitialRefresh = '0';
        loadStored(autoRefresh);
    }
    document.addEventListener('smartslope:location-selected', event => selectLocation(event.detail.location));
    document.addEventListener('smartslope:point-selected', async (event) => {
        const version = ++requestVersion;
        if (activeRequest) activeRequest.abort();
        selectedLocation = null;
        refreshButton.disabled = true;
        if (retryButton) retryButton.hidden = true;
        const report = document.getElementById('report-submit');
        if (report) report.disabled = true;
        if (download) { download.removeAttribute('href'); download.classList.add('disabled'); }
        empty(currentBody,adminActions ? 10 : 9,'Loading clicked point…');
        document.getElementById('selected-location-name').textContent = 'Resolving clicked point…';
        renderRisk(null,true,null);
        status('Resolving the clicked coordinates…');
        try {
            const response = await fetch(panel.dataset.mapLocationUrl, {
                method:'POST', credentials:'same-origin',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:new URLSearchParams({latitude:event.detail.latitude,longitude:event.detail.longitude,
                    csrf_token:document.getElementById('weather-csrf-token').value}).toString()
            });
            const payload = await response.json();
            if (version !== requestVersion) return;
            if (!response.ok || !payload.data) throw new Error(payload.error || 'database_unavailable');
            window.SmartSlopeMap?.registerLocation?.(payload.data);
            const note=document.getElementById('map-selection-message');
            if (note) note.textContent=`Selected ${payload.data.location_name}. Readings are saved for this point.`;
            panel.dataset.skipInitialRefresh = '0';
            selectLocation(payload.data);
        } catch (error) {
            if (version !== requestVersion) return;
            status(errorMessage(error.message),true);
            const note=document.getElementById('map-selection-message');
            if (note) note.textContent=errorMessage(error.message);
        }
    });
    refreshButton.addEventListener('click', loadWeather);
    if (retryButton) retryButton.addEventListener('click', loadWeather);
    empty(currentBody,adminActions ? 10 : 9,'Select a map location to load readings.');
})();
