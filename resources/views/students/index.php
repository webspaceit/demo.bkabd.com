<?php
/**
 * Students list page — server-side pagination + filters over GET /api/students.
 * Columns mirror the original students/index.vue: Sl., Reg. No, Roll No, Name,
 * Class, Center, Institute, Status, Action (view/edit/delete).
 * Behaviour is a page-scoped inline script (BK library) — no separate JS file.
 *
 * $sessions / $centers / $institutes are rendered server-side so the filter bar
 * works without extra API calls; $filters carries the current query-string state.
 */
$classes = $classes ?? [1, 2, 3, 4, 5, 6];
$filters = $filters ?? [];
// Original list is scoped to the active session; default the dropdown to it.
// An explicit query-string value (including "All Sessions" = empty) wins.
$activeSession = (string) ($activeSession ?? '');
$selectedSession = array_key_exists('session', $filters) && $filters['session'] !== null
    ? (string) $filters['session']
    : $activeSession;
?>
<div class="space-y-4">
    <div class="card" id="students-list"
         data-perm-edit="<?= can('student-edit') ? '1' : '0' ?>"
         data-perm-delete="<?= can('student-delete') ? '1' : '0' ?>">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All Student's With Details Info (<span id="student-total">0</span>)</h1>
            <?php if (can('student-registration')): ?>
                <a href="/student-registration" class="btn btn-primary btn-sm">Add New Student</a>
            <?php endif; ?>
        </div>
        <div class="card-body space-y-4">
            <div class="filter-bar grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label class="label" for="f-session">Exam Session</label>
                    <select id="f-session" class="w-full">
                        <option value="">All Sessions</option>
                        <?php foreach ((array) $sessions as $s): ?>
                            <option value="<?= (int) $s['session'] ?>" <?= $selectedSession === (string) $s['session'] ? 'selected' : '' ?>>
                                <?= e($s['session']) ?><?= (int) ($s['active'] ?? 0) === 1 ? ' (active)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="f-center">Center</label>
                    <select id="f-center" class="w-full">
                        <option value="">All Centers</option>
                        <?php foreach ((array) $centers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (string) ($filters['center_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="f-institute">Institute</label>
                    <select id="f-institute" class="w-full">
                        <option value="">All Institutes</option>
                        <?php foreach ((array) $institutes as $i): ?>
                            <option value="<?= (int) $i['id'] ?>" data-center="<?= (int) $i['center_id'] ?>" <?= (string) ($filters['institute_id'] ?? '') === (string) $i['id'] ? 'selected' : '' ?>>
                                <?= e($i['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="f-class">Class</label>
                    <select id="f-class" class="w-full">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int) $c ?>" <?= (string) ($filters['class'] ?? '') === (string) $c ? 'selected' : '' ?>>
                                <?= e(ClassName($c)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="f-search">Search</label>
                    <input type="search" id="f-search" class="w-full" placeholder="Name, reg. no, roll, institute..." value="<?= e($filters['search'] ?? '') ?>">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:10px">Sl.</th>
                            <th>Reg. No</th>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Center</th>
                            <th>Institute</th>
                            <th>Status</th>
                            <th style="width:210px">Action</th>
                        </tr>
                    </thead>
                    <tbody id="student-rows">
                        <tr><td colspan="9" class="text-center py-8 text-slate-400">Loading…</td></tr>
                    </tbody>
                </table>
            </div>

            <div id="student-pagination"></div>
        </div>
    </div>
</div>

<!-- Student details modal (data loaded from GET /api/students/{id}) -->
<div id="student-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 overflow-y-auto">
    <div class="bg-white rounded-xl w-full max-w-2xl my-8 shadow-xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <h3 class="text-base font-semibold text-slate-800" id="modal-title">Student Info</h3>
            <button type="button" class="p-2 rounded hover:bg-slate-100 text-slate-500" data-modal-close aria-label="Close">&#10005;</button>
        </div>
        <div class="p-5">
            <div class="flex flex-col sm:flex-row gap-5">
                <div class="shrink-0">
                    <img id="modal-photo" src="" alt="Student Photo" class="w-32 h-36 object-cover rounded border border-slate-200 hidden">
                </div>
                <table class="table text-sm">
                    <tr><th class="w-40 text-left">Name</th><td class="text-left">: <span id="m-name"></span></td></tr>
                    <tr><th class="text-left">Registration</th><td class="text-left">: <span id="m-registration"></span></td></tr>
                    <tr><th class="text-left">Roll</th><td class="text-left">: <span id="m-roll"></span></td></tr>
                    <tr><th class="text-left">Father's Name</th><td class="text-left">: <span id="m-father_name"></span></td></tr>
                    <tr><th class="text-left">Mother's Name</th><td class="text-left">: <span id="m-mother_name"></span></td></tr>
                    <tr><th class="text-left">Center</th><td class="text-left">: <span id="m-center_name"></span></td></tr>
                    <tr><th class="text-left">Institute</th><td class="text-left">: <span id="m-institute_name"></span></td></tr>
                    <tr><th class="text-left">Email</th><td class="text-left">: <span id="m-email"></span></td></tr>
                    <tr><th class="text-left">Phone</th><td class="text-left">: <span id="m-phone"></span></td></tr>
                    <tr><th class="text-left">Religion</th><td class="text-left">: <span id="m-religion"></span></td></tr>
                    <tr><th class="text-left">Present Address</th><td class="text-left">: <span id="m-present_address"></span></td></tr>
                    <tr><th class="text-left">Permanent Address</th><td class="text-left">: <span id="m-permanent_address"></span></td></tr>
                    <tr><th class="text-left">Date of Birth</th><td class="text-left">: <span id="m-dob"></span></td></tr>
                    <tr><th class="text-left">Gender</th><td class="text-left">: <span id="m-gender"></span></td></tr>
                    <tr><th class="text-left">Class</th><td class="text-left">: <span id="m-class"></span></td></tr>
                    <tr><th class="text-left">Session</th><td class="text-left">: <span id="m-session"></span></td></tr>
                    <tr><th class="text-left">Division</th><td class="text-left">: <span id="m-division_name"></span></td></tr>
                    <tr><th class="text-left">District</th><td class="text-left">: <span id="m-district_name"></span></td></tr>
                    <tr><th class="text-left">Upazila</th><td class="text-left">: <span id="m-upazila_name"></span></td></tr>
                    <tr><th class="text-left">Education Type</th><td class="text-left">: <span id="m-education_type"></span></td></tr>
                    <tr><th class="text-left">Principal Name</th><td class="text-left">: <span id="m-principal_name"></span></td></tr>
                    <tr><th class="text-left">Note</th><td class="text-left">: <span id="m-note"></span></td></tr>
                </table>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
            <button type="button" class="btn btn-primary btn-sm" data-modal-close>Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const listBox = document.getElementById('students-list');
    const rowsEl = document.getElementById('student-rows');
    if (!rowsEl || !listBox) { return; }

    const totalEl = document.getElementById('student-total');
    const pagerEl = document.getElementById('student-pagination');
    const sessionEl = document.getElementById('f-session');
    const classEl = document.getElementById('f-class');
    const centerEl = document.getElementById('f-center');
    const instituteEl = document.getElementById('f-institute');
    const searchEl = document.getElementById('f-search');
    const modalEl = document.getElementById('student-modal');
    const PER_PAGE = 20;

    const can = function (name) { return listBox.getAttribute('data-perm-' + name) === '1'; };
    const val = function (el) { return el ? el.value : ''; };

    const CLASS_NAMES = { 6: 'KG', 1: 'ONE', 2: 'TWO', 3: 'THREE', 4: 'FOUR', 5: 'FIVE' };
    const GENDERS = { 1: 'Male', 2: 'Female', 3: 'Others' };
    const RELIGIONS = { 1: 'Muslim', 2: 'Hindu', 3: 'Buddhist', 4: 'Christian' };
    const EDUCATION = { 1: 'Bangla Medium', 2: 'English Version', 3: 'Arabic Version' };

    let pageNum = 1;
    let searchTimer = null;

    function rowHtml(rec, i) {
        const id = BK.esc(String(rec.id));
        const className = CLASS_NAMES[rec.class] || '';
        const badge = String(rec.active) === '1'
            ? '<span class="badge badge-green">Active</span>'
            : '<span class="badge badge-gray">Inactive</span>';

        let actions = '<div class="row-actions flex items-center gap-1">';
        actions += '<button type="button" class="btn btn-sm btn-light" data-act="view" data-id="' + id + '" title="Preview">&#128065;</button>';
        if (can('edit')) {
            actions += '<a class="btn btn-sm btn-light" href="/edit-student/' + id + '" title="Edit">&#9998;</a>';
        }
        if (can('delete')) {
            actions += '<button type="button" class="btn btn-sm btn-danger" data-act="delete" data-id="' + id + '" title="Delete">&#10005;</button>';
        }
        actions += '</div>';

        return '<tr><td>' + ((pageNum - 1) * PER_PAGE + i + 1) + '</td>' +
            '<td>' + BK.esc(rec.registration) + '</td>' +
            '<td>' + BK.esc(rec.roll) + '</td>' +
            '<td>' + BK.esc(rec.name) + '</td>' +
            '<td>' + BK.esc(className) + '</td>' +
            '<td>' + BK.esc(rec.center_name) + '</td>' +
            '<td>' + BK.esc(rec.institute_name) + '</td>' +
            '<td>' + badge + '</td>' +
            '<td>' + actions + '</td></tr>';
    }

    function reload() {
        const url = BK.qs('/api/students', {
            page: pageNum,
            per_page: PER_PAGE,
            session: val(sessionEl),
            class: val(classEl),
            center_id: val(centerEl),
            institute_id: val(instituteEl),
            search: val(searchEl)
        });
        BK.get(url).then(function (json) {
            const pager = (json && json.data) || {};
            const rows = pager.data || [];
            totalEl.textContent = pager.total || 0;
            rowsEl.innerHTML = rows.length
                ? rows.map(rowHtml).join('')
                : '<tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No records found</td></tr>';
            if (pager.current_page) { pageNum = pager.current_page; }
            if (pagerEl) {
                pagerEl.innerHTML = '';
                BK.renderPagination(pagerEl, pager);
            }
        }).catch(function (err) {
            rowsEl.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-red-500">Could not load the list</td></tr>';
            BK.toast(err.message || 'Could not load the list', 'error');
        });
    }

    // Pager links point at the JSON URL — turn clicks into AJAX page loads.
    if (pagerEl) {
        pagerEl.addEventListener('click', function (event) {
            const link = event.target.closest('a');
            if (!link) { return; }
            event.preventDefault();
            pageNum = Number(new URL(link.href).searchParams.get('page') || 1);
            reload();
        });
    }

    const reloadFromFilters = function () { pageNum = 1; reload(); };

    [sessionEl, classEl, centerEl, instituteEl].forEach(function (el) {
        if (el) { el.addEventListener('change', reloadFromFilters); }
    });

    // Keep the institute dropdown aligned with the selected center.
    function syncInstitutes() {
        const cid = val(centerEl);
        let cleared = false;
        instituteEl.querySelectorAll('option[data-center]').forEach(function (opt) {
            const visible = cid === '' || opt.getAttribute('data-center') === cid;
            opt.hidden = !visible;
            if (opt.selected && !visible) { cleared = true; }
        });
        if (cleared) { instituteEl.value = ''; }
    }
    if (centerEl && instituteEl) {
        centerEl.addEventListener('change', syncInstitutes);
        syncInstitutes();
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                pageNum = 1;
                reload();
            }, 300);
        });
    }

    // -----------------------------------------------------------------
    // Details modal (GET /api/students/{id} -> {data: student row})
    // -----------------------------------------------------------------
    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = (value === null || value === undefined || value === '') ? '—' : value;
        }
    }

    function closeModal() {
        if (modalEl) { modalEl.classList.add('hidden'); }
    }

    function openModal(id) {
        BK.get('/api/students/' + encodeURIComponent(id)).then(function (json) {
            const s = (json && json.data) || {};
            setText('m-name', s.name);
            setText('m-registration', s.registration);
            setText('m-roll', s.roll);
            setText('m-father_name', s.father_name);
            setText('m-mother_name', s.mother_name);
            setText('m-center_name', s.center_name);
            setText('m-institute_name', s.institute_name);
            setText('m-email', s.email);
            setText('m-phone', s.phone);
            setText('m-religion', RELIGIONS[s.religion] || s.religion);
            setText('m-present_address', s.present_address);
            setText('m-permanent_address', s.permanent_address);
            setText('m-dob', s.dob);
            setText('m-gender', GENDERS[s.gender] || s.gender);
            setText('m-class', (s.class !== null && s.class !== undefined) ? (CLASS_NAMES[s.class] || s.class) : '—');
            setText('m-session', s.session);
            setText('m-division_name', s.division_name);
            setText('m-district_name', s.district_name);
            setText('m-upazila_name', s.upazila_name);
            setText('m-education_type', EDUCATION[s.education_type] || s.education_type);
            setText('m-principal_name', s.principal_name);
            setText('m-note', s.note);

            const img = document.getElementById('modal-photo');
            if (img) {
                if (s.photo) {
                    img.src = '/' + s.photo;
                    img.classList.remove('hidden');
                } else {
                    img.classList.add('hidden');
                }
            }
            if (modalEl) { modalEl.classList.remove('hidden'); }
        }).catch(function (err) {
            BK.toast(err.message || 'Could not load student details', 'error');
        });
    }

    rowsEl.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-act]');
        if (!btn) { return; }
        const id = btn.getAttribute('data-id');
        const act = btn.getAttribute('data-act');

        if (act === 'view') {
            openModal(id);
            return;
        }
        if (act === 'delete') {
            BK.confirm("Are you sure? You won't be able to revert this!").then(function (confirmed) {
                if (!confirmed) { return; }
                BK.del('/api/students/' + encodeURIComponent(id)).then(function () {
                    BK.toast('Student has been deleted.', 'success');
                    reload();
                }).catch(function (err) {
                    BK.toast(err.message || 'Could not delete the student', 'error');
                });
            });
        }
    });

    if (modalEl) {
        modalEl.querySelectorAll('[data-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', closeModal);
        });
        modalEl.addEventListener('click', function (event) {
            if (event.target === modalEl) { closeModal(); }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { closeModal(); }
        });
    }

    reload();
});
</script>