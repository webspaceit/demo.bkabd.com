<?php
/**
 * Admit Cards index page
 */
?>
<div class="card">
    <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold">Admit Cards</h3>
    </div>
    <div class="card-body">
        <form id="admitcard-form" class="filter-bar grid grid-cols-1 md:grid-cols-3 gap-4" data-form>
            <div class="fl">
                <select class="input" data-field="session" disabled>
                    <option value="<?= e($activeSession) ?>"><?= e($activeSession) ?></option>
                </select>
                <label>Exam Session</label>
            </div>
            <div class="fl">
                <select class="input" data-field="center_id" required>
                    <option value="">Select Center</option>
                </select>
                <label>Center <span class="text-red-500">*</span></label>
                <p class="field-error hidden" data-error-for="center_id"></p>
            </div>
            <div class="fl">
                <select class="input" data-field="institute_id">
                    <option value="">Select Institute</option>
                </select>
                <label>Institute</label>
            </div>
            <div class="fl">
                <select class="input" data-field="class">
                    <option value="">Select Class</option>
                    <option value="6">KG</option>
                    <option value="1">One</option>
                    <option value="2">Two</option>
                    <option value="3">Three</option>
                    <option value="4">Four</option>
                    <option value="5">Five</option>
                </select>
                <label>Class</label>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn btn-primary">Search</button>
                <button type="button" class="btn btn-light" id="btn-reset">Reset</button>
            </div>
        </form>

        <div class="mt-6">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Registration</th>
                            <th>Roll</th>
                            <th>Name</th>
                            <th>Father</th>
                            <th>Mother</th>
                            <th>Class</th>
                            <th>Center</th>
                            <th>Institute</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="list-body"></tbody>
                </table>
            </div>
            <div data-pagination class="mt-4"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('admitcard-form');
    const centerSel = form.querySelector('[data-field="center_id"]');
    const instituteSel = form.querySelector('[data-field="institute_id"]');
    const listBody = document.getElementById('list-body');
    const pagerEl = document.querySelector('[data-pagination]');
    const resetBtn = document.getElementById('btn-reset');
    const activeSession = <?= json_encode($activeSession) ?>;

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

    centerSel.addEventListener('change', function() {
        const centerId = this.value;
        instituteSel.innerHTML = '<option value="">Select Institute</option>';
        if (!centerId) return;
        BK.get('/api/instituteListByCenter?center_id=' + centerId).then(function(res) {
            const institutes = res.data.instituteList || [];
            institutes.forEach(function(inst) {
                const opt = document.createElement('option');
                opt.value = inst.id;
                opt.textContent = (inst.name || '').toUpperCase();
                instituteSel.appendChild(opt);
            });
        });
    });

    function loadList(page) {
        page = page || 1;
        const params = new URLSearchParams();
        params.set('page', page);
        params.set('per_page', 20);
        params.set('session', activeSession);
        const centerId = centerSel.value;
        const instituteId = instituteSel.value;
        const cls = form.querySelector('[data-field="class"]').value;
        if (centerId) params.set('center_id', centerId);
        if (instituteId) params.set('institute_id', instituteId);
        if (cls) params.set('class', cls);

        BK.get('/api/report-student-list?' + params.toString()).then(function(res) {
            const paginator = res.data.paginator || {};
            const rows = paginator.data || [];
            let html = '';
            if (rows.length === 0) {
                html = '<tr><td colspan="10" class="text-center text-slate-500">No records found</td></tr>';
            } else {
                rows.forEach(function(r, idx) {
                    const sl = ((paginator.current_page - 1) * paginator.per_page) + idx + 1;
                    html += '<tr>' +
                        '<td>' + sl + '</td>' +
                        '<td>' + (r.registration || '') + '</td>' +
                        '<td>' + (r.roll || '') + '</td>' +
                        '<td>' + (r.name || '') + '</td>' +
                        '<td>' + (r.father_name || '') + '</td>' +
                        '<td>' + (r.mother_name || '') + '</td>' +
                        '<td>' + getClassName(r.class) + '</td>' +
                        '<td>' + (r.center_name || '') + '</td>' +
                        '<td>' + (r.institute_name || '') + '</td>' +
                        '<td class="row-actions flex gap-1">' +
                            '<button type="button" class="btn btn-sm btn-primary" data-action="view" data-institute="' + r.institute_id + '" data-class="' + r.class + '">View/Print</button>' +
                        '</td>' +
                    '</tr>';
                });
            }
            listBody.innerHTML = html;
            BK.renderPagination(pagerEl, paginator);

            // Attach action handlers
            listBody.querySelectorAll('[data-action="view"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const instId = this.getAttribute('data-institute');
                    const clsId = this.getAttribute('data-class');
                    if (!instId || !clsId) return;
                    window.open('/api/admitcard_print/' + instId + '/' + clsId, '_blank', 'scrollbars=yes,width=800,height=600');
                });
            });
        }).catch(function(err) {
            BK.toast(err.message || 'Failed to load students', 'error');
        });
    }

    function getClassName(cls) {
        cls = parseInt(cls);
        switch(cls) {
            case 6: return 'KG';
            case 1: return 'One';
            case 2: return 'Two';
            case 3: return 'Three';
            case 4: return 'Four';
            case 5: return 'Five';
            default: return cls;
        }
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        loadList(1);
    });

    resetBtn.addEventListener('click', function() {
        form.reset();
        instituteSel.innerHTML = '<option value="">Select Institute</option>';
        listBody.innerHTML = '<tr><td colspan="10" class="text-center text-slate-500">No records found</td></tr>';
        pagerEl.innerHTML = '';
    });
});
</script>
