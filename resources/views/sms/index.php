<?php
/**
 * SMS dashboard (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/sms/index.vue —
 * three tabs (individual / center / institute), phone auto-filled from
 * GET /api/getNumberCenter/{id} and GET /api/getNumberInstitute/{id}/{institute},
 * sends through POST /api/smsSave -> {status:'success'}.
 *
 * Type labels follow real SmsService behaviour (the original page had them
 * swapped): 1 = Masking sender "BKABD", 2 = Numeric sender.
 */
?>
<div class="space-y-4" id="sms-page">
    <div class="card max-w-4xl">
        <div class="card-header">SMS</div>
        <div class="card-body space-y-5">
            <div class="flex flex-wrap gap-2" id="sms-tabs" role="tablist">
                <button type="button" class="btn btn-sm tab-btn" data-tab="general" aria-selected="true">For Individual Person</button>
                <button type="button" class="btn btn-sm tab-btn" data-tab="center" aria-selected="false">For Center Authority</button>
                <button type="button" class="btn btn-sm tab-btn" data-tab="institute" aria-selected="false">For Institute Authority</button>
            </div>

            <div class="tab-panel space-y-4" data-panel="general">
                <p class="text-sm text-slate-500">Type the phone number(s) below manually (comma separated for multiple).</p>
            </div>

            <div class="tab-panel hidden space-y-4" data-panel="center">
                <div class="max-w-md">
                    <label class="label" for="sms-center">Center <span class="text-red-500">*</span></label>
                    <select id="sms-center" data-field="center_id">
                        <option value="">Select Center</option>
                        <option value="0">All</option>
                    </select>
                    <p class="field-error hidden" data-error-for="center_id"></p>
                </div>
            </div>

            <div class="tab-panel hidden space-y-4" data-panel="institute">
                <div class="max-w-md">
                    <label class="label" for="sms-ins-center">Center <span class="text-red-500">*</span></label>
                    <select id="sms-ins-center" data-field="center_id">
                        <option value="">Select Center</option>
                    </select>
                    <p class="field-error hidden" data-error-for="center_id"></p>
                </div>
                <div class="max-w-md">
                    <label class="label" for="sms-institute">Name of Institute <span class="text-red-500">*</span></label>
                    <select id="sms-institute" data-field="institute_id">
                        <option value="">Select Institute</option>
                        <option value="0">All</option>
                    </select>
                    <p class="field-error hidden" data-error-for="institute_id"></p>
                </div>
            </div>

            <form id="sms-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="sms-phone">Phone Number <span class="text-red-500">*</span></label>
                        <textarea id="sms-phone" name="phone" data-field="phone" rows="4"
                                  placeholder="Phone"></textarea>
                        <p class="field-error hidden" data-error-for="phone"></p>
                    </div>
                    <div>
                        <label class="label" for="sms-message">Message <span class="text-red-500">*</span></label>
                        <textarea id="sms-message" name="message" data-field="message" rows="4"
                                  placeholder="Message"></textarea>
                        <p class="field-error hidden" data-error-for="message"></p>
                    </div>
                    <div>
                        <label class="label" for="sms-type">SMS Type <span class="text-red-500">*</span></label>
                        <select id="sms-type" name="type" data-field="type">
                            <option value="">Select Type</option>
                            <option value="1">Masking (BKABD)</option>
                            <option value="2">Numeric Sender</option>
                        </select>
                        <p class="field-error hidden" data-error-for="type"></p>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="btn btn-primary w-full">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('sms-page');
    if (!root) { return; }

    const form = document.getElementById('sms-form');
    const phoneEl = document.getElementById('sms-phone');
    const centerEl = document.getElementById('sms-center');
    const insCenterEl = document.getElementById('sms-ins-center');
    const instituteEl = document.getElementById('sms-institute');

    // -----------------------------------------------------------------
    // Tabs
    // -----------------------------------------------------------------
    root.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const tab = btn.getAttribute('data-tab');
            root.querySelectorAll('.tab-btn').forEach(function (b) {
                const on = b === btn;
                b.classList.toggle('btn-primary', on);
                b.classList.toggle('btn-light', !on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            root.querySelectorAll('.tab-panel').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-panel') !== tab);
            });
        });
    });
    // Start with the first tab active.
    root.querySelectorAll('.tab-btn').forEach(function (b, i) {
        b.classList.toggle('btn-primary', i === 0);
        b.classList.toggle('btn-light', i !== 0);
    });

    // -----------------------------------------------------------------
    // Dropdown data
    // -----------------------------------------------------------------
    async function fillSelect(el, rows, labelFn) {
        (rows || []).forEach(function (row) {
            const opt = document.createElement('option');
            opt.value = row.id;
            opt.textContent = labelFn(row);
            el.appendChild(opt);
        });
    }

    (async function () {
        try {
            const json = await BK.get('/api/center');
            const centers = json.centerList || [];
            await fillSelect(centerEl, centers, function (c) { return c.name; });
            await fillSelect(insCenterEl, centers, function (c) { return c.name; });
        } catch (err) {
            BK.toast(err.message || 'Could not load centers', 'error');
        }
    })();

    async function loadNumbers(url) {
        try {
            const json = await BK.get(url);
            phoneEl.value = json.numbers || '';
        } catch (err) {
            BK.toast(err.message || 'Could not load phone numbers', 'error');
        }
    }

    centerEl.addEventListener('change', function () {
        if (centerEl.value === '') { return; }
        loadNumbers('/api/getNumberCenter/' + encodeURIComponent(centerEl.value));
    });

    insCenterEl.addEventListener('change', async function () {
        instituteEl.innerHTML = '<option value="">Select Institute</option><option value="0">All</option>';
        if (insCenterEl.value === '') { return; }
        try {
            const json = await BK.get(BK.qs('/api/instituteListByCenter', { center_id: insCenterEl.value }));
            await fillSelect(instituteEl, (json.data || {}).instituteList || [], function (i) {
                return String(i.name).toUpperCase();
            });
        } catch (err) {
            BK.toast(err.message || 'Could not load institutes', 'error');
        }
    });

    instituteEl.addEventListener('change', function () {
        if (insCenterEl.value === '' || instituteEl.value === '') { return; }
        loadNumbers('/api/getNumberInstitute/' + encodeURIComponent(insCenterEl.value) +
            '/' + encodeURIComponent(instituteEl.value));
    });

    // -----------------------------------------------------------------
    // Send
    // -----------------------------------------------------------------
    function fieldValue(name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.post('/api/smsSave', {
                phone: fieldValue('phone'),
                message: fieldValue('message'),
                type: fieldValue('type')
            });
            BK.toast('SMS send successfully', 'success');
            form.reset();
        });
    });
});
</script>
