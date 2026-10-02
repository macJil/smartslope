/* Saved readings are rendered on page load; Refresh uses AJAX without navigating. */
(function ($, settings) {
    'use strict';
    const button = $('#refresh-weather');
    if (!button.length || !settings.locationId) return;

    function value(v, suffix) {
        return v === null || v === undefined ? 'N/A' : String(v) + (suffix || '');
    }
    function observed(v) {
        if (!v) return 'Unavailable';
        const date = new Date(v.replace(' ', 'T') + 'Z');
        return isNaN(date.getTime()) ? v : new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila', dateStyle: 'medium', timeStyle: 'short'
        }).format(date) + ' PHT';
    }
    function appendLocation(cell, location) {
        $('<div>').text(location.name || 'Unknown').appendTo(cell);
        const details = [];
        if (location.landmark) details.push('Street/Landmark: ' + location.landmark);
        if (location.purok) details.push('Purok: ' + location.purok);
        details.push('Barangay Irisan, Baguio City, Benguet, Philippines');
        if (location.lat !== null && location.lng !== null) {
            details.push('Coordinates: ' + Number(location.lat).toFixed(5) + ', ' + Number(location.lng).toFixed(5));
        }
        details.forEach(detail => $('<small>').addClass('text-muted d-block').text(detail).appendTo(cell));
    }
    function render(data) {
        const r = data.latest;
        if (!r) return;
        const panel = $('#risk-content').empty();
        const colors = {low:'success', normal:'primary', medium:'warning', high:'danger'};
        $('<div>').addClass('display-6 fw-bold text-' + (colors[r.risk_level] || 'secondary'))
            .text(r.risk_level.toUpperCase() + ' Risk').appendTo(panel);
        const details = $('<p>').appendTo(panel);
        [
            ['Location', data.location.name], ['1h Rainfall', value(r.rainfall_1h, ' mm')],
            ['24h Rainfall', value(r.rainfall_24h, ' mm')], ['72h Rainfall', value(r.rainfall_72h, ' mm')],
            ['Next 24h forecast', value(r.rainfall_forecast_24h, ' mm')],
            ['Max rain chance', value(r.precipitation_probability_24h, '%')],
            ['Modeled soil moisture 9–27 / 27–81 cm', value(r.soil_moisture_9_27cm) + ' / ' + value(r.soil_moisture_27_81cm) + ' m³/m³'],
            ['Temperature', value(r.temperature, '°C')], ['Humidity', value(r.humidity, '%')],
            ['Wind', value(r.wind_speed, ' km/h')], ['Observed', observed(r.observed_at)]
        ].forEach(([label, content]) => {
            $('<strong>').text(label + ': ').appendTo(details);
            details.append(document.createTextNode(content));
            details.append($('<br>'));
        });
        $('<p>').addClass('text-muted small').text('Preliminary rainfall screening, not an official warning. Forecast and soil moisture are model estimates.').appendTo(panel);

        const tbody = $('#recent-readings-body').empty();
        data.readings.forEach(row => {
            const tr = $('<tr>').toggleClass('text-muted', Number(row.stale) === 1).appendTo(tbody);
            appendLocation($('<td>').appendTo(tr), data.location);
            $('<td>').text(value(row.risk_level).toUpperCase()).appendTo(tr);
            ['rainfall_1h','rainfall_24h','rainfall_72h','rainfall_forecast_24h','precipitation_probability_24h'].forEach(key => $('<td>').text(value(row[key])).appendTo(tr));
            $('<td>').text(value(row.soil_moisture_9_27cm) + ' / ' + value(row.soil_moisture_27_81cm)).appendTo(tr);
            $('<td>').text(observed(row.observed_at)).appendTo(tr);
            $('<td>').text(Number(row.stale) ? 'Stale' : 'Current').appendTo(tr);
            if (settings.isAdmin) {
                const td = $('<td>').appendTo(tr);
                $('<a>').attr('href', settings.editUrl + encodeURIComponent(row.id)).addClass('btn btn-sm btn-outline-primary me-1').text('Edit').appendTo(td);
                const form = $('<form>').attr({method:'post',action:settings.deleteUrl}).addClass('d-inline').appendTo(td);
                $('<input>').attr({type:'hidden',name:'csrf_token'}).val(settings.csrf).appendTo(form);
                $('<input>').attr({type:'hidden',name:'reading_id'}).val(row.id).appendTo(form);
                $('<button>').addClass('btn btn-sm btn-outline-danger').text('Delete').appendTo(form);
                form.on('submit', () => confirm('Remove this reading?'));
            }
        });
        if (window.updateMapRisk) window.updateMapRisk(settings.locationId, r.risk_level);
    }
    button.on('click', function () {
        button.prop('disabled', true).text('Refreshing…');
        $.ajax({url:settings.apiUrl,method:'POST',dataType:'json',data:{location_id:settings.locationId,csrf_token:settings.csrf}})
            .done(render)
            .fail(xhr => alert((xhr.responseJSON && xhr.responseJSON.error) || 'The provider is unavailable; saved readings remain visible.'))
            .always(() => button.prop('disabled', false).text('Refresh Weather'));
    });
})(jQuery, window.SmartSlope);
