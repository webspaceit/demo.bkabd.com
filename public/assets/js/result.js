/**
 * Public result search page — Clear button + double-submit guard.
 * The form posts natively (original UX: server renders the result sheet
 * directly on success, redirects back with the message on failure).
 */
(function () {
    const form = document.getElementById('result-search-form');
    if (!form) { return; }

    const clearBtn = document.getElementById('result-clear');
    const submitBtn = document.getElementById('result-submit');

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            form.reset();
            BK.clearErrors(form);
        });
    }

    form.addEventListener('submit', function () {
        if (submitBtn) {
            // Original flow: "Submitted. Wait for result" while navigating.
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitted. Wait for result';
        }
    });
})();
