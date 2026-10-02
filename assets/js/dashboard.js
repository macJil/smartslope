/* Page loads and refresh responses use the same escaped PHP presentation. */
(function ($, settings) {
    'use strict';
    const button = $('#refresh-weather');
    if (!button.length || !settings.locationId) return;
    const feedback = $('#weather-feedback');
    function message(text, failed) {
        feedback.removeClass('d-none alert-success alert-danger')
            .addClass(failed ? 'alert-danger' : 'alert-success').text(text);
    }
    button.on('click', function () {
        if (button.prop('disabled')) return;
        button.prop('disabled', true).text('Refreshing…');
        $('#risk-content').attr('aria-busy', 'true');
        feedback.addClass('d-none').text('');
        $.ajax({url:settings.apiUrl, method:'POST', dataType:'json', timeout:45000,
            data:{location_id:settings.locationId, csrf_token:settings.csrf}})
            .done(function (data) {
                // Fragments come only from our authenticated API and escape every data value.
                if (!data.view || typeof data.view.assessment !== 'string' || typeof data.view.readings !== 'string') {
                    message('The response was incomplete. Reload the page to check saved readings.', true);
                    return;
                }
                $('#risk-content').html(data.view.assessment);
                $('#recent-readings-body').html(data.view.readings);
                $('#current-high-count').text(data.view.high);
                $('#current-medium-count').text(data.view.medium);
                const selectAll = document.querySelector('[data-select-all="dashboard-readings"]');
                if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
                if (window.updateMapRisk) window.updateMapRisk(settings.locationId, data.assessment.current_category, data.assessment, data.latest);
                message('Weather request completed. Data status: ' + data.assessment.data_status + '.', false);
            })
            .fail(function (xhr) {
                message(xhr.responseJSON?.error || 'Weather refresh failed or timed out. Displayed readings are unchanged; reload to check for a saved update.', true);
            })
            .always(function () {
                button.prop('disabled', false).text('Refresh Weather');
                $('#risk-content').attr('aria-busy', 'false');
            });
    });
})(jQuery, window.SmartSlope);
