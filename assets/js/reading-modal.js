/* One accessible Bootstrap dialog serves details rendered on page load or after AJAX. */
(function () {
    'use strict';
    const dialog = document.getElementById('readingWeatherModal');
    if (!dialog) return;
    const title = dialog.querySelector('#readingWeatherTitle');
    const body = dialog.querySelector('#readingWeatherBody');
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-weather-dialog]');
        if (!button) return;
        const template = document.getElementById(button.dataset.templateId);
        if (!(template instanceof HTMLTemplateElement)) return;
        title.textContent = button.dataset.detailsTitle || 'Weather details';
        body.replaceChildren(template.content.cloneNode(true));
        bootstrap.Modal.getOrCreateInstance(dialog).show(button);
    });
    dialog.addEventListener('hidden.bs.modal', function () {
        body.replaceChildren();
    });
})();
