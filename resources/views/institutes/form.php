<?php
/**
 * Add / Edit institute form (behaviour inline at the bottom of this view).
 * Original Vue pages: resources/js/components/institutes/create.vue + edit.vue.
 * $mode = 'create' | 'edit'; $instituteId is set for edit.
 */
$isEdit = ($mode ?? 'create') === 'edit';
?>
<div class="space-y-4" id="institute-form-page" data-mode="<?= e($mode ?? 'create') ?>" data-id="<?= (int) ($instituteId ?? 0) ?>">
    <div class="card max-w-4xl">
        <div class="card-header"><?= $isEdit ? 'Edit Institute' : 'Add Institute' ?></div>
        <div class="card-body">
            <form id="institute-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="fl">
                        <select class="input" id="f-center_id" data-field="center_id"><option value="">Select Center</option></select>
                        <label for="f-center_id">Center <span class="text-red-600">*</span></label>
                        <p class="field-error hidden" data-error-for="center_id"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="f-name" data-field="name" placeholder="Institute Name" autocomplete="off">
                        <label for="f-name">Institute Name <span class="text-red-600">*</span></label>
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="f-director_name" data-field="director_name" placeholder="Director Name" autocomplete="off">
                        <label for="f-director_name">Director Name</label>
                        <p class="field-error hidden" data-error-for="director_name"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="f-phone" data-field="phone" placeholder="Phone" autocomplete="off">
                        <label for="f-phone">Phone</label>
                        <p class="field-error hidden" data-error-for="phone"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="email" id="f-email" data-field="email" placeholder="Email Address" autocomplete="off">
                        <label for="f-email">Email</label>
                        <p class="field-error hidden" data-error-for="email"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="f-website" data-field="website" placeholder="Website" autocomplete="off">
                        <label for="f-website">Website</label>
                        <p class="field-error hidden" data-error-for="website"></p>
                    </div>
                    <div class="fl">
                        <select class="input" id="f-division_id" data-field="division_id"><option value="">Select Division</option></select>
                        <label for="f-division_id">Division</label>
                        <p class="field-error hidden" data-error-for="division_id"></p>
                    </div>
                    <div class="fl">
                        <select class="input" id="f-district_id" data-field="district_id"><option value="">Select District</option></select>
                        <label for="f-district_id">District</label>
                        <p class="field-error hidden" data-error-for="district_id"></p>
                    </div>
                    <div class="fl">
                        <select class="input" id="f-upazila_id" data-field="upazila_id"><option value="">Select Upazila</option></select>
                        <label for="f-upazila_id">Upazila</label>
                        <p class="field-error hidden" data-error-for="upazila_id"></p>
                    </div>
                    <div class="md:col-span-2 fl">
                        <input class="input" type="text" id="f-address" data-field="address" placeholder="Address" autocomplete="off">
                        <label for="f-address">Address</label>
                        <p class="field-error hidden" data-error-for="address"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="f-certificate_no" data-field="certificate_no" placeholder="Certificate No" autocomplete="off">
                        <label for="f-certificate_no">Certificate No</label>
                        <p class="field-error hidden" data-error-for="certificate_no"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="date" id="f-established_date" data-field="established_date" placeholder="Established Date">
                        <label for="f-established_date">Established Date</label>
                        <p class="field-error hidden" data-error-for="established_date"></p>
                    </div>
                    <div class="fl">
                        <select class="input" id="f-active" data-field="active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <label for="f-active">Status</label>
                        <p class="field-error hidden" data-error-for="active"></p>
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
    const root = document.getElementById('institute-form-page');
    if (!root) { return; }
    const isEdit = root.getAttribute('data-mode') === 'edit';
    const instituteId = root.getAttribute('data-id');
    const form = document.getElementById('institute-form');
    const centerSel = document.getElementById('f-center_id');
    const divisionSel = document.getElementById('f-division_id');
    const districtSel = document.getElementById('f-district_id');
    const upazilaSel = document.getElementById('f-upazila_id');

    function optionList(items, selectedId, placeholder) {
        let html = '<option value="">' + BK.esc(placeholder) + '</option>';
        (items || []).forEach(function (item) {
            const selected = selectedId !== null && selectedId !== undefined && selectedId !== '' &&
                String(item.id) === String(selectedId);
            html += '<option value="' + BK.esc(String(item.id)) + '"' + (selected ? ' selected' : '') + '>' +
                BK.esc(item.name) + '</option>';
        });
        return html;
    }

    async function loadDistricts(selectedId) {
        if (!divisionSel.value) {
            districtSel.innerHTML = optionList([], null, 'Select District');
            return;
        }
        const json = await BK.get('/api/districtById/' + divisionSel.value);
        districtSel.innerHTML = optionList(json.districtById || [], selectedId, 'Select District');
    }

    async function loadUpazilas(selectedId) {
        if (!districtSel.value) {
            upazilaSel.innerHTML = optionList([], null, 'Select Upazila');
            return;
        }
        const json = await BK.get('/api/upazilaById/' + districtSel.value);
        upazilaSel.innerHTML = optionList(json.upazilaById || [], selectedId, 'Select Upazila');
    }

    divisionSel.addEventListener('change', function () {
        upazilaSel.innerHTML = optionList([], null, 'Select Upazila');
        loadDistricts(null).catch(function (err) { BK.toast(err.message || 'Could not load districts', 'error'); });
    });
    districtSel.addEventListener('change', function () {
        loadUpazilas(null).catch(function (err) { BK.toast(err.message || 'Could not load upazilas', 'error'); });
    });

    function setField(name, value) {
        const el = form.querySelector('[data-field="' + name + '"]');
        if (el && value !== null && value !== undefined) { el.value = value; }
    }

    try {
        const centers = await BK.get('/api/center');
        centerSel.innerHTML = optionList(centers.centerList || [], null, 'Select Center');
        const divisions = await BK.get('/api/divisionList');
        divisionSel.innerHTML = optionList(divisions.divisionList || [], null, 'Select Division');

        if (isEdit) {
            const json = await BK.get('/api/institutes/' + instituteId);
            const row = json.instituteById;
            centerSel.value = row.center_id ?? '';
            ['name', 'director_name', 'phone', 'email', 'website', 'address', 'certificate_no',
                'established_date', 'active'].forEach(function (f) { setField(f, row[f]); });
            divisionSel.value = row.division_id ?? '';
            await loadDistricts(row.district_id);
            await loadUpazilas(row.upazila_id);
        }
    } catch (err) {
        BK.toast(err.message || 'Could not load the form', 'error');
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const body = {};
        form.querySelectorAll('[data-field]').forEach(function (el) {
            body[el.getAttribute('data-field')] = el.value;
        });
        BK.submit(form, async function () {
            await BK.post(isEdit ? '/api/institutes/' + instituteId : '/api/institutes', body);
            BK.toast(isEdit ? 'Institute updated successfully' : 'Institute added successfully', 'success');
            window.location.href = '/institutes';
        });
    });
});
</script>
