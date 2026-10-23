<?php
/**
 * Pending registration queue — GET /pending-students.
 * Students are registered as PENDING (active = 0) and stay in this queue
 * until a user with student-approve approves them (POST /api/pending-students/
 * {id}/approve). Approved students become Active and join the exam documents
 * (mark entry, admit card, seat plan, certificates); rejecting removes the
 * registration. Columns mirror the students list plus a queue-specific
 * "Submitted" date with a waiting-age hint and Approve/Reject actions.
 *
 * $sessions / $centers / $institutes are rendered server-side so the filter
 * bar works without extra API calls; $filters carries the query-string state.
 */
$classes = $classes ?? [1, 2, 3, 4, 5, 6];
$filters = $filters ?? [];
$canApprovePending = can('student-approve');
// Default the session dropdown to the active session (query string wins).
$activeSession = (string) ($activeSession ?? '');
$selectedSession = array_key_exists('session', $filters) && $filters['session'] !== null
    ? (string) $filters['session']
    : $activeSession;
?>
<style>
/* Skeleton shimmer while rows load (animate-pulse is not in the prebuilt CSS). */
#pending-rows .pending-skel {
    height: 12px;
    border-radius: 6px;
    background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
    background-size: 300% 100%;
    animation: pending-skel 1.2s ease infinite;
}
@keyframes pending-skel {
    from { background-position: 100% 0; }
    to { background-position: -100% 0; }
}
</style>
<div class="space-y-4">
    <div class="card" id="pending-list" data-perm-approve="<?= $canApprovePending ? '1' : '0' ?>">
        <div class="card-header">
            <div class="flex items-center gap-3 min-w-0">
                <span class="inline-flex items-center justify-center rounded-full bg-emerald-50 shrink-0" style="width:2.5rem;height:2.5rem" aria-hidden="true">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-slate-800">Pending Registration</h1>
                    <p class="text-xs text-slate-400 mt-0.5">New registrations wait here until they are approved. Approved students become Active and join the exam documents.</p>
                </div>
            </div>
            <span id="pending-count-chip"
                  class="inline-flex items-center gap-2 shrink-0 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800"
                  title="Registrations awaiting approval">
                <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                <span id="pending-total">0</span>
                <span class="hidden sm:inline font-medium">awaiting approval</span>
            </span>
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
                    <label for="f-center" class="f-center-search">Center</label>
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
                    <label for="f-institute" class="f-institute">Institute</label>
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

            <?php if ($canApprovePending): ?>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">Select registrations on this page to approve them together.</p>
                <button type="button" id="pending-bulk-approve" class="btn btn-primary" disabled>Approve selected (0)</button>
            </div>
            <?php endif; ?>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <?php if ($canApprovePending): ?><th style="width:36px"><input type="checkbox" id="pending-select-all" aria-label="Select all registrations on this page"></th><?php endif; ?>
                            <th style="width:10px">#</th>
                            <th>Student</th>
                            <th>Reg. No</th>
                            <th>Roll No</th>
                            <th>Class</th>
                            <th>Center</th>
                            <th>Institute</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th style="width:220px">Action</th>
                        </tr>
                    </thead>
                    <tbody id="pending-rows">
                        <tr><td colspan="<?= $canApprovePending ? '11' : '10' ?>" class="text-center py-8 text-slate-400">Loading…</td></tr>
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

    const chipEl = document.getElementById('pending-count-chip');
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
    const columnCount = canApprove ? 11 : 10;
    const selectAllEl = document.getElementById('pending-select-all');
    const bulkApproveBtn = document.getElementById('pending-bulk-approve');
    const selectedIds = new Set();
    const val = function (el) { return el ? el.value : ''; };

    const CLASS_NAMES = { 6: 'KG', 1: 'ONE', 2: 'TWO', 3: 'THREE', 4: 'FOUR', 5: 'FIVE' };
    const GENDERS = { 1: 'Male', 2: 'Female', 3: 'Others' };
    const RELIGIONS = { 1: 'Muslim', 2: 'Hindu', 3: 'Buddhist', 4: 'Christian' };
    const EDUCATION = { 1: 'Bangla Medium', 2: 'English Version', 3: 'Arabic Version' };
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    let pageNum = 1;
    let searchTimer = null;

    // -----------------------------------------------------------------
    // Inline SVG icons (same lucide-style language as the sidebar).
    // -----------------------------------------------------------------
    function svgIcon(paths, cls) {
        return '<svg class="' + (cls || 'w-4 h-4') + '" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">' + paths + '</svg>';
    }
    const ICON = {
        eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        check: '<path d="m5 13 4 4L19 7"/>',
        checkCircle: '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 5-5"/>',
        x: '<path d="M18 6 6 18M6 6l12 12"/>',
        printer: '<path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="2"/>',
        search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        clock: svgIcon('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'w-4 h-4 shrink-0')
    };

    // -----------------------------------------------------------------
    // Header count chip: amber "N awaiting approval" or a green all-clear.
    // -----------------------------------------------------------------
    function renderCountChip(total) {
        if (!chipEl) { return; }
        if (total > 0) {
            chipEl.className = 'inline-flex items-center gap-2 shrink-0 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800';
            chipEl.innerHTML = svgIcon('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'w-4 h-4 text-amber-500 shrink-0')
                + '<span id="pending-total">' + Number(total).toLocaleString('en-US') + '</span>'
                + '<span class="hidden sm:inline font-medium">awaiting approval</span>';
        } else {
            chipEl.className = 'inline-flex items-center gap-2 shrink-0 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-600';
            chipEl.innerHTML = svgIcon(ICON.checkCircle, 'w-4 h-4 shrink-0')
                + '<span id="pending-total">0</span>'
                + '<span class="hidden sm:inline font-medium">all caught up</span>';
        }
    }

    // -----------------------------------------------------------------
    // Date + waiting-age helpers (server timestamps are plain strings).
    // -----------------------------------------------------------------
    function utcDay(ts) {
        const p = String(ts || '').slice(0, 10).split('-').map(Number);
        return Date.UTC(p[0] || 0, (p[1] || 1) - 1, p[2] || 1);
    }
    function daysWaiting(ts) {
        const now = new Date();
        const today = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());
        return Math.floor((today - utcDay(ts)) / 86400000);
    }
    function fmtDate(ts) {
        const p = String(ts || '').slice(0, 10).split('-');
        if (p.length !== 3) { return '—'; }
        return (p[2] || '') + ' ' + (MONTHS[Number(p[1]) - 1] || '?') + ' ' + p[0];
    }
    function waitingLine(ts) {
        const days = daysWaiting(ts);
        const text = days <= 0 ? 'submitted today' : (days === 1 ? '1 day waiting' : days + ' days waiting');
        const cls = days >= 3 ? 'text-amber-500 font-medium' : 'text-slate-400';
        return '<div class="text-xs ' + cls + ' mt-0.5">' + text + '</div>';
    }

    // -----------------------------------------------------------------
    // Photo avatar — uploaded photo or a soft emerald person placeholder.
    // -----------------------------------------------------------------
    // -----------------------------------------------------------------
    // Loading skeletons + empty states.
    // -----------------------------------------------------------------
    function skeletonRows(count) {
        const widths = canApprove
            ? [16, 16, 110, 78, 40, 34, 96, 120, 66, 58, 88]
            : [16, 110, 78, 40, 34, 96, 120, 66, 58, 88];
        let out = '';
        for (let r = 0; r < count; r++) {
            out += '<tr>' + widths.map(function (w) {
                return '<td><div class="pending-skel" style="width:' + w + 'px"></div></td>';
            }).join('') + '</tr>';
        }
        return out;
    }

    function updateBulkControls() {
        const selectedCount = selectedIds.size;
        if (bulkApproveBtn) {
            bulkApproveBtn.disabled = selectedCount === 0;
            bulkApproveBtn.textContent = 'Approve selected (' + selectedCount + ')';
        }
        if (selectAllEl) {
            const rowChecks = Array.from(rowsEl.querySelectorAll('.pending-select-row'));
            selectAllEl.checked = rowChecks.length > 0 && rowChecks.every(function (checkbox) { return checkbox.checked; });
            selectAllEl.indeterminate = rowChecks.some(function (checkbox) { return checkbox.checked; }) && !selectAllEl.checked;
        }
    }

    function clearSelection() {
        selectedIds.clear();
        if (selectAllEl) { selectAllEl.checked = false; selectAllEl.indeterminate = false; }
        updateBulkControls();
    }

    function hasActiveFilters() {
        return !!(val(sessionEl) || val(classEl) || val(centerEl) || val(instituteEl) || val(searchEl));
    }

    function emptyStateHtml() {
        if (hasActiveFilters()) {
            return '<tr><td colspan="' + columnCount + '" class="px-4 py-10 text-center">'
                + '<div class="inline-flex flex-col items-center gap-2 text-slate-400">'
                + svgIcon(ICON.search, 'w-6 h-6')
                + '<p class="text-sm font-medium text-slate-500">No pending registrations match the current filters</p>'
                + '<p class="text-xs">Try changing or clearing them to see the rest of the queue.</p>'
                + '<button type="button" class="btn btn-sm btn-light" data-act="reset-filters">Reset filters</button>'
                + '</div></td></tr>';
        }
        return '<tr><td colspan="' + columnCount + '" class="px-4 py-10 text-center">'
            + '<div class="inline-flex flex-col items-center gap-2">'
            + '<span class="inline-flex items-center justify-center rounded-full bg-emerald-50" style="width:3rem;height:3rem">'
            + svgIcon(ICON.checkCircle, 'w-6 h-6 text-emerald-600') + '</span>'
            + '<p class="text-sm font-medium text-slate-700">All caught up — no pending registrations</p>'
            + '<p class="text-xs text-slate-400">New student registrations will appear here for your approval.</p>'
            + '</div></td></tr>';
    }

    // -----------------------------------------------------------------
    // Rows.
    // -----------------------------------------------------------------
    function dash(value) {
        return (value === null || value === undefined || String(value) === '') ? '—' : BK.esc(String(value));
    }

    function rowHtml(rec, i) {
        const id = BK.esc(String(rec.id));
        const className = CLASS_NAMES[rec.class] || '';

        const statusBadge = '<span class="badge inline-flex items-center gap-1 border border-amber-200 bg-amber-50 text-amber-800">'
            + '<span class="shrink-0 rounded-full" style="width:.375rem;height:.375rem;background:#f59e0b"></span>Pending</span>';

        let actions = '<div class="row-actions flex items-center gap-1">';
        actions += '<button type="button" class="btn btn-sm btn-light" data-act="view" data-id="' + id + '" title="Preview" aria-label="Preview">'
            + svgIcon(ICON.eye) + '</button>';
        if (canApprove) {
            actions += '<button type="button" class="btn btn-sm btn-primary inline-flex gap-1" data-act="approve" data-id="' + id + '" title="Approve">'
                + svgIcon(ICON.check, 'w-3.5 h-3.5') + '<span class="hidden lg:inline">Approve</span></button>';
            actions += '<button type="button" class="btn btn-sm btn-danger" data-act="reject" data-id="' + id + '" title="Reject" aria-label="Reject">'
                + svgIcon(ICON.x) + '</button>';
        }
        // Printable registration card — the endpoint resolves the REGISTRATION
        // number (not the row id), so rows without one get no link.
        if (rec.registration) {
            actions += '<a class="btn btn-sm btn-light" target="_blank" rel="noopener" title="Registration card" aria-label="Registration card"'
                + ' href="/api/registrationpdf/' + encodeURIComponent(String(rec.registration)) + '">'
                + svgIcon(ICON.printer) + '</a>';
        }
        actions += '</div>';

        return '<tr>'
            + (canApprove ? '<td><input type="checkbox" class="pending-select-row" value="' + id + '" aria-label="Select ' + BK.esc(rec.name || 'student') + '"></td>' : '')
            + '<td class="text-slate-400">' + ((pageNum - 1) * PER_PAGE + i + 1) + '</td>'
            + '<td><div class="min-w-0"><div class="font-medium text-slate-800">' + BK.esc(rec.name) + '</div>'
            + (rec.phone ? '<div class="text-xs text-slate-400 mt-0.5">' + BK.esc(rec.phone) + '</div>' : '')
            + '</div></div></td>'
            + '<td><div class="font-semibold text-slate-800">' + BK.esc(rec.registration) + '</div></td>'
            + '<td>' + dash(rec.roll) + '</td>'
            + '<td><span class="badge badge-gray">' + BK.esc(className) + '</span></td>'
            + '<td>' + BK.esc(rec.center_name) + '</td>'
            + '<td>' + BK.esc(rec.institute_name) + '</td>'
            + '<td><div class="text-slate-700">' + fmtDate(rec.created_at) + '</div>' + waitingLine(rec.created_at) + '</td>'
            + '<td>' + statusBadge + '</td>'
            + '<td>' + actions + '</td></tr>';
    }

    function reload() {
        clearSelection();
        rowsEl.innerHTML = skeletonRows(5);
        const url = BK.qs('/api/pending-students', {
            page: pageNum,
            per_page: PER_PAGE,
            session: val(sessionEl),
            class: val(classEl),
            center_id: val(centerEl),
            institute_id: val(instituteEl),
            search: val(searchEl)
        });
        BK.get(url, {cache: 'no-store'}).then(function (json) {
            const pager = (json && json.data) || {};
            const rows = pager.data || [];
            renderCountChip(pager.total || 0);
            rowsEl.innerHTML = rows.length
                ? rows.map(rowHtml).join('')
                : emptyStateHtml();
            if (pager.current_page) { pageNum = pager.current_page; }
            if (pagerEl) {
                pagerEl.innerHTML = '';
                BK.renderPagination(pagerEl, pager);
            }
        }).catch(function (err) {
            renderCountChip(0);
            rowsEl.innerHTML = '<tr><td colspan="' + columnCount + '" class="px-4 py-8 text-center text-red-600">Could not load the queue</td></tr>';
            BK.toast(err.message || 'Could not load the queue', 'error');
        });
    }

    // Keep the visible pending-count chip current while this page stays open.
    // The filtered total updates here; the sidebar badge tracks the global total.
    function refreshVisibleCount() {
        const url = BK.qs('/api/pending-students', {
            page: 1,
            per_page: 1,
            session: val(sessionEl),
            class: val(classEl),
            center_id: val(centerEl),
            institute_id: val(instituteEl),
            search: val(searchEl)
        });
        BK.get(url).then(function (json) {
            const pager = (json && json.data) || {};
            renderCountChip(pager.total || 0);
        }).catch(function () { /* retain the last displayed total */ });
    }

    setInterval(refreshVisibleCount, 1000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { refreshVisibleCount(); }
    });

    // Keep the badges (sidebar + top bar) in sync after an approve/reject
    // without a full page reload (unfiltered total = the badge count).
    // window.updatePendingBadges is defined in layouts/dashboard.php.
    function syncNavBadge() {
        BK.get('/api/pending-students?per_page=1').then(function (json) {
            const total = (json && json.data && json.data.total) || 0;
            if (window.updatePendingBadges) {
                window.updatePendingBadges(total);
                return;
            }
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

    if (selectAllEl) {
        selectAllEl.addEventListener('change', function () {
            rowsEl.querySelectorAll('.pending-select-row').forEach(function (checkbox) {
                checkbox.checked = selectAllEl.checked;
                if (checkbox.checked) { selectedIds.add(Number(checkbox.value)); }
                else { selectedIds.delete(Number(checkbox.value)); }
            });
            updateBulkControls();
        });
    }
    rowsEl.addEventListener('change', function (event) {
        const checkbox = event.target.closest('.pending-select-row');
        if (!checkbox) { return; }
        const id = Number(checkbox.value);
        if (checkbox.checked) { selectedIds.add(id); }
        else { selectedIds.delete(id); }
        updateBulkControls();
    });
    if (bulkApproveBtn) {
        bulkApproveBtn.addEventListener('click', function () {
            const ids = Array.from(selectedIds);
            if (!ids.length) { return; }
            BK.confirm('Approve ' + ids.length + ' selected pending registration(s)?').then(function (confirmed) {
                if (!confirmed) { return; }
                bulkApproveBtn.disabled = true;
                BK.post('/api/pending-students/approve-bulk', { ids: ids }).then(function (json) {
                    const approved = Number((json && json.data && json.data.approved) || 0);
                    BK.toast(approved + ' registration(s) approved successfully.', 'success');
                    clearSelection();
                    reload();
                    syncNavBadge();
                }).catch(function (err) {
                    BK.toast(err.message || 'Could not approve selected registrations.', 'error');
                }).finally(updateBulkControls);
            });
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
        if (act === 'reset-filters') {
            [sessionEl, classEl, centerEl, instituteEl].forEach(function (el) {
                if (el) { el.value = ''; }
            });
            if (searchEl) { searchEl.value = ''; }
            syncInstitutes();
            pageNum = 1;
            reload();
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
