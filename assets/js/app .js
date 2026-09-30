(() => {
    'use strict';

    const readingPanel = document.getElementById('reading-panel');
    const readingMessage = document.getElementById('reading-message');
    const historyBody = document.getElementById('weather-hourly-body');
    const retryButton = document.getElementById('weather-retry');

    if (!readingPanel || !readingMessage || !historyBody) return;

    const refreshButton = document.getElementById('weather-refresh');
    let activeRequest = null;
    let selectedLocation = null;
    let requestVersion = 0;

    function isAvailable(value) {
        return value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value));
    }

    function number(value, decimals = 1) {
        return isAvailable(value) ? Number(value).toFixed(decimals) : '—';
    }

    function timeLabel(value) {
        if (!value) return '—';
        const time = String(value);
        const withZone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(time) ? time : `${time}+08:00`;
        const parsed = new Date(withZone);
        return Number.isNaN(parsed.getTime())
            ? time
            : `${parsed.toLocaleString('en-PH', { timeZone: 'Asia/Manila', year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true })} PHT`;
    }

    function setMessage(message, isError = false) {
        readingMessage.textContent = message;
        readingMessage.className = isError ? 'small text-danger' : 'small text-muted';
    }

    function resetHistory(message) {
        historyBody.replaceChildren();
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 14;
        cell.className = 'text-center text-muted';
        cell.textContent = message;
        row.appendChild(cell);
        historyBody.appendChild(row);

        const summary = document.getElementById('weather-hourly-summary');
        if (summary) summary.textContent = message;
    }

    function setRisk(rainfall, isStale) {
        const risk = rainfall.risk_level;
        document.getElementById('risk-explanation').textContent = rainfall.risk_explanation || 'Risk cannot be assessed without complete rainfall data.';
        const classes = {
            low: 'text-bg-success',
            normal: 'text-bg-primary',
            medium: 'text-bg-warning',
            high: 'text-bg-danger'
        };
        const badge = document.getElementById('risk-level');
        badge.className = `badge ${isStale ? 'text-bg-warning' : (classes[risk] || 'text-bg-secondary')}`;
        badge.textContent = risk
            ? `${isStale ? 'STALE — ' : ''}${risk.toUpperCase()}`
            : 'UNAVAILABLE';

        document.getElementById('rainfall-1h').textContent =
            isAvailable(rainfall.rainfall_1h_mm)
                ? `${number(rainfall.rainfall_1h_mm, 2)} mm`
                : 'Unavailable';

        document.getElementById('rainfall-24h').textContent =
            isAvailable(rainfall.rainfall_24h_mm)
                ? `${number(rainfall.rainfall_24h_mm, 2)} mm`
                : 'Unavailable';

        document.getElementById('rainfall-72h').textContent =
            isAvailable(rainfall.rainfall_72h_mm)
                ? `${number(rainfall.rainfall_72h_mm, 2)} mm`
                : 'Unavailable';

        document.getElementById('reading-observed-at').textContent =
            timeLabel(rainfall.observed_at);
    }

    function setUnavailable() {
        setRisk({
            risk_level: null,
            rainfall_1h_mm: null,
            rainfall_24h_mm: null,
            rainfall_72h_mm: null,
            observed_at: null
        }, false);

        setCurrent(null);
        const alertEl = document.getElementById('active-alert');
        if (alertEl) { alertEl.textContent = ''; alertEl.hidden = true; }
    }

    function setCurrent(current) {
        const fields = [
            'weather-temperature',
            'weather-apparent-temperature',
            'weather-humidity',
            'weather-precipitation',
            'weather-rain-showers',
            'weather-wind',
            'weather-wind-direction',
            'weather-cloud-cover',
            'weather-code',
            'weather-current-time'
        ];

        if (!current) {
            fields.forEach((id) => {
                document.getElementById(id).textContent = 'Unavailable';
            });
            return;
        }

        document.getElementById('weather-temperature').textContent =
            isAvailable(current.temperature_2m)
                ? `${number(current.temperature_2m)} °C`
                : '—';

        document.getElementById('weather-apparent-temperature').textContent =
            isAvailable(current.apparent_temperature)
                ? `${number(current.apparent_temperature)} °C`
                : '—';

        document.getElementById('weather-humidity').textContent =
            isAvailable(current.relative_humidity_2m)
                ? `${number(current.relative_humidity_2m, 0)}%`
                : '—';

        const intervalMinutes = isAvailable(current.interval)
            ? `${Math.round(Number(current.interval) / 60)}-minute interval`
            : 'API interval';

        document.getElementById('weather-precipitation').textContent =
            isAvailable(current.precipitation)
                ? `${number(current.precipitation, 2)} mm (${intervalMinutes})`
                : '—';

        document.getElementById('weather-rain-showers').textContent =
            `${number(current.rain, 2)} mm rain / ${number(current.showers, 2)} mm showers`;

        document.getElementById('weather-wind').textContent =
            `${number(current.wind_speed_10m)} km/h / ${number(current.wind_gusts_10m)} km/h gusts`;

        document.getElementById('weather-wind-direction').textContent =
            isAvailable(current.wind_direction_10m)
                ? `${number(current.wind_direction_10m, 0)}°`
                : '—';

        document.getElementById('weather-cloud-cover').textContent =
            isAvailable(current.cloud_cover)
                ? `${number(current.cloud_cover, 0)}%`

                : '—';

        document.getElementById('weather-code').textContent =
            isAvailable(current.weather_code)
                ? String(Math.round(Number(current.weather_code)))
                : '—';

        document.getElementById('weather-current-time').textContent =
            timeLabel(current.time);
    }

    function addCell(row, value) {
        const cell = document.createElement('td');
        cell.textContent = value === null || value === undefined
            ? '—'
            : String(value);
        row.appendChild(cell);
    }

    function setHistory(rows, retrievedAt) {
        historyBody.replaceChildren();

        if (!Array.isArray(rows) || rows.length === 0) {
            resetHistory('The provider did not return hourly readings for this location.');
            return;
        }

        rows.slice().reverse().forEach((reading) => {
            const row = document.createElement('tr');

            addCell(row, timeLabel(reading.time));
            addCell(row, number(reading.temperature_2m));
            addCell(row, number(reading.relative_humidity_2m, 0));
            addCell(row, number(reading.apparent_temperature));
            addCell(row, number(reading.precipitation, 2));
            addCell(row, number(reading.rain, 2));
            addCell(row, number(reading.showers, 2));
            addCell(row, number(reading.wind_speed_10m));
            addCell(row, number(reading.wind_direction_10m, 0));
            addCell(row, number(reading.wind_gusts_10m));
            addCell(row, number(reading.cloud_cover, 0));
            addCell(row, number(reading.pressure_msl, 1));
            addCell(row, number(reading.surface_pressure, 1));
            addCell(row, isAvailable(reading.weather_code)
                ? String(Math.round(Number(reading.weather_code)))
                : '—');

            historyBody.appendChild(row);
        });

        const summary = document.getElementById('weather-hourly-summary');
        if (summary) {
            summary.textContent =
                `${rows.length} saved hourly observations. Last fetched ${timeLabel(retrievedAt)}.`;
        }
    }

    function messageFor(errorCode) {
        const messages = {
            location_coordinates_missing:
                'This location needs latitude and longitude before weather can load.',
            location_not_found:
                'The selected location is inactive or unavailable.',
            authentication_required:
                'Your session expired. Sign in again to load weather readings.',
            weather_provider_unavailable:
                'The weather provider is temporarily unavailable. Try again shortly.',
            database_unavailable:
                'Location data could not be read. Check the local MySQL connection.',
            weather_history_unavailable:
                'The API returned no recent hourly history for this location.',
            invalid_csrf_token:
                'Your security token expired. Reload this page and try again.',
            invalid_location_id:
                'Select a valid location and try again.',
            invalid_response:
                'The weather endpoint did not return JSON. Check the PHP error log and endpoint path.'
        };

        return messages[errorCode] ||
            'Weather readings could not be loaded. Check the location coordinates and try again.';
    }

    function showData(data, stored = false) {
        document.getElementById('weather-location-label').textContent =
            data.location.location_name + (data.location.purok_zone ? ` — ${data.location.purok_zone}` : '');
        document.getElementById('weather-refresh-time').textContent =
            data.retrieved_at ? `Last fetched: ${timeLabel(data.retrieved_at)}. Provider observation: ${timeLabel(data.current?.time)}.` : 'No saved provider observations yet.';
        setRisk(data.rainfall || {}, data.stale === true);
        setCurrent(data.current);
        setHistory(data.hourly, data.retrieved_at);
        const alertEl = document.getElementById('active-alert');
        if (alertEl) {
            alertEl.textContent = !data.stale && data.alert ? `Prototype ${data.alert.risk_level.toUpperCase()} alert for this location.` : '';
            alertEl.hidden = !(!data.stale && data.alert);
        }
        if (window.SmartSlopeMap) window.SmartSlopeMap.updateRisk(data.location.location_id, data.rainfall?.risk_level || null, data.stale === true);
        if (stored) setMessage(data.rainfall
            ? `Showing saved readings for ${data.location.location_name}${data.stale ? ' (stale observation)' : ''}. Refresh to request new provider data.`
            : 'No saved risk reading for this location. Refresh to request provider data.', data.stale === true);
    }

    async function loadStored() {
        const version = ++requestVersion;
        const locationId = selectedLocation?.location_id;
        if (activeRequest) activeRequest.abort();
        if (!locationId) return;
        setUnavailable();
        if (retryButton) retryButton.hidden = true;
        setMessage('Loading saved readings…');
        resetHistory('Loading saved hourly observations…');
        try {
            const response=await fetch(`${readingPanel.dataset.storedUrl}?location_id=${encodeURIComponent(locationId)}`, {credentials:'same-origin'});
            const payload=await response.json();
            if (!response.ok || !payload.data) throw new Error(payload.error || 'invalid_response');
            if (version !== requestVersion) return;
            showData(payload.data,true);
        } catch (error) {
            if (version !== requestVersion) return;
            setMessage(messageFor(error.message),true);
            resetHistory('Saved observations unavailable.');
        }
    }

    async function loadWeather() {
        const version=++requestVersion;
        const locationId=selectedLocation?.location_id;
        if (!locationId) return;
        if (activeRequest) activeRequest.abort();
        refreshButton.disabled=true;
        refreshButton.textContent='Refreshing…';
        setMessage('Fetching provider readings and saving them in the database…');
        if (retryButton) retryButton.hidden=true;

        const requestBody = new URLSearchParams({
            location_id: String(locationId),
            csrf_token: document.getElementById('weather-csrf-token').value
        });

        try {
            const payload = await new Promise((resolve, reject) => {
                activeRequest = jQuery.ajax({
                    url: readingPanel.dataset.weatherUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: requestBody.toString(),
                    timeout: 30000,
                    success: resolve,
                    error: (xhr, status) => {
                        const error = new Error(xhr.responseJSON?.error ||
                            (status === 'parsererror' ? 'invalid_response' : 'weather_provider_unavailable'));
                        if (status === 'abort') error.name = 'AbortError';
                        reject(error);
                    }
                });
            });
            if (!payload.data) throw new Error(payload.error || 'invalid_response');

            if (version !== requestVersion) return;

            const data = payload.data;
            showData(data);

            if (data.stale) {
                setMessage(
                    `Weather provider observation is stale. Showing the saved Open-Meteo risk summary for ${data.location.location_name}, observed ${timeLabel(data.rainfall.observed_at)}. Check the provider observation time before using this status.`,
                    true
                );
            } else if (data.persistence_warning) {
                const area = data.location.purok_zone
                    ? ` — ${data.location.purok_zone}`
                    : '';

                setMessage(
                    `Live weather loaded for ${data.location.location_name}${area}, but the readings could not be saved to the database. Please notify the administrator.`,
                    true
                );
            } else {
                const area = data.location.purok_zone
                    ? ` — ${data.location.purok_zone}`
                    : '';

                setMessage(`Weather readings loaded for ${data.location.location_name}${area}. ${data.saved_observations ?? 0} observations saved or updated in the database.`);
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (version !== requestVersion) return;

            if (retryButton) retryButton.hidden = false;
            setMessage(messageFor(error.message), true);
        } finally {
            if (version === requestVersion) {
                refreshButton.disabled = !selectedLocation;
                refreshButton.textContent = 'Refresh readings';
            }
        }
    }

    document.addEventListener('smartslope:location-selected', (event) => {
        selectedLocation=event.detail.location;
        document.getElementById('selected-location-name').textContent = selectedLocation.location_name;
        const formLocation=document.getElementById('report-location');
        if (formLocation) {
            formLocation.value=String(selectedLocation.location_id);
            document.getElementById('report-location-name').value=selectedLocation.location_name;
            document.getElementById('report-submit').disabled=false;
        }
        refreshButton.disabled=false;
        loadStored();
    });

    refreshButton.addEventListener('click', loadWeather);
    if (retryButton) retryButton.addEventListener('click', loadWeather);
    setUnavailable();
    resetHistory('Select a location marker to view saved readings.');
})();
