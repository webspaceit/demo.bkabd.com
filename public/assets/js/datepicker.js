/**
 * Themed date picker for `<input data-datepicker>` (flatpickr).
 *
 * Displays DD-MM-YYYY for people while still submitting ISO Y-m-d, which is
 * what the server validates (`date_format:Y-m-d`). Loaded only on the pages
 * that opt in via `$pageScripts` (student registration + edit), together with
 * the vendored flatpickr runtime and `datepicker.css`.
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
                dateFormat: 'Y-m-d',                 // value written to the real (hidden) field
                altInput: true,
                altFormat: 'd-m-Y',                  // value shown to the user
                altInputClass: 'input datepicker-input',
                allowInput: true,                    // typing is allowed; bad input clears the value
                maxDate: 'today',                    // a date of birth cannot be in the future
                disableMobile: false,                // keep the native picker on touch devices
                position: 'auto'
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
