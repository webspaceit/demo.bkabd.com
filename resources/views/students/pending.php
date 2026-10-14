<?php
/**
 * Pending registration queue — GET /pending-students.
 * Students are registered as PENDING (active = 0) and stay in this queue
 * until a user with student-edit approves them (POST /api/pending-students/
 * {id}/approve). Approved students become Active and join the exam documents
 * (mark entry, admit card, seat plan, certificates); rejecting removes the
 * registration. Columns mirror the students list plus a queue-specific
 * "Registered" date and Approve/Reject actions.
 *
 * $sessions / $centers / $institutes are rendered server-side so the filter
 * bar works without extra API calls; $filters carries the query-string state.
 */
$classes = $classes ?? [1, 2, 3, 4, 5, 6];
$filters = $filters ?? [];
// Default the session dropdown to the active session (query string wins).
$activeSession = (string) ($activeSession ?? '');
$selectedSession = array_key_exists('session', $filters) && $filters['session'] !== null
    ? (string) $filters['session']
    : $activeSession;
?>
<div class="space-y-4">
    <div class="card" id="pending-list" data-perm-approve="<?= can('student-edit') ? '1' : '0' ?>">
        <div class="card-header">
            <div class="min-w-0">
                <h1 class="text-base font-semibold text-slate-800">Pending Registration (<span id="pending-total">0</span>)</h1>
                <p class="text-xs text-slate-400 mt-1">New registrations wait here until they are approved. Approved students become Active and join the exam documents.</p>
            </div>
        </div>
        <div class="card-body space-y-4">
            <div class="filter-bar grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
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
                    <label for="f-center">Center</label>
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
                    <label for="f-institute">Institute</label>
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
                    <input type="search" id="f-search" class="w-full" placeholder="Name, reg. no, roll, institute..." value="<?= e($filters['search'] ?? '') ?>">
                    <label for="f-search">Search</label>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:10px">Sl.</th>
                            <th>Reg. No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Center</th>
                            <th>Institute</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th style="width:240px">Action</th>
                        </tr>
                    </thead>
                    <tbody id="pending-rows">
                        <tr><td colspan="9" class="text-center py-8 text-slate-400">Loading…</td></tr>
                    </tbody>
                </table>
            </div>

            <div id="pending-pagination"></div>
        </div>
    </div>
</div>

<!-- Student details modal (data loaded from GET /api/students/{id}) -->
<?= partial('students._details_modal') ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const listBox = document.getElementById('pending-list');
    const rowsEl = document.getElementById('pending-rows');
    if (!rowsEl || !listBox) { return; }

    const totalEl = document.getElementById('pending-total');
    const pagerEl = document.getElementById('pending-pagination');
    const sessionEl = document.getElementById('f-session');
    const classEl = document.getElementById('f-class');
    const centerEl = document.getElementById('f-center');
    const instituteEl = document.getElementById('f-institute');
    const searchEl = document.getElementById('f-search');
    const modalEl = document.getElementById('student-modal');
    const PER_PAGE = 20;
    const BADGE_SEL = '[data-nav-badge="/pending-students"]';

    const canApprove = listBox.getAttribute('data-perm-approve') === '1';
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
        const registered = String(rec.created_at || '').slice(0, 10);
        const badge = '<span class="badge bg-amber-50 text-amber-800">Pending</span>';

        let actions = '<div class="row-actions flex items-center gap-1">';
        actions += '<button type="button" class="btn btn-sm btn-light" data-act="view" data-id="' + id + '" title="Preview">&#128065;</button>';
        if (canApprove) {
            actions += '<button type="button" class="btn btn-sm btn-primary" data-act="approve" data-id="' + id + '" title="Approve">&#10003; Approve</button>';
            actions += '<button type="button" class="btn btn-sm btn-danger" data-act="reject" data-id="' + id + '" title="Reject">&#10005;</button>';
        }
        // Printable registration card — the endpoint resolves the REGISTRATION
        // number (not the row id), so rows without one get no link.
        if (rec.registration) {
            actions += '<a class="btn btn-sm btn-light" target="_blank" rel="noopener" title="Registration card"'
                + ' href="/api/registrationpdf/' + encodeURIComponent(String(rec.registration)) + '">&#128424;</a>';
        }
        actions += '</div>';

        return '<tr><td>' + ((pageNum - 1) * PER_PAGE + i + 1) + '</td>' +
            '<td>' + BK.esc(rec.registration) + '</td>' +
            '<td>' + BK.esc(rec.name) + '</td>' +
            '<td>' + BK.esc(className) + '</td>' +
            '<td>' + BK.esc(rec.center_name) + '</td>' +
            '<td>' + BK.esc(rec.institute_name) + '</td>' +
            '<td>' + BK.esc(registered) + '</td>' +
            '<td>' + badge + '</td>' +
            '<td>' + actions + '</td></tr>';
    }

    function reload() {
        const url = BK.qs('/api/pending-students', {
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
                : '<tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No pending registrations</td></tr>';
            if (pager.current_page) { pageNum = pager.current_page; }
            if (pagerEl) {
                pagerEl.innerHTML = '';
                BK.renderPagination(pagerEl, pager);
            }
        }).catch(function (err) {
            rowsEl.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-red-500">Could not load the queue</td></tr>';
            BK.toast(err.message || 'Could not load the queue', 'error');
        });
    }

    // Keep the sidebar badge in sync after an approve/reject without a full
    // page reload (unfiltered total = the badge count).
    function syncNavBadge() {
        BK.get('/api/pending-students?per_page=1').then(function (json) {
            const total = (json && json.data && json.data.total) || 0;
            document.querySelectorAll(BADGE_SEL).forEach(function (el) {
                if (total > 0) {
                    el.textContent = total.toLocaleString('en-US');
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            });
        }).catch(function () { /* the next page load re-renders it */ });
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
        if (act === 'approve') {
            BK.post('/api/pending-students/' + encodeURIComponent(id) + '/approve').then(function () {
                BK.toast('Registration approved successfully.', 'success');
                reload();
                syncNavBadge();
            }).catch(function (err) {
                BK.toast(err.message || 'Could not approve the registration', 'error');
            });
            return;
        }
        if (act === 'reject') {
            BK.confirm('Reject this registration? The record will be removed permanently.').then(function (confirmed) {
                if (!confirmed) { return; }
                BK.post('/api/pending-students/' + encodeURIComponent(id) + '/reject').then(function () {
                    BK.toast('Registration rejected and removed.', 'success');
                    reload();
                    syncNavBadge();
                }).catch(function (err) {
                    BK.toast(err.message || 'Could not reject the registration', 'error');
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
