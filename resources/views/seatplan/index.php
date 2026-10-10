<?php
/**
 * Seat Plan index page
 */
?>
<div class="card">
    <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold">Seat Plan</h3>
    </div>
    <div class="card-body">
        <form id="seatplan-form" class="filter-bar grid grid-cols-1 md:grid-cols-3 gap-4" data-form>
            <div>
                <label class="label">Exam Session</label>
                <select class="input" data-field="session" disabled>
                    <option value="<?= e($activeSession) ?>"><?= e($activeSession) ?></option>
                </select>
            </div>
            <div>
                <label class="label">Center <span class="text-red-500">*</span></label>
                <select class="input" data-field="center_id" required>
                    <option value="">Select Center</option>
                </select>
                <p class="field-error hidden" data-error-for="center_id"></p>
            </div>
            <div>
                <label class="label">Class <span class="text-red-500">*</span></label>
                <select class="input" data-field="class" required>
                    <option value="">Select Class</option>
                    <option value="6">KG</option>
                    <option value="1">One</option>
                    <option value="2">Two</option>
                    <option value="3">Three</option>
                    <option value="4">Four</option>
                    <option value="5">Five</option>
                </select>
                <p class="field-error hidden" data-error-for="class"></p>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-primary">View/Print Seat Plan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('seatplan-form');
    const centerSel = form.querySelector('[data-field="center_id"]');

    // Load centers
    BK.get('/api/centerList').then(function(res) {
        const centers = res.data.centerList || [];
        centers.forEach(function(c) {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = (c.center_name || c.name || '').toUpperCase();
            centerSel.appendChild(opt);
        });
    }).catch(function(err) {
        BK.toast('Failed to load centers', 'error');
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        BK.submit(form, async function() {
            const centerId = centerSel.value;
            const cls = form.querySelector('[data-field="class"]').value;
            if (!centerId) throw new Error('Please select center');
            if (!cls) throw new Error('Please select class');
            window.open('/api/seatplan_print/' + centerId + '/' + cls, '_blank', 'scrollbars=yes,width=800,height=600');
        });
    });
});
</script>
