<?php
/**
 * Shared mark-entry / grade-assign panel (filters + editable marks table).
 * Used by resources/views/markentry/index.php and resources/views/gradeassign/index.php
 * with $heading + $endpoint (original Vue pages: markentry/index.vue and
 * gradeassign/index.vue — identical UI, different fetch endpoint).
 *
 * $endpoint   '/api/markentrystudent' | '/api/markentrystudenthighestmark'
 * $heading    card heading
 * $activeSession  passed by the page controller (scopes the fullmarks lookup)
 *
 * Behaviour ported from the original Vue component:
 *  - center -> /api/center, institute -> /api/instituteById/{centerId}
 *  - preview fetches {students:[{id,name,registration,roll,bangla,english,math,total,percentage,grade,ml}]}
 *  - full marks from GET /api/fullmarks?session=<active> ({data:{bangla,english,math,total}})
 *  - over-max marks are rejected, grade 4 (AB) zeroes the row, submit posts
 *    {students:[...]} to /api/markentrystudent/updateTotalNumbers and shows
 *    the returned `message`.
 */
$heading = $heading ?? '';
$endpoint = $endpoint ?? '/api/markentrystudent';
$activeSession = $activeSession ?? '';
?>
<div class="space-y-4" id="entry-page"
     data-endpoint="<?= e($endpoint) ?>"
     data-session="<?= e($activeSession) ?>">
    <div class="card">
        <div class="card-body">
            <p class="text-base font-semibold text-slate-800 text-center mb-4"><?= e($heading) ?></p>
            <form id="entry-filter" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="label" for="entry-center">Center <span class="text-red-500">*</span></label>
                        <select id="entry-center" data-field="center_id">
                            <option value="">Select Center</option>
                        </select>
                        <p class="field-error hidden" data-error-for="center_id"></p>
                    </div>
                    <div>
                        <label class="label" for="entry-institute">Institute <span class="text-red-500">*</span></label>
                        <select id="entry-institute" data-field="institute_id">
                            <option value="">Select Institute</option>
                        </select>
                        <p class="field-error hidden" data-error-for="institute_id"></p>
                    </div>
                    <div>
                        <label class="label" for="entry-class">Class <span class="text-red-500">*</span></label>
                        <select id="entry-class" data-field="class">
                            <option value="">Select Exam Class</option>
                            <option value="6">KG</option>
                            <option value="1">One</option>
                            <option value="2">Two</option>
                            <option value="3">Three</option>
                            <option value="4">Four</option>
                            <option value="5">Five</option>
                        </select>
                        <p class="field-error hidden" data-error-for="class"></p>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="btn btn-primary w-full">Preview</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <form id="entry-form" class="hidden" novalidate>
        <div class="card">
            <div class="card-body space-y-4">
                <div class="overflow-x-auto">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center">Sl.</th>
                                <th style="text-align:center">Registration</th>
                                <th style="text-align:center;width:70px">Roll</th>
                                <th>Student Name</th>
                                <th style="text-align:center;width:85px">Bangla</th>
                                <th style="text-align:center;width:85px">English</th>
                                <th style="text-align:center;width:85px">Math</th>
                                <th style="text-align:center;width:85px">Total</th>
                                <th style="text-align:center;width:85px">(%)</th>
                                <th style="text-align:center;width:95px">Grade</th>
                                <th style="text-align:center;width:95px">M.L</th>
                            </tr>
                        </thead>
                        <tbody id="entry-rows"></tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary w-full">Submit</button>
            </div>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('entry-page');
    if (!root) { return; }

    const endpoint = root.getAttribute('data-endpoint');
    const activeSession = root.getAttribute('data-session');
    const filterForm = document.getElementById('entry-filter');
    const form = document.getElementById('entry-form');
    const rowsEl = document.getElementById('entry-rows');
    const centerEl = document.getElementById('entry-center');
    const instituteEl = document.getElementById('entry-institute');
    const classEl = document.getElementById('entry-class');

    // Full marks config (max per subject + total) for the active session.
    const max = { bangla: 0, english: 0, math: 0, total: 0 };
    let students = [];

    const GRADES = [
        { value: 0, label: 'NONE' },
        { value: 1, label: 'T.P' },
        { value: 2, label: 'G.G' },
        { value: 3, label: 'S.G' },
        { value: 4, label: 'AB' }
    ];
    const MERITS = [
        { value: 0, label: 'NONE' },
        { value: 1, label: '1st' },
        { value: 2, label: '2nd' },
        { value: 3, label: '3rd' }
    ];

    (async function () {
        try {
            const json = await BK.get('/api/center');
            (json.centerList || []).forEach(function (row) {
                const opt = document.createElement('option');
                opt.value = row.id;
                opt.textContent = row.name;
                centerEl.appendChild(opt);
            });
        } catch (err) {
            BK.toast(err.message || 'Could not load centers', 'error');
        }

        try {
            const json = await BK.get(BK.qs('/api/fullmarks', { session: activeSession }));
            const row = json.data || {};
            max.bangla = Number(row.bangla) || 0;
            max.english = Number(row.english) || 0;
            max.math = Number(row.math) || 0;
            max.total = Number(row.total) || 0;
        } catch (err) {
            BK.toast(err.message || 'Full marks configuration not found for this session', 'error');
        }
    })();

    centerEl.addEventListener('change', async function () {
        instituteEl.innerHTML = '<option value="">Select Institute</option>';
        if (centerEl.value === '') { return; }
        try {
            const json = await BK.get('/api/instituteById/' + encodeURIComponent(centerEl.value));
            (json.instituteById || []).forEach(function (row) {
                const opt = document.createElement('option');
                opt.value = row.id;
                opt.textContent = String(row.name).toUpperCase();
                instituteEl.appendChild(opt);
            });
        } catch (err) {
            BK.toast(err.message || 'Could not load institutes', 'error');
        }
    });

    // -----------------------------------------------------------------
    // Rendering
    // -----------------------------------------------------------------
    function options(list, selected) {
        return list.map(function (opt) {
            return '<option value="' + opt.value + '"' +
                (Number(selected) === opt.value ? ' selected' : '') + '>' + opt.label + '</option>';
        }).join('');
    }

    function renderRows() {
        rowsEl.innerHTML = students.map(function (s, i) {
            const ab = Number(s.grade) === 4;
            return '<tr data-index="' + i + '">' +
                '<td class="text-center">' + (i + 1) + '</td>' +
                '<td class="text-center">' + BK.esc(s.registration) + '</td>' +
                '<td class="text-center">' + BK.esc(s.roll) + '</td>' +
                '<td>' + BK.esc(s.name) + '</td>' +
                '<td class="text-center"><input type="number" data-mark="bangla" class="form-control" placeholder="0" value="' + BK.esc(s.bangla) + '"' +
                    (ab ? ' readonly disabled' : '') + '></td>' +
                '<td class="text-center"><input type="number" data-mark="english" class="form-control" placeholder="0" value="' + BK.esc(s.english) + '"' +
                    (ab ? ' readonly disabled' : '') + '></td>' +
                '<td class="text-center"><input type="number" data-mark="math" class="form-control" placeholder="0" value="' + BK.esc(s.math) + '"' +
                    (ab ? ' readonly disabled' : '') + '></td>' +
                '<td class="text-center" data-out="total">' + BK.esc(s.total) + '</td>' +
                '<td class="text-center" data-out="percentage">' + BK.esc(s.percentage) + '%</td>' +
                '<td class="text-center"><select data-out-select="grade" class="form-control">' +
                    options(GRADES, s.grade) + '</select></td>' +
                '<td class="text-center"><select data-out-select="ml" class="form-control">' +
                    options(MERITS, s.ml) + '</select></td>' +
                '</tr>';
        }).join('');
    }

    function readRow(index) {
        const tr = rowsEl.querySelector('tr[data-index="' + index + '"]');
        if (!tr) { return null; }
        const out = {};
        tr.querySelectorAll('[data-mark]').forEach(function (input) {
            out[input.getAttribute('data-mark')] = input.value;
        });
        tr.querySelectorAll('[data-out-select]').forEach(function (sel) {
            out[sel.getAttribute('data-out-select')] = sel.value;
        });
        return { tr: tr, values: out };
    }

    /** Port of calculateTotalNumber(): caps + total + percentage. */
    function recalculate(index) {
        const row = readRow(index);
        if (!row) { return; }
        const student = students[index];
        const limits = { bangla: max.bangla, english: max.english, math: max.math };

        Object.keys(limits).forEach(function (subject) {
            const limit = limits[subject];
            const raw = row.values[subject];
            if (limit > 0 && raw !== '' && Number(raw) > limit) {
                BK.toast('Wrong Mark — Please give valid mark.', 'error');
                const input = row.tr.querySelector('[data-mark="' + subject + '"]');
                if (input) { input.value = 0; }
                row.values[subject] = '0';
                student[subject] = 0;
            } else {
                student[subject] = raw === '' ? 0 : Number(raw);
            }
        });

        student.total = Number(student.bangla) + Number(student.english) + Number(student.math);
        student.percentage = max.total > 0 ? (student.total * 100) / max.total : 0;

        row.tr.querySelector('[data-out="total"]').textContent = student.total;
        row.tr.querySelector('[data-out="percentage"]').textContent =
            (Math.round(student.percentage * 100) / 100) + '%';
    }

    /** Port of onChange(): grade 4 (AB) zeroes the whole row. */
    function onGradeChange(index) {
        const row = readRow(index);
        if (!row) { return; }
        const student = students[index];
        student.grade = Number(row.values.grade);

        if (Number(row.values.grade) === 4) {
            ['bangla', 'english', 'math'].forEach(function (subject) {
                student[subject] = 0;
                const input = row.tr.querySelector('[data-mark="' + subject + '"]');
                if (input) { input.value = 0; input.setAttribute('readonly', 'readonly'); input.disabled = true; }
            });
            student.total = 0;
            student.percentage = 0;
            row.tr.querySelector('[data-out="total"]').textContent = '0';
            row.tr.querySelector('[data-out="percentage"]').textContent = '0%';
        } else {
            row.tr.querySelectorAll('[data-mark]').forEach(function (input) {
                input.removeAttribute('readonly');
                input.disabled = false;
            });
            recalculate(index);
        }
    }

    rowsEl.addEventListener('input', function (event) {
        const input = event.target.closest('[data-mark]');
        if (!input) { return; }
        const tr = input.closest('tr');
        recalculate(Number(tr.getAttribute('data-index')));
    });

    rowsEl.addEventListener('change', function (event) {
        const sel = event.target.closest('[data-out-select="grade"]');
        if (!sel) { return; }
        const tr = sel.closest('tr');
        onGradeChange(Number(tr.getAttribute('data-index')));
    });

    // -----------------------------------------------------------------
    // Preview + submit
    // -----------------------------------------------------------------
    function fieldError(name, message) {
        const box = filterForm.querySelector('[data-error-for="' + name + '"]');
        const input = filterForm.querySelector('[data-field="' + name + '"]');
        if (box) {
            box.textContent = message;
            box.classList.remove('hidden');
        }
        if (input) { input.classList.add('border-red-500'); }
    }

    filterForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        BK.clearErrors(filterForm);

        let ok = true;
        if (!centerEl.value) { fieldError('center_id', 'The center field is required.'); ok = false; }
        if (!instituteEl.value) { fieldError('institute_id', 'The institute field is required.'); ok = false; }
        if (!classEl.value) { fieldError('class', 'The class field is required.'); ok = false; }
        if (!ok) { return; }

        try {
            const json = await BK.get(
                endpoint + '/' + encodeURIComponent(instituteEl.value) + '/' + encodeURIComponent(classEl.value)
            );
            students = json.students || [];
            if (!students.length) {
                form.classList.add('hidden');
                BK.toast('Sorry — No Students available in this class under this Institute. Please try another class.', 'error');
                return;
            }
            renderRows();
            form.classList.remove('hidden');
        } catch (err) {
            BK.toast(err.message || 'Could not load students', 'error');
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            const payload = students.map(function (s) {
                return {
                    id: s.id,
                    bangla: s.bangla,
                    english: s.english,
                    math: s.math,
                    total: s.total,
                    percentage: s.percentage,
                    grade: s.grade,
                    ml: s.ml
                };
            });
            const json = await BK.post('/api/markentrystudent/updateTotalNumbers', { students: payload });
            BK.toast(json.message || 'Updated successfully', 'success');
        });
    });
});
</script>
