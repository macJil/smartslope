(() => {
    'use strict';

    const panel = document.getElementById('reading-panel');
    const message = document.getElementById('reading-message');
    const currentBody = document.getElementById('current-reading-body');
    const refreshButton = document.getElementById('weather-refresh');
    if (!panel || !message || !currentBody || !refreshButton) return;

    const riskBody = document.getElementById('risk-reading-body');
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
                hour:'2-digit', minute:'2-digit', hour12:true})} PHT`;
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
        window.SmartSlopeMap?.updateRisk(selectedLocation.location_id, level || null, stale);
    }
    function renderCurrent(rows) {
        currentBody.replaceChildren();
        const saved = Array.isArray(rows) ? rows : [];
        document.getElementById('current-reading-summary').textContent = saved.length
            ? `${saved.length} most recent saved current observations for this location.`
            : 'No current observations have been saved for this location.';
        if (!saved.length) return empty(currentBody, adminActions ? 8 : 7, 'No saved current readings yet.');
        saved.forEach((item) => {
            const tr = document.createElement('tr');
            addCell(tr, timeLabel(item.time));
            addCell(tr, present(item.temperature_2m) ? `${numeric(item.temperature_2m)} °C` : '—');
            addCell(tr, present(item.relative_humidity_2m) ? `${numeric(item.relative_humidity_2m,0)}%` : '—');
            addCell(tr, present(item.precipitation) ? `${numeric(item.precipitation,2)} mm` : '—');
            addCell(tr, `${numeric(item.rain,2)} / ${numeric(item.showers,2)} mm`);
            addCell(tr, `${numeric(item.wind_speed_10m)} / ${numeric(item.wind_gusts_10m)} km/h`);
            addCell(tr, timeLabel(item.fetched_at));
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
    function renderAdmin(rows) {
        if (!riskBody) return;
        riskBody.replaceChildren();
        const saved = Array.isArray(rows) ? rows : [];
        if (!saved.length) return empty(riskBody, 4, 'No active rainfall summaries for this location.');
        saved.forEach((reading) => {
            const tr = document.createElement('tr');
            addCell(tr, timeLabel(reading.observed_at));
            addCell(tr, `${numeric(reading.rainfall_1h_mm,2)} / ${numeric(reading.rainfall_24h_mm,2)} / ${numeric(reading.rainfall_72h_mm,2)} mm`);
            addCell(tr, String(reading.risk_level || 'Unavailable').toUpperCase());
            const actions = addCell(tr, ''); actions.textContent = '';
            const details = document.createElement('details');
            const summary = document.createElement('summary'); summary.textContent = 'Edit rainfall';
            details.appendChild(summary);
            const form = createForm(reading, 'update');
            for (const [field,label] of [['rainfall_1h_mm','1 hour'],['rainfall_24h_mm','24 hours'],
                ['rainfall_72h_mm','72 hours']]) {
                const group = document.createElement('label'); group.className = 'd-block small mt-2';
                group.textContent = `${label} (mm)`;
                const input = document.createElement('input');
                input.className = 'form-control form-control-sm'; input.type = 'number'; input.name = field;
                input.min = '0'; input.max = '99999.99'; input.step = '0.01'; input.required = true;
                input.value = reading[field] ?? '';
                group.appendChild(input); form.appendChild(group);
            }
            const save = document.createElement('button'); save.type = 'submit';
            save.className = 'btn btn-sm btn-primary mt-2'; save.textContent = 'Save correction';
            form.appendChild(save); details.appendChild(form); actions.appendChild(details);
            const removeForm = createForm(reading, 'delete'); removeForm.className = 'mt-2';
            const remove = document.createElement('button'); remove.type = 'submit';
            remove.className = 'btn btn-sm btn-outline-danger'; remove.textContent = 'Delete from active readings';
            removeForm.appendChild(remove); actions.appendChild(removeForm);
            riskBody.appendChild(tr);
        });
    }
    function showData(data) {
        document.getElementById('weather-location-label').textContent = data.location.location_name +
            (data.location.purok_zone ? ` — ${data.location.purok_zone}` : '');
        const latest = Array.isArray(data.current_readings) ? data.current_readings[0] : null;
        document.getElementById('weather-refresh-time').textContent = latest
            ? `Latest saved observation: ${timeLabel(latest.time)}. Fetched: ${timeLabel(latest.fetched_at)}.`
            : 'No saved current observations yet.';
        renderRisk(data.rainfall, data.stale === true, data.alert);
        renderCurrent(data.current_readings);
        renderAdmin(data.risk_readings);
    }
    function errorMessage(code) {
        return ({authentication_required:'Your session expired. Sign in again.', invalid_csrf_token:'Reload the page and try again.',
            location_not_found:'That location is no longer active.', location_coordinates_missing:'This location needs valid coordinates.',
            database_unavailable:'Saved readings are temporarily unavailable.',
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
        status('Requesting Open-Meteo and saving the current observations…');
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
            else status(`${data.saved_observations ?? 0} current and hourly observations saved or updated for ${data.location.location_name}.`);
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
    document.addEventListener('smartslope:location-selected',(event) => {
        selectedLocation = event.detail.location;
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
        empty(currentBody,adminActions ? 8 : 7,'Loading selected location…');
        empty(riskBody,4,'Loading selected location…');
        renderRisk(null,true,null);
        const autoRefresh = panel.dataset.skipInitialRefresh !== '1';
        panel.dataset.skipInitialRefresh = '0';
        loadStored(autoRefresh);
    });
    refreshButton.addEventListener('click', loadWeather);
    if (retryButton) retryButton.addEventListener('click', loadWeather);
    empty(currentBody,adminActions ? 8 : 7,'Select a map location to load readings.');
})();
