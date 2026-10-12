/**
 * Themed date picker for `<input data-datepicker>` (flatpickr).
 *
 * Displays DD-MM-YYYY for people while still submitting ISO Y-m-d, which is
 * what the server validates (`date_format:Y-m-d`). Loaded only on the pages
 * that opt in via $pageScripts (student registration + edit), together with
 * the vendored flatpickr runtime and datepicker.css.
 *
 * appendTo: document.body is critical — it moves the calendar popup out of
 * the grid/flex DOM so it never pushes sibling cells down in Chrome.
 */
(function () {
    'use strict';

    function init() {
        if (typeof flatpickr !== 'function') {
            return;
        }
        document.querySelectorAll('input[data-datepicker]').forEach(function (el) {
            if (el._flatpickr) {
                return;
            }
            flatpickr(el, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd-m-Y',
                altInputClass: 'input datepicker-input',
                allowInput: true,
                maxDate: 'today',
                disableMobile: false,
                position: 'auto',
                appendTo: document.body
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
