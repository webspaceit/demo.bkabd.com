<?php
/**
 * Set Institute Validity (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/institutes/validity.vue.
 * Data loads from GET /api/institutes/{id}; save via PUT /api/institutevalidity/{id}.
 */
?>
<div class="space-y-4" id="institute-validity-page" data-id="<?= (int) $instituteId ?>">
    <div class="card max-w-4xl">
        <div class="card-header">Set Institute Validity</div>
        <div class="card-body">
            <form id="institute-validity-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="label" for="v-last_renewal_date">Last Renewal Date <span class="text-red-600">*</span></label>
                        <input type="date" id="v-last_renewal_date" data-field="last_renewal_date" placeholder="Last Renewal Date">
                        <p class="field-error hidden" data-error-for="last_renewal_date"></p>
                    </div>
                    <div>
                        <label class="label" for="v-validity_date">Validity Date <span class="text-red-600">*</span></label>
                        <input type="date" id="v-validity_date" data-field="validity_date" placeholder="Validity Date">
                        <p class="field-error hidden" data-error-for="validity_date"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <a href="/institutes" class="btn btn-light">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const root = document.getElementById('institute-validity-page');
    if (!root) { return; }
    const instituteId = root.getAttribute('data-id');
    const form = document.getElementById('institute-validity-form');

    try {
        const json = await BK.get('/api/institutes/' + instituteId);
        const row = json.instituteById || {};
        // Backend stores Y-m-d; date inputs expect the same.
        ['last_renewal_date', 'validity_date'].forEach(function (f) {
            const el = form.querySelector('[data-field="' + f + '"]');
            const value = row[f];
            if (el && value) { el.value = String(value).slice(0, 10); }
        });
    } catch (err) {
        BK.toast(err.message || 'Could not load the institute', 'error');
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const body = {};
        form.querySelectorAll('[data-field]').forEach(function (el) {
            body[el.getAttribute('data-field')] = el.value;
        });
        BK.submit(form, async function () {
            await BK.put('/api/institutevalidity/' + instituteId, body);
            BK.toast('Institute validity updated successfully', 'success');
            window.location.href = '/institutes';
        });
    });
});
</script>
