<?php
/**
 * Institute certificate picker (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/certificate/institute_certificate.vue —
 * choose center and institute, then open the printable certificate.
 */
?>
<div class="space-y-4" id="institute-certificate-page">
    <div class="card max-w-4xl">
        <div class="card-header">Institute Certificate</div>
        <div class="card-body">
            <form id="institute-certificate-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="fl">
                        <select class="input" id="ic-center"><option value="">Select Center</option></select>
                        <label for="ic-center">Center <span class="text-red-600">*</span></label>
                    </div>
                    <div class="fl">
                        <select class="input" id="ic-institute"><option value="">Select Institute</option></select>
                        <label for="ic-institute">Institute <span class="text-red-600">*</span></label>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Show Certificate</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const root = document.getElementById('institute-certificate-page');
    if (!root) { return; }
    const centerSel = document.getElementById('ic-center');
    const instituteSel = document.getElementById('ic-institute');
    const form = document.getElementById('institute-certificate-form');

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
        if (!instituteSel.value) {
            BK.toast('Please select an institute', 'error');
            return;
        }
        window.open('/api/institute-certificate/' + encodeURIComponent(instituteSel.value), '_blank');
    });
});
</script>
