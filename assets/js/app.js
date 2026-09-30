(() => {
    'use strict';

    const locationSelect = document.getElementById('risk-location-select');
    const readingPanel = document.getElementById('reading-panel');
    const readingMessage = document.getElementById('reading-message');
    const historyBody = document.getElementById('weather-hourly-body');
    const retryButton = document.getElementById('weather-retry');

    if (!locationSelect || !readingPanel || !readingMessage || !historyBody) return;

    const refreshButton = document.getElementById('weather-refresh');
    let activeRequest = null;
    let requestTimer = null;
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
        cell.colSpan = 19;
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
            'weather-soil-moisture-shallow',
            'weather-soil-moisture-deep',
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

        document.getElementById('weather-soil-moisture-shallow').textContent =
            isAvailable(current.soil_moisture_0_to_1cm)
                ? `${number(current.soil_moisture_0_to_1cm, 3)} m³/m³`
                : '—';

        document.getElementById('weather-soil-moisture-deep').textContent =
            isAvailable(current.soil_moisture_27_to_81cm)
                ? `${number(current.soil_moisture_27_to_81cm, 3)} m³/m³`
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
            addCell(row, number(reading.soil_moisture_0_to_1cm, 3));
            addCell(row, number(reading.soil_moisture_1_to_3cm, 3));
            addCell(row, number(reading.soil_moisture_3_to_9cm, 3));
            addCell(row, number(reading.soil_moisture_9_to_27cm, 3));
            addCell(row, number(reading.soil_moisture_27_to_81cm, 3));
            addCell(row, isAvailable(reading.weather_code)
                ? String(Math.round(Number(reading.weather_code)))
                : '—');

            historyBody.appendChild(row);
        });

        const summary = document.getElementById('weather-hourly-summary');
        if (summary) {
            summary.textContent =
                `${rows.length} hourly readings loaded. API refreshed ${timeLabel(retrievedAt)}.`;
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

    async function loadWeather() {
        const version = ++requestVersion;
        const locationId = locationSelect.value;
        if (activeRequest) activeRequest.abort();

        refreshButton.disabled = !locationId;
        if (!locationId) {
            refreshButton.textContent = 'Refresh readings';
            document.getElementById('weather-location-label').textContent = 'Select a location above.';
            document.getElementById('weather-refresh-time').textContent = 'Not refreshed yet.';
            if (retryButton) retryButton.hidden = true;
            setMessage('Choose a location with saved coordinates to load weather readings.');
            setUnavailable();
            resetHistory('Choose a location above to load its hourly readings.');
            return;
        }

        refreshButton.disabled = true;
        refreshButton.textContent = 'Refreshing…';
        document.getElementById('weather-refresh-time').textContent = 'Fetching the latest provider data…';
        setUnavailable();

        if (retryButton) retryButton.hidden = true;
        setMessage('Loading weather data and calculating the prototype risk level…');
        resetHistory('Loading hourly readings…');

        const requestBody = new URLSearchParams({
            location_id: locationId,
            csrf_token: document.getElementById('weather-csrf-token').value
        });

        try {
            const payload = await new Promise((resolve, reject) => {
                activeRequest = jQuery.ajax({
                    url: locationSelect.dataset.weatherUrl,
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

            if (version !== requestVersion || locationSelect.value !== locationId) return;

            const data = payload.data;
            document.getElementById('weather-location-label').textContent = data.location.location_name + (data.location.purok_zone ? ` — ${data.location.purok_zone}` : '');
            document.getElementById('weather-refresh-time').textContent = `Last refreshed: ${timeLabel(data.retrieved_at)}. Provider data: ${timeLabel(data.current?.time)}.`;
            setRisk(data.rainfall, data.stale === true);
            setCurrent(data.current);
            setHistory(data.hourly, data.retrieved_at);

            if (data.stale) {
                setMessage(
                    `Weather API unavailable. Showing the last saved Open-Meteo risk summary for ${data.location.location_name}, observed ${timeLabel(data.rainfall.observed_at)}. Current conditions are unavailable.`,
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
            if (version !== requestVersion || locationSelect.value !== locationId) return;

            if (retryButton) retryButton.hidden = false;
            setUnavailable();
            setMessage(messageFor(error.message), true);
            resetHistory('No hourly readings loaded.');
            document.getElementById('weather-refresh-time').textContent = 'Refresh failed. No current readings loaded.';
        } finally {
            if (version === requestVersion) {
                refreshButton.disabled = !locationSelect.value;
                refreshButton.textContent = 'Refresh readings';
            }
        }
    }

    locationSelect.addEventListener('change', () => {
        ++requestVersion;
        document.getElementById('weather-location-label').textContent = 'Loading selected location…';
        document.getElementById('weather-refresh-time').textContent = '';
        if (activeRequest) activeRequest.abort();
        setUnavailable();
        resetHistory('Loading the selected location…');
        window.clearTimeout(requestTimer);
        requestTimer = window.setTimeout(loadWeather, 200);
    });

    refreshButton.addEventListener('click', () => {
        window.clearTimeout(requestTimer);
        loadWeather();
    });
    refreshButton.disabled = !locationSelect.value;
    if (retryButton) retryButton.addEventListener('click', loadWeather);

    if (locationSelect.value) {
        loadWeather();
    } else {
        setUnavailable();
        resetHistory('No weather readings loaded. Select a coordinate-ready location after one is added.');
    }
})();