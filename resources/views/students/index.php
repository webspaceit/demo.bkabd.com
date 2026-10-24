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
         data-perm-delete="<?= can('student-delete') ? '1' : '0' ?>"
         data-perm-pend="<?= can('student-approve') ? '1' : '0' ?>">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All Student's With Details Info (<span id="student-total">0</span>)</h1>
            <?php if (can('student-registration')): ?>
                <a href="/student-registration" class="btn btn-primary btn-sm">Add New Student</a>
            <?php endif; ?>
        </div>
        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div id="selected-center-summary" class="hidden flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3" role="status" aria-live="polite">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Selected Center</p>
                        <p id="selected-center-name" class="mt-0.5 truncate text-sm font-semibold text-emerald-950"></p>
                    </div>
                    <div class="shrink-0 rounded-full bg-white px-3 py-1.5 text-sm font-bold text-emerald-800 ring-1 ring-emerald-200">
                        <span id="selected-center-student-count">…</span> matching students
                    </div>
                </div>
                <div id="selected-institute-summary" class="hidden flex flex-wrap items-center justify-between gap-3 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3" role="status" aria-live="polite">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Selected Institute</p>
                        <p id="selected-institute-name" class="mt-0.5 truncate text-sm font-semibold text-teal-950"></p>
                    </div>
                    <div class="shrink-0 rounded-full bg-white px-3 py-1.5 text-sm font-bold text-teal-800 ring-1 ring-teal-200">
                        <span id="selected-institute-student-count">…</span> matching students
                    </div>
                </div>
            </div>

            <div class="filter-bar students-filter-bar">
                <div class="fl">
                    <select id="f-session" class="w-full">
                        <option value="">All Sessions</option>
                        <?php foreach ((array) $sessions as $s): ?>
                            <option value="<?= (int) $s['session'] ?>" <?= $selectedSession === (string) $s['session'] ? 'selected' : '' ?>>
                                <?= e($s['session']) ?><?= (int) ($s['active'] ?? 0) === 1 ? ' (active)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="f-session">Exam Session</label>
                </div>
                <div class="fl">
                    <select id="f-center" class="w-full">
                        <option value="">All Centers</option>
                        <?php foreach ((array) $centers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (string) ($filters['center_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="f-center" class="f-center-search">Center <span class="filter-record-count" id="center-record-count" title="Available centers"><?= count((array) $centers) ?></span></label>
                </div>
                <div class="fl">
                    <select id="f-institute" class="w-full">
                        <option value="">All Institutes</option>
                        <?php foreach ((array) $institutes as $i): ?>
                            <option value="<?= (int) $i['id'] ?>" data-center="<?= (int) $i['center_id'] ?>" <?= (string) ($filters['institute_id'] ?? '') === (string) $i['id'] ? 'selected' : '' ?>>
                                <?= e($i['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="f-institute" class="f-institute">Institute <span class="filter-record-count" id="institute-record-count" data-total-count="<?= count((array) $institutes) ?>" title="Available institutes"><?= count((array) $institutes) ?></span></label>
                </div>
                <div class="fl">
                    <select id="f-class" class="w-full">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int) $c ?>" <?= (string) ($filters['class'] ?? '') === (string) $c ? 'selected' : '' ?>>
                                <?= e(ClassName($c)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="f-class">Class</label>
                </div>
                <div class="fl">
                    <select id="f-status" class="w-full">
                        <option value="">All Statuses</option>
                        <option value="active" <?= (string) ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="pending" <?= (string) ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                    <label for="f-status">Status</label>
                </div>
                <div class="fl" style="display:flex;align-items:center;gap:.5rem">
                    <input type="search" id="f-search" class="w-full min-w-0" style="flex:1" placeholder="Name, reg. no, roll..." value="<?= e($filters['search'] ?? '') ?>">
                    <label for="f-search">Search</label>
                    <button type="button" id="btn-reset-filters" class="btn btn-light shrink-0">Reset</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:10px">Sl.</th>
                            <th style="width:130px">Reg. No</th>
                            <th class="text-center" style="width:80px">Roll No</th>
                            <th>Name</th>
                            <th class="text-center">Class</th>
                            <th>Center</th>
                            <th>Institute</th>
                            <th class="text-center">Status</th>
                            <th style="width:90px">Action</th>
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
<?= partial('students._details_modal') ?>

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
    const statusEl = document.getElementById('f-status');
    const centerEl = document.getElementById('f-center');
    const instituteEl = document.getElementById('f-institute');
    const searchEl = document.getElementById('f-search');
    const resetFiltersBtn = document.getElementById('btn-reset-filters');
    const modalEl = document.getElementById('student-modal');
    const centerSummaryEl = document.getElementById('selected-center-summary');
    const centerSummaryNameEl = document.getElementById('selected-center-name');
    const centerSummaryCountEl = document.getElementById('selected-center-student-count');
    const instituteSummaryEl = document.getElementById('selected-institute-summary');
    const instituteSummaryNameEl = document.getElementById('selected-institute-name');
    const instituteSummaryCountEl = document.getElementById('selected-institute-student-count');
    const instituteCountEl = document.getElementById('institute-record-count');
    const PER_PAGE = 20;

    const can = function (name) { return listBox.getAttribute('data-perm-' + name) === '1'; };
    const val = function (el) { return el ? el.value : ''; };
    const initialSession = val(sessionEl);

    const CLASS_NAMES = { 6: 'KG', 1: 'ONE', 2: 'TWO', 3: 'THREE', 4: 'FOUR', 5: 'FIVE' };
    const GENDERS = { 1: 'Male', 2: 'Female', 3: 'Others' };
    const RELIGIONS = { 1: 'Muslim', 2: 'Hindu', 3: 'Buddhist', 4: 'Christian' };
    const EDUCATION = { 1: 'Bangla Medium', 2: 'English Version', 3: 'Arabic Version' };

    let pageNum = 1;
    let searchTimer = null;
    let centerSummaryRequest = 0;
    let instituteSummaryRequest = 0;

    function syncInstituteRecordCount() {
        if (!instituteCountEl || !instituteEl) return;
        const visibleCount = Array.from(instituteEl.options).filter(function (option) {
            return option.value !== '' && !option.hidden;
        }).length;
        const totalCount = Number(instituteCountEl.dataset.totalCount || 0);
        const count = val(centerEl) ? visibleCount : totalCount;
        instituteCountEl.textContent = count.toLocaleString('en-US');
        instituteCountEl.title = count + ' available institute(s)';
    }

    function syncSelectionSummaries() {
        const centerId = val(centerEl);
        const instituteId = val(instituteEl);
        const centerRequest = ++centerSummaryRequest;
        const instituteRequest = ++instituteSummaryRequest;
        const centerOption = centerEl.options[centerEl.selectedIndex];
        const instituteOption = instituteEl.options[instituteEl.selectedIndex];

        if (centerSummaryEl) centerSummaryEl.classList.toggle('hidden', !centerId);
        if (instituteSummaryEl) instituteSummaryEl.classList.toggle('hidden', !instituteId);
        if (centerId && centerSummaryNameEl) centerSummaryNameEl.textContent = centerOption ? centerOption.textContent.trim() : '';
        if (instituteId && instituteSummaryNameEl) instituteSummaryNameEl.textContent = instituteOption ? instituteOption.textContent.trim() : '';
        if (centerId && centerSummaryCountEl) centerSummaryCountEl.textContent = '…';
        if (instituteId && instituteSummaryCountEl) instituteSummaryCountEl.textContent = '…';

        const base = {
            session: val(sessionEl),
            class: val(classEl),
            status: val(statusEl),
            search: val(searchEl),
            per_page: 1,
            page: 1
        };
        if (centerId) {
            BK.get(BK.qs('/api/students', Object.assign({}, base, {center_id: centerId})))
                .then(function (json) {
                    if (centerRequest !== centerSummaryRequest) return;
                    const total = Number((json.data && json.data.total) || 0);
                    if (centerSummaryCountEl) centerSummaryCountEl.textContent = total.toLocaleString('en-US');
                }).catch(function () {
                    if (centerRequest === centerSummaryRequest && centerSummaryCountEl) centerSummaryCountEl.textContent = 'unavailable';
                });
        }
        if (instituteId) {
            BK.get(BK.qs('/api/students', Object.assign({}, base, {center_id: centerId, institute_id: instituteId})))
                .then(function (json) {
                    if (instituteRequest !== instituteSummaryRequest) return;
                    const total = Number((json.data && json.data.total) || 0);
                    if (instituteSummaryCountEl) instituteSummaryCountEl.textContent = total.toLocaleString('en-US');
                }).catch(function () {
                    if (instituteRequest === instituteSummaryRequest && instituteSummaryCountEl) instituteSummaryCountEl.textContent = 'unavailable';
                });
        }
    }

    function rowHtml(rec, i) {
        const id = BK.esc(String(rec.id));
        const className = CLASS_NAMES[rec.class] || '';
        // Status: students are created Pending (active = 0) and become Active
        // once approved in the Pending Registration queue (/pending-students).
        const badge = String(rec.active) === '1'
            ? '<span class="badge badge-green">Active</span>'
            : '<span class="badge bg-amber-50 text-amber-800">Pending</span>';

        let actions = '<div class="row-actions flex items-center gap-1">';
        actions += '<button type="button" class="btn btn-sm btn-light" data-act="view" data-id="' + id + '" title="Preview">&#128065;</button>';
        if (can('edit')) {
            actions += '<a class="btn btn-sm btn-light" href="/edit-student/' + id + '" title="Edit">&#9998;</a>';
        }
        if (String(rec.active) === '1' && can('pend')) {
            actions += '<button type="button" class="btn btn-return-pending" data-act="pend" data-id="' + id + '" title="Return to pending" aria-label="Return to pending">'
                + '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5"/><path d="M12 8v4l-2 2"/></svg>'
                + '</button>';
        }
        if (can('delete')) {
            actions += '<button type="button" class="btn btn-sm btn-danger" data-act="delete" data-id="' + id + '" title="Delete">&#10005;</button>';
        }
        // Printable registration card — the endpoint resolves the REGISTRATION
        // number (not the row id), so rows without one get no link.
        if (rec.registration) {
            actions += '<a class="btn btn-sm btn-light" target="_blank" rel="noopener" title="Registration card"'
                + ' href="/api/registrationpdf/' + encodeURIComponent(String(rec.registration)) + '">&#128424;</a>';
        }
        actions += '</div>';

        return '<tr><td class="text-center">' + ((pageNum - 1) * PER_PAGE + i + 1) + '</td>' +
            '<td>' + BK.esc(rec.registration) + '</td>' +
            '<td class="text-center">' + BK.esc(rec.roll) + '</td>' +
            '<td>' + BK.esc(rec.name) + '</td>' +
            '<td class="text-center">' + BK.esc(className) + '</td>' +
            '<td>' + BK.esc(rec.center_name) + '</td>' +
            '<td>' + BK.esc(rec.institute_name) + '</td>' +
            '<td class="text-center">' + badge + '</td>' +
            '<td>' + actions + '</td></tr>';
    }

    function reload() {
        const url = BK.qs('/api/students', {
            page: pageNum,
            per_page: PER_PAGE,
            session: val(sessionEl),
            class: val(classEl),
            status: val(statusEl),
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

    const reloadFromFilters = function () { pageNum = 1; reload(); syncSelectionSummaries(); };

    [sessionEl, classEl, statusEl, instituteEl].forEach(function (el) {
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
        syncInstituteRecordCount();
    }
    if (centerEl && instituteEl) {
        centerEl.addEventListener('change', function () {
            syncInstitutes();
            reloadFromFilters();
        });
        syncInstitutes();
    }

    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', function () {
            clearTimeout(searchTimer);
            sessionEl.value = initialSession;
            centerEl.value = '';
            instituteEl.value = '';
            classEl.value = '';
            statusEl.value = '';
            searchEl.value = '';
            syncInstitutes();
            pageNum = 1;
            reloadFromFilters();
        });
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                pageNum = 1;
                reloadFromFilters();
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
        if (modalEl) { modalEl.classList.remove('hidden'); }
        ['m-name', 'm-registration', 'm-roll', 'm-father_name', 'm-mother_name', 'm-center_name', 'm-institute_name', 'm-email', 'm-phone', 'm-religion', 'm-present_address', 'm-permanent_address', 'm-dob', 'm-gender', 'm-class', 'm-session', 'm-division_name', 'm-district_name', 'm-upazila_name', 'm-education_type', 'm-principal_name', 'm-note'].forEach(function (field) {
            setText(field, '—');
        });
        setText('m-name', 'Loading student details…');
        const photo = document.getElementById('modal-photo');
        if (photo) { photo.classList.add('hidden'); photo.removeAttribute('src'); }
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
        }).catch(function (err) {
            setText('m-name', 'Student details could not be loaded.');
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
        if (act === 'pend') {
            const studentName = btn.closest('tr').children[3].textContent.trim();
            BK.confirm('Move ' + studentName + ' back to Pending? The student will be excluded from active exam documents until approved again.').then(function (confirmed) {
                if (!confirmed) { return; }
                BK.post('/api/students/' + encodeURIComponent(id) + '/pend', {}).then(function () {
                    BK.toast('Student moved to pending approval.', 'success');
                    reload();
                    BK.get('/api/pending-students?per_page=1', {cache: 'no-store'}).then(function (json) {
                        if (window.updatePendingBadges) {
                            window.updatePendingBadges((json && json.data && json.data.total) || 0);
                        }
                    }).catch(function () {});
                }).catch(function (err) {
                    BK.toast(err.message || 'Could not move the student to Pending.', 'error');
                });
            });
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
    syncInstituteRecordCount();
    syncSelectionSummaries();
});
</script>
