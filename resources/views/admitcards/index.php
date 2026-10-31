<?php
/**
 * Admit Cards index page
 */
$classes = [1, 2, 3, 4, 5, 6];
$centerCount = count((array) ($centers ?? []));
$instituteCount = (int) ($instituteCount ?? 0);
?>
<div class="space-y-4">
    <style>
        #admit-pagination-footer { display:flex; width:100%; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; }
        #admit-per-page-control { margin-left:auto; white-space:nowrap; }
    </style>
    <div class="card" id="admitcard-list">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">Admit Cards</h1>
            <div class="flex flex-wrap items-center gap-2">
            <button type="button" id="btn-print-center" class="btn btn-primary btn-sm hidden">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print All (Center) <span class="ml-1" data-page-count>0 pages</span>
            </button>
            <button type="button" id="btn-print-institute" class="btn btn-primary btn-sm hidden" disabled>
                Print All (Institute) <span class="ml-1" data-page-count>0 pages</span>
            </button>
            <button type="button" id="btn-print-class" class="btn btn-primary btn-sm hidden" disabled>
                Print All (Class) <span class="ml-1" data-page-count>0 pages</span>
            </button>
            </div>
        </div>
        <div class="card-body space-y-4">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div id="selected-center-summary" class="hidden flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3" role="status" aria-live="polite">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Selected Center</p>
                    <p id="selected-center-name" class="mt-0.5 truncate text-sm font-semibold text-emerald-950"></p>
                </div>
                <div class="shrink-0 rounded-full bg-white px-3 py-1.5 text-sm font-bold text-emerald-800 ring-1 ring-emerald-200">
                    <span id="selected-center-student-count">…</span> active students
                </div>
            </div>
            <div id="selected-institute-summary" class="hidden flex flex-wrap items-center justify-between gap-3 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3" role="status" aria-live="polite">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">Selected Institute</p>
                    <p id="selected-institute-name" class="mt-0.5 truncate text-sm font-semibold text-teal-950"></p>
                </div>
                <div class="shrink-0 rounded-full bg-white px-3 py-1.5 text-sm font-bold text-teal-800 ring-1 ring-teal-200">
                    <span id="selected-institute-student-count">…</span> active students
                </div>
            </div>
            </div>

            <!-- Filter bar — same style as students list -->
            <div class="filter-bar grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="fl">
                    <select id="f-session" class="w-full">
                        <option value="">All Sessions</option>
                        <?php foreach ((array) $sessions as $s): ?>
                            <option value="<?= (int) $s['session'] ?>"
                                <?= (string) ($s['session'] ?? '') === (string) $activeSession ? 'selected' : '' ?>>
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
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="f-center" class="f-center-search">Center <span class="filter-record-count" id="center-record-count" title="Available centers"><?= $centerCount ?></span></label>
                </div>
                <div class="fl">
                    <select id="f-institute" class="w-full">
                        <option value="">All Institutes</option>
                    </select>
                    <label for="f-institute" class="f-institute">Institute <span class="filter-record-count" id="institute-record-count" data-total-count="<?= $instituteCount ?>" title="Available institutes"><?= $instituteCount ?></span></label>
                </div>
                <div class="fl">
                    <select id="f-class" class="w-full">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int) $c ?>"><?= e(ClassName($c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="f-class">Class <span class="filter-record-count" id="class-record-count" title="Classes with active students"><?= count($classes) ?></span></label>
                </div>
                <div class="fl" style="display:flex;align-items:center;gap:.5rem">
                    <input type="search" id="f-search" class="w-full min-w-0" style="flex:1" placeholder="Name, reg. no, roll...">
                    <label for="f-search">Search</label>
                    <button type="button" id="btn-reset-filters" class="btn btn-light shrink-0">Reset</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:10px">Sl.</th>
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
                    <tbody id="admit-rows">
                        <tr><td colspan="10" class="text-center py-8 text-slate-400">Loading students…</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="admit-pagination-footer" class="mt-4">
                <div id="admit-pagination" class="flex flex-wrap items-center gap-2"></div>
                <label id="admit-per-page-control" for="admit-per-page" class="flex items-center gap-2 text-sm text-slate-500">
                    Rows per page
                    <select id="admit-per-page" class="input" style="width:auto;min-width:5rem">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="150">150</option>
                        <option value="200">200</option>
                    </select>
                </label>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const sessionEl   = document.getElementById('f-session');
    const centerEl    = document.getElementById('f-center');
    const instituteEl = document.getElementById('f-institute');
    const classEl     = document.getElementById('f-class');
    const searchEl    = document.getElementById('f-search');
    const resetFiltersBtn = document.getElementById('btn-reset-filters');
    const rowsEl      = document.getElementById('admit-rows');
    const pagerEl     = document.getElementById('admit-pagination');
    const perPageEl   = document.getElementById('admit-per-page');
    const btnPrintCenter = document.getElementById('btn-print-center');
    const btnPrintInstitute = document.getElementById('btn-print-institute');
    const btnPrintClass = document.getElementById('btn-print-class');
    const instituteCountEl = document.getElementById('institute-record-count');
    const classCountEl = document.getElementById('class-record-count');
    const centerSummaryEl = document.getElementById('selected-center-summary');
    const centerSummaryNameEl = document.getElementById('selected-center-name');
    const centerSummaryCountEl = document.getElementById('selected-center-student-count');
    const instituteSummaryEl = document.getElementById('selected-institute-summary');
    const instituteSummaryNameEl = document.getElementById('selected-institute-name');
    const instituteSummaryCountEl = document.getElementById('selected-institute-student-count');
    const CLASS_NAMES = { 6:'KG', 1:'ONE', 2:'TWO', 3:'THREE', 4:'FOUR', 5:'FIVE' };

    let pageNum     = 1;
    let perPage     = Number(perPageEl?.value || 20);
    let searchTimer = null;
    let centerCountRequest = 0;
    let optionCountRequest = 0;
    let studentCounts = {center_counts: {}, institute_counts: {}, class_counts: {}};
    let listRequest = 0;
    let listController = null;
    const val = el => el ? el.value : '';
    const initialSession = val(sessionEl);

    function applyOptionStudentCounts() {
        [centerEl, instituteEl, classEl].forEach(function (select, index) {
            if (!select) return;
            const counts = index === 0 ? studentCounts.center_counts : (index === 1 ? studentCounts.institute_counts : studentCounts.class_counts);
            Array.from(select.options).forEach(function (option) {
                if (!option.value || option.disabled) return;
                if (!option.dataset.baseLabel) option.dataset.baseLabel = option.textContent.trim();
                const count = Number(counts[String(option.value)] || 0);
                option.textContent = option.dataset.baseLabel + ' (' + count.toLocaleString('en-US') + ')';
            });
        });
    }

    function refreshOptionStudentCounts() {
        const requestId = ++optionCountRequest;
        BK.get(BK.qs('/api/student-filter-counts', {
            session: val(sessionEl),
            center_id: val(centerEl),
            institute_id: val(instituteEl),
            status: 'active'
        })).then(function (json) {
            if (requestId !== optionCountRequest) return;
            studentCounts = (json && json.data) || {center_counts: {}, institute_counts: {}, class_counts: {}};
            applyOptionStudentCounts();
            if (classCountEl) {
                const availableClasses = Object.keys(studentCounts.class_counts || {}).filter(function (key) {
                    return Number(studentCounts.class_counts[key] || 0) > 0;
                }).length;
                classCountEl.textContent = availableClasses.toLocaleString('en-US');
                classCountEl.title = availableClasses + ' class(es) with active students for the selected filters';
            }
        }).catch(function () {
            // The admit-card filters remain usable if the optional counts fail.
        });
    }

    function syncInstituteRecordCount(count) {
        if (!instituteCountEl) return;
        const totalCount = Number(instituteCountEl.dataset.totalCount || 0);
        const visibleCount = typeof count === 'number'
            ? count
            : (val(centerEl) ? Math.max(0, instituteEl.options.length - 1) : totalCount);
        instituteCountEl.textContent = visibleCount.toLocaleString('en-US');
        instituteCountEl.title = visibleCount + ' available institute(s)';
    }

    let instituteSummaryRequest = 0;
    function syncInstituteSummary() {
        const instituteId = val(instituteEl);
        const requestId = ++instituteSummaryRequest;
        if (!instituteSummaryEl) return;
        instituteSummaryEl.classList.toggle('hidden', !instituteId);
        if (!instituteId) return;

        const selectedInstitute = instituteEl.options[instituteEl.selectedIndex];
        if (instituteSummaryNameEl) instituteSummaryNameEl.textContent = selectedInstitute ? (selectedInstitute.dataset.baseLabel || selectedInstitute.textContent.trim()) : '';
        if (instituteSummaryCountEl) instituteSummaryCountEl.textContent = '…';

        BK.get(BK.qs('/api/students', {
            session: val(sessionEl),
            center_id: val(centerEl),
            institute_id: instituteId,
            status: 'active',
            per_page: 1,
            page: 1
        })).then(function (json) {
            if (requestId !== instituteSummaryRequest) return;
            const total = Number((json.data && json.data.total) || 0);
            if (instituteSummaryCountEl) instituteSummaryCountEl.textContent = total.toLocaleString('en-US');
        }).catch(function () {
            if (requestId === instituteSummaryRequest && instituteSummaryCountEl) instituteSummaryCountEl.textContent = 'unavailable';
        });
    }

    // Show/hide Print All button based on center selection
    function syncPrintBtn() {
        const cid = centerEl.value;
        const requestId = ++centerCountRequest;
        const selectedCenter = centerEl.options[centerEl.selectedIndex];
        if (btnPrintCenter) {
            btnPrintCenter.classList.toggle('hidden', !cid);
            btnPrintCenter.dataset.centerId = cid || '0';
            btnPrintCenter.disabled = !cid;
            const count = btnPrintCenter.querySelector('[data-page-count]');
            if (count) count.textContent = cid ? '…' : '0 pages';
        }
        if (centerSummaryEl) {
            centerSummaryEl.classList.toggle('hidden', !cid);
            if (cid && centerSummaryNameEl) centerSummaryNameEl.textContent = selectedCenter ? (selectedCenter.dataset.baseLabel || selectedCenter.textContent.trim()) : '';
            if (cid && centerSummaryCountEl) centerSummaryCountEl.textContent = '…';
        }
        if (!cid) return;

        BK.get(BK.qs('/api/students', {
            session: val(sessionEl),
            center_id: cid,
            status: 'active',
            per_page: 1,
            page: 1
        })).then(function (json) {
            if (requestId !== centerCountRequest || !btnPrintCenter) return;
            const total = Number((json.data && json.data.total) || 0);
            if (centerSummaryCountEl) centerSummaryCountEl.textContent = total.toLocaleString('en-US');
            const pages = Math.ceil(total / 2);
            const count = btnPrintCenter.querySelector('[data-page-count]');
            if (count) count.textContent = pages + (pages === 1 ? ' page' : ' pages');
            btnPrintCenter.dataset.studentCount = String(total);
            btnPrintCenter.disabled = total === 0;
            btnPrintCenter.title = total + ' active student(s) · ' + pages + ' page(s)';
        }).catch(function () {
            if (requestId !== centerCountRequest || !btnPrintCenter) return;
            const count = btnPrintCenter.querySelector('[data-page-count]');
            if (count) count.textContent = 'count unavailable';
            btnPrintCenter.disabled = true;
            if (centerSummaryCountEl) centerSummaryCountEl.textContent = 'unavailable';
        });
    }

    let printCountRequest = 0;
    function syncInstitutePrintBtns() {
        const instituteId = val(instituteEl);
        const classId = val(classEl);
        const requestId = ++printCountRequest;
        [btnPrintInstitute, btnPrintClass].forEach(function (btn) {
            if (btn) {
                btn.classList.toggle('hidden', !instituteId || (btn === btnPrintClass && !classId));
                btn.disabled = true;
                const count = btn.querySelector('[data-page-count]');
                if (count) count.textContent = '…';
            }
        });
        if (!instituteId) return;

        const base = {
            session: val(sessionEl),
            center_id: val(centerEl),
            institute_id: instituteId,
            status: 'active',
            per_page: 1,
            page: 1
        };
        const instituteCount = BK.get(BK.qs('/api/students', base));
        const classCount = classId
            ? BK.get(BK.qs('/api/students', Object.assign({}, base, { class: classId })))
            : Promise.resolve(null);
        Promise.all([instituteCount, classCount]).then(function (results) {
            if (requestId !== printCountRequest) return;
            [[btnPrintInstitute, results[0]], [btnPrintClass, results[1]]].forEach(function (item) {
                const btn = item[0], json = item[1];
                if (!btn || !json) return;
                const total = Number((json.data && json.data.total) || 0);
                const pages = Math.ceil(total / 2);
                const count = btn.querySelector('[data-page-count]');
                if (count) count.textContent = pages + (pages === 1 ? ' page' : ' pages');
                btn.dataset.studentCount = String(total);
                btn.disabled = total === 0;
                btn.title = total + ' active student(s) · ' + pages + ' page(s)';
            });
        }).catch(function () {
            if (requestId !== printCountRequest) return;
            [btnPrintInstitute, btnPrintClass].forEach(function (btn) {
                if (btn && !btn.classList.contains('hidden')) {
                    const count = btn.querySelector('[data-page-count]');
                    if (count) count.textContent = 'count unavailable';
                    btn.disabled = true;
                }
            });
        });
    }

    if (btnPrintCenter) {
        btnPrintCenter.addEventListener('click', function () {
            const cid = btnPrintCenter.dataset.centerId || '0';
            window.open('/api/admitcard_center/' + encodeURIComponent(cid) + printQuery(), '_blank');
        });
    }

    function printQuery() {
        return '?session=' + encodeURIComponent(val(sessionEl));
    }
    if (btnPrintInstitute) {
        btnPrintInstitute.addEventListener('click', function () {
            window.open('/api/admitcard_institute/' + encodeURIComponent(val(instituteEl)) + printQuery(), '_blank');
        });
    }
    if (btnPrintClass) {
        btnPrintClass.addEventListener('click', function () {
            window.open('/api/admitcard_class/' + encodeURIComponent(val(instituteEl)) + '/' + encodeURIComponent(val(classEl)) + printQuery(), '_blank');
        });
    }

    // Cascade: center → institute
    centerEl.addEventListener('change', function () {
        instituteEl.innerHTML = '<option value="">All Institutes</option>';
        const cid = centerEl.value;
        syncInstituteRecordCount(0);
        if (!cid) {
            syncInstituteRecordCount();
            return;
        }
        BK.get('/api/instituteListByCenter?center_id=' + encodeURIComponent(cid))
            .then(function (res) {
                (res.data.instituteList || []).forEach(function (i) {
                    const o = document.createElement('option');
                    o.value = i.id;
                    o.textContent = i.name || '';
                    o.dataset.baseLabel = o.textContent;
                    instituteEl.appendChild(o);
                });
                applyOptionStudentCounts();
                syncInstituteRecordCount(instituteEl.options.length - 1);
            })
            .catch(() => BK.toast('Could not load institutes', 'error'));
    });

    function reload() {
        if (listController) listController.abort();
        listController = new AbortController();
        const requestId = ++listRequest;
        rowsEl.innerHTML = '<tr><td colspan="10" class="text-center py-8 text-slate-400">Loading students…</td></tr>';
        const url = BK.qs('/api/admitcards/search', {
            page:         pageNum,
            per_page:     perPage,
            session:      val(sessionEl),
            center_id:    val(centerEl),
            institute_id: val(instituteEl),
            class:        val(classEl),
            status:       'active',
            search:       val(searchEl),
        });
        BK.get(url, { signal: listController.signal }).then(function (json) {
            if (requestId !== listRequest) return;
            const pager = (json && json.data) || {};
            const rows  = pager.data || [];
            rowsEl.innerHTML = rows.length ? rows.map(function (r, idx) {
                const sl  = ((pager.current_page - 1) * pager.per_page) + idx + 1;
                const cls = CLASS_NAMES[r.class] || r.class;
                return '<tr>' +
                    '<td>' + sl + '</td>' +
                    '<td>' + BK.esc(r.registration || '') + '</td>' +
                    '<td>' + BK.esc(r.roll || '') + '</td>' +
                    '<td>' + BK.esc(r.name || '') + '</td>' +
                    '<td>' + BK.esc(r.father_name || '') + '</td>' +
                    '<td>' + BK.esc(r.mother_name || '') + '</td>' +
                    '<td>' + BK.esc(cls) + '</td>' +
                    '<td>' + BK.esc(r.center_name || '') + '</td>' +
                    '<td>' + BK.esc(r.institute_name || '') + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-primary"' +
                        ' data-student="' + BK.esc(String(r.id)) + '">View/Print</button></td>' +
                '</tr>';
            }).join('') : '<tr><td colspan="10" class="text-center py-8 text-slate-400">No matching students found. Check the session and filters, then search again.</td></tr>';

            if (pager.current_page) pageNum = pager.current_page;
            pagerEl.innerHTML = '';
            BK.renderPagination(pagerEl, pager);
        }).catch(function (err) {
            if (err.name === 'AbortError' || requestId !== listRequest) return;
            rowsEl.innerHTML = '<tr><td colspan="10" class="text-center py-8 text-red-500">Could not load list</td></tr>';
            BK.toast(err.message || 'Could not load list', 'error');
        });
    }

    // View/Print button
    rowsEl.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-student]');
        if (!btn) return;
        window.open('/api/admitcard_student/' + encodeURIComponent(btn.dataset.student), '_blank');
    });

    // Pagination
    pagerEl.addEventListener('click', function (e) {
        const a = e.target.closest('a');
        if (!a) return;
        e.preventDefault();
        pageNum = Number(new URL(a.href).searchParams.get('page') || 1);
        reload();
    });
    perPageEl?.addEventListener('change', function () {
        perPage = Number(perPageEl.value) || 20;
        pageNum = 1;
        reload();
    });

    // Filter change triggers reload
    [sessionEl, centerEl, instituteEl, classEl].forEach(el => el.addEventListener('change', () => {
        pageNum = 1;
        reload();
        syncPrintBtn();
        syncInstitutePrintBtns();
        syncInstituteSummary();
        if (el === centerEl || el === instituteEl) refreshOptionStudentCounts();
    }));
    sessionEl.addEventListener('change', refreshOptionStudentCounts);

    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', function () {
            clearTimeout(searchTimer);
            sessionEl.value = initialSession;
            centerEl.value = '';
            instituteEl.innerHTML = '<option value="">All Institutes</option>';
            classEl.value = '';
            searchEl.value = '';
            pageNum = 1;
            reload();
            syncPrintBtn();
            syncInstitutePrintBtns();
            syncInstituteRecordCount();
            syncInstituteSummary();
            refreshOptionStudentCounts();
        });
    }

    // Search with debounce
    searchEl.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { pageNum = 1; reload(); }, 350);
    });
    searchEl.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') { event.preventDefault(); clearTimeout(searchTimer); pageNum = 1; reload(); }
    });

    reload();
    syncPrintBtn();
    syncInstitutePrintBtns();
    syncInstituteRecordCount();
    syncInstituteSummary();
    refreshOptionStudentCounts();
});
</script>
