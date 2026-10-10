<?php
/**
 * Student certificate picker (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/certificate/index.vue —
 * choose center, institute and class, then open the printable certificates.
 */
?>
<div class="space-y-4" id="student-certificate-page">
    <div class="card max-w-4xl">
        <div class="card-header">Student Certificate</div>
        <div class="card-body">
            <form id="student-certificate-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="label" for="sc-center">Center <span class="text-red-600">*</span></label>
                        <select id="sc-center"><option value="">Select Center</option></select>
                        <p class="field-error hidden" data-error-for="center_id"></p>
                    </div>
                    <div>
                        <label class="label" for="sc-institute">Institute <span class="text-red-600">*</span></label>
                        <select id="sc-institute"><option value="">Select Institute</option></select>
                        <p class="field-error hidden" data-error-for="institute_id"></p>
                    </div>
                    <div>
                        <label class="label" for="sc-class">Class <span class="text-red-600">*</span></label>
                        <select id="sc-class">
                            <option value="">Select Class</option>
                            <option value="6">KG</option>
                            <option value="1">ONE</option>
                            <option value="2">TWO</option>
                            <option value="3">THREE</option>
                            <option value="4">FOUR</option>
                            <option value="5">FIVE</option>
                        </select>
                        <p class="field-error hidden" data-error-for="class"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Show Certificates</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const root = document.getElementById('student-certificate-page');
    if (!root) { return; }
    const centerSel = document.getElementById('sc-center');
    const instituteSel = document.getElementById('sc-institute');
    const classSel = document.getElementById('sc-class');
    const form = document.getElementById('student-certificate-form');

    function options(items, placeholder) {
        let html = '<option value="">' + BK.esc(placeholder) + '</option>';
        (items || []).forEach(function (item) {
            html += '<option value="' + BK.esc(String(item.id)) + '">' + BK.esc(item.name) + '</option>';
        });
        return html;
    }

    centerSel.addEventListener('change', async function () {
        instituteSel.innerHTML = options([], 'Select Institute');
        if (!centerSel.value) { return; }
        try {
            const json = await BK.get('/api/instituteListByCenter?center_id=' + encodeURIComponent(centerSel.value));
            instituteSel.innerHTML = options((json.data && json.data.instituteList) || [], 'Select Institute');
        } catch (err) {
            BK.toast(err.message || 'Could not load institutes', 'error');
        }
    });

    try {
        const json = await BK.get('/api/center');
        centerSel.innerHTML = options(json.centerList || [], 'Select Center');
    } catch (err) {
        BK.toast(err.message || 'Could not load centers', 'error');
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!instituteSel.value || !classSel.value) {
            BK.toast('Please select an institute and a class', 'error');
            return;
        }
        window.open('/api/certificate/' + encodeURIComponent(instituteSel.value) + '/' + encodeURIComponent(classSel.value), '_blank');
    });
});
</script>
