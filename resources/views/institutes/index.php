<?php
/**
 * Institute list page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/institutes/index.vue.
 */
?>
<div class="space-y-4" id="institutes-page" data-perm-edit="<?= can('institute-edit') ? '1' : '0' ?>" data-perm-delete="<?= can('institute-delete') ? '1' : '0' ?>" data-perm-validity="<?= can('institute-validity') ? '1' : '0' ?>">
    <style>
        #institute-pagination-footer { display:flex; width:100%; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; }
        #institute-per-page-control { margin-left:auto; }
    </style>
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All Institutes (<span id="institute-count">0</span>)</h1>
            <?php if (can('institute-create')): ?>
                <a href="/institute-registration" class="btn btn-primary btn-sm">Add New Institute</a>
            <?php endif; ?>
        </div>
        <div class="card-body space-y-4">
            <div class="filter-bar">
                <div id="institute-center-filter" style="display:none" class="items-center gap-2 text-sm text-slate-600">
                    <span>Filtered by center: <span id="institute-center-filter-text" class="font-medium text-slate-800"></span></span>
                    <a href="/institutes" class="text-emerald-600">Clear</a>
                </div>
                <div class="ml-auto">
                    <input type="search" id="institute-search" placeholder="Search Here..." autocomplete="off">
                </div>
                <div>
                    <select id="institute-status-filter" class="form-control" aria-label="Institute approval status">
                        <option value="">All institutes</option>
                        <option value="pending">Pending approval</option>
                        <option value="approved">Approved</option>
                    </select>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:8px">Sl.</th>
                            <th>Membership No.</th>
                            <th>Institute Name</th>
                            <th>Representative Name</th>
                            <th>Phone No.</th>
                            <th>Center Name</th>
                            <th>Status</th>
                            <th style="width:200px;text-align:center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="institute-rows">
                        <tr><td colspan="8" class="text-center py-8 text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="institute-pagination-footer" class="mt-4">
                <div id="institute-pagination" class="flex flex-wrap items-center gap-2"></div>
                <label id="institute-per-page-control" for="institute-per-page" class="flex items-center gap-2 text-sm text-slate-500">
                    Rows per page
                    <select id="institute-per-page" class="input" style="width:auto;min-width:5rem">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('institutes-page');
    if (!root) { return; }

    const rowsEl = document.getElementById('institute-rows');
    const countEl = document.getElementById('institute-count');
    const pagerEl = document.getElementById('institute-pagination');
    const searchEl = document.getElementById('institute-search');
    const statusEl = document.getElementById('institute-status-filter');
    const perPageEl = document.getElementById('institute-per-page');
    const filterEl = document.getElementById('institute-center-filter');
    const filterTextEl = document.getElementById('institute-center-filter-text');
    const canEdit = root.getAttribute('data-perm-edit') === '1';
    const canDelete = root.getAttribute('data-perm-delete') === '1';
    const canValidity = root.getAttribute('data-perm-validity') === '1';

    // Optional ?center_id=N filter (linked from the /centers delete guard).
    const centerId = new URLSearchParams(window.location.search).get('center_id') || '';
    const perPage = function () { return Number(perPageEl.value) || 20; };

    function actionCell(row) {
        const id = row.id;
        const hasCenter = Number(row.linked_center_id) > 0;
        let html = '';
        if (canEdit && Number(row.active) !== 1) {
            if (hasCenter) {
                html += '<button type="button" class="btn btn-sm btn-primary" data-approve="' + BK.esc(String(id)) + '" title="Approve institute" aria-label="Approve institute">&#10003;</button>';
            } else {
                html += '<button type="button" class="btn btn-sm btn-light" data-center-required title="Assign a center before approval" aria-label="Assign a center before approval"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><path d="M12 7v6M9 10h6"/></svg></button>';
            }
        }
        if (canEdit && Number(row.active) === 1 && !String(row.certificate_no || '').trim()) {
            if (hasCenter) {
                html += '<button type="button" class="btn btn-sm btn-light" data-assign-certificate="' + BK.esc(String(id)) + '" title="Assign next certificate number" aria-label="Assign certificate number">#</button>';
            } else {
                html += '<button type="button" class="btn btn-sm btn-light" data-center-required title="Assign a center before issuing a certificate" aria-label="Assign a center before issuing a certificate"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><path d="M12 7v6M9 10h6"/></svg></button>';
            }
        }
        if (canEdit) {
            html += '<a class="btn btn-sm btn-light" href="/edit-institute/' + encodeURIComponent(id) + '" title="Edit">✎</a>';
        }
        if (canValidity) {
            html += '<a class="btn btn-sm btn-primary" href="/validityinstitute/' + encodeURIComponent(id) + '" title="Validity">⟳</a>';
        }
        if (canDelete) {
            html += '<button type="button" class="btn btn-sm btn-danger" data-delete="' + BK.esc(String(id)) + '" title="Delete">&#10005;</button>';
        }
        return '<div class="row-actions flex flex-wrap items-center justify-center gap-1">' + html + '</div>';
    }

    function renderRow(no, row) {
        const approved = Number(row.active) === 1;
        const centerCell = Number(row.linked_center_id) > 0
            ? BK.esc(row.center_name || '—')
            : '<span class="badge badge-red">Unassigned</span>';
        return '<tr>' +
            '<td>' + no + '</td>' +
            '<td>' + (approved ? BK.esc(row.certificate_no || '—') : '—') + '</td>' +
            '<td>' + BK.esc(row.name) + '</td>' +
            '<td>' + BK.esc(row.director_name || '—') + '</td>' +
            '<td>' + BK.esc(row.phone || '—') + '</td>' +
            '<td>' + centerCell + '</td>' +
            '<td><span class="badge ' + (approved ? 'badge-green' : 'badge-amber') + '">' + (approved ? 'Approved' : 'Pending') + '</span></td>' +
            '<td class="text-center">' + actionCell(row) + '</td>' +
            '</tr>';
    }

    async function load(url) {
        try {
            const pager = await BK.get(url);
            const rows = pager.data || [];
            const start = (pager.from || 1) - 1;
            rowsEl.innerHTML = rows.length
                ? rows.map(function (row, i) { return renderRow(start + i + 1, row); }).join('')
                : '<tr><td colspan="8" class="text-center py-8 text-slate-400">No records found</td></tr>';
            countEl.textContent = pager.total;
            BK.renderPagination(pagerEl, pager);
            if (centerId && filterEl) {
                filterEl.style.display = 'flex';
                filterTextEl.textContent = (rows[0] && rows[0].center_name) ? rows[0].center_name : ('#' + centerId);
            }
        } catch (err) {
            rowsEl.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-red-500">Could not load the list</td></tr>';
            BK.toast(err.message || 'Could not load the list', 'error');
        }
    }

    pagerEl.addEventListener('click', function (event) {
        const link = event.target.closest('a');
        if (!link) { return; }
        event.preventDefault();
        load(link.getAttribute('href'));
    });

    rowsEl.addEventListener('click', async function (event) {
        const centerRequiredBtn = event.target.closest('[data-center-required]');
        if (centerRequiredBtn) {
            BK.toast('Assign a center first, then approve the institute.', 'error');
            return;
        }
        const approveBtn = event.target.closest('[data-approve]');
        if (approveBtn) {
            approveBtn.disabled = true;
            try {
                const result = await BK.post('/api/institutes/changestatus/' + encodeURIComponent(approveBtn.getAttribute('data-approve')) + '/1');
                BK.toast('Institute approved. Certificate number: ' + (result.certificate_no || 'assigned'), 'success');
                load(BK.qs('/api/institutes', { page: 1, per_page: perPage(), search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
            } catch (err) {
                approveBtn.disabled = false;
                BK.toast(err.message || 'Could not approve institute', 'error');
            }
            return;
        }
        const assignBtn = event.target.closest('[data-assign-certificate]');
        if (assignBtn) {
            assignBtn.disabled = true;
            try {
                const result = await BK.post('/api/institutes/' + encodeURIComponent(assignBtn.getAttribute('data-assign-certificate')) + '/assign-certificate-number');
                BK.toast('Certificate number assigned: ' + result.certificate_no, 'success');
                load(BK.qs('/api/institutes', { page: 1, per_page: perPage(), search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
            } catch (err) {
                assignBtn.disabled = false;
                BK.toast(err.message || 'Could not assign certificate number', 'error');
            }
            return;
        }
        const btn = event.target.closest('[data-delete]');
        if (!btn || !window.confirm('Delete this institute?')) { return; }
        try {
            await BK.del('/api/institutes/' + btn.getAttribute('data-delete'));
            BK.toast('Institute deleted', 'success');
            load(BK.qs('/api/institutes', { page: 1, per_page: perPage(), search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
        } catch (err) {
            BK.toast(err.message || 'Could not delete', 'error');
        }
    });

    let timer = null;
    searchEl.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            load(BK.qs('/api/institutes', { page: 1, per_page: perPage(), search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
        }, 200);
    });

    statusEl.addEventListener('change', function () {
        load(BK.qs('/api/institutes', { page: 1, per_page: perPage(), search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
    });

    perPageEl.addEventListener('change', function () {
        load(BK.qs('/api/institutes', { page: 1, per_page: perPage(), search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
    });

    statusEl.value = new URLSearchParams(window.location.search).get('status') || '';
    load(BK.qs('/api/institutes', { page: 1, per_page: perPage(), center_id: centerId, status: statusEl.value }));
});
</script>
