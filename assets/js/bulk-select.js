(function () {
    'use strict';

    function itemsFor(group) {
        return Array.from(document.querySelectorAll('[data-bulk-item]'))
            .filter(item => item.dataset.bulkItem === group);
    }

    document.querySelectorAll('[data-select-all]').forEach(selectAll => {
        selectAll.addEventListener('change', () => {
            itemsFor(selectAll.dataset.selectAll).forEach(item => { item.checked = selectAll.checked; });
        });
    });

    document.addEventListener('change', event => {
        const item = event.target.closest('[data-bulk-item]');
        if (!item) return;
        const selectAll = document.querySelector(`[data-select-all="${item.dataset.bulkItem}"]`);
        if (!selectAll) return;
        const items = itemsFor(item.dataset.bulkItem);
        const checkedCount = items.filter(candidate => candidate.checked).length;
        selectAll.checked = checkedCount === items.length && items.length > 0;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < items.length;
    });

    document.querySelectorAll('form[data-bulk-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            const selected = Array.from(document.querySelectorAll('[data-bulk-item]:checked'))
                .filter(item => item.form === form).length;
            if (!selected) {
                event.preventDefault();
                window.alert('Select at least one item first.');
                return;
            }
            const message = form.dataset.bulkConfirm.replace('%d', String(selected));
            if (!window.confirm(message)) event.preventDefault();
        });
    });
})();
