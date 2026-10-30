<?php
/**
 * Institute list page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/institutes/index.vue.
 */
?>
<div class="space-y-4" id="institutes-page" data-perm-edit="<?= can('institute-edit') ? '1' : '0' ?>" data-perm-delete="<?= can('institute-delete') ? '1' : '0' ?>" data-perm-validity="<?= can('institute-validity') ? '1' : '0' ?>">
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
                            <th>Institute</th>
                            <th>Center</th>
                            <th>Director</th>
                            <th>Phone</th>
                            <th>Certificate No</th>
                            <th>Status</th>
                            <th>Validity</th>
                            <th style="width:200px;text-align:center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="institute-rows">
                        <tr><td colspan="9" class="text-center py-8 text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="institute-pagination" class="flex flex-wrap items-center gap-1 text-sm"></div>
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
    const filterEl = document.getElementById('institute-center-filter');
    const filterTextEl = document.getElementById('institute-center-filter-text');
    const canEdit = root.getAttribute('data-perm-edit') === '1';
    const canDelete = root.getAttribute('data-perm-delete') === '1';
    const canValidity = root.getAttribute('data-perm-validity') === '1';

    // Optional ?center_id=N filter (linked from the /centers delete guard).
    const centerId = new URLSearchParams(window.location.search).get('center_id') || '';

    function actionCell(row) {
        const id = row.id;
        let html = '';
        if (canEdit && Number(row.active) !== 1) {
            html += '<button type="button" class="btn btn-sm btn-primary" data-approve="' + BK.esc(String(id)) + '" title="Approve institute" aria-label="Approve institute">&#10003;</button>';
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
        const validity = row.validity_date ? BK.esc(row.validity_date) : '—';
        const approved = Number(row.active) === 1;
        return '<tr>' +
            '<td>' + no + '</td>' +
            '<td>' + BK.esc(row.name) + '</td>' +
            '<td>' + BK.esc(row.center_name || '—') + '</td>' +
            '<td>' + BK.esc(row.director_name || '—') + '</td>' +
            '<td>' + BK.esc(row.phone || '—') + '</td>' +
            '<td>' + (approved ? BK.esc(row.certificate_no || '—') : '—') + '</td>' +
            '<td><span class="badge ' + (approved ? 'badge-green' : 'badge-gray') + '">' + (approved ? 'Approved' : 'Pending') + '</span></td>' +
            '<td>' + validity + '</td>' +
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
                : '<tr><td colspan="9" class="text-center py-8 text-slate-400">No records found</td></tr>';
            countEl.textContent = pager.total;
            BK.renderPagination(pagerEl, pager);
            if (centerId && filterEl) {
                filterEl.style.display = 'flex';
                filterTextEl.textContent = (rows[0] && rows[0].center_name) ? rows[0].center_name : ('#' + centerId);
            }
        } catch (err) {
            rowsEl.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-red-500">Could not load the list</td></tr>';
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
        const approveBtn = event.target.closest('[data-approve]');
        if (approveBtn) {
            approveBtn.disabled = true;
            try {
                await BK.post('/api/institutes/changestatus/' + encodeURIComponent(approveBtn.getAttribute('data-approve')) + '/1');
                BK.toast('Institute approved. Its certificate number is now visible.', 'success');
                load(BK.qs('/api/institutes', { page: 1, search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
            } catch (err) {
                approveBtn.disabled = false;
                BK.toast(err.message || 'Could not approve institute', 'error');
            }
            return;
        }
        const btn = event.target.closest('[data-delete]');
        if (!btn || !window.confirm('Delete this institute?')) { return; }
        try {
            await BK.del('/api/institutes/' + btn.getAttribute('data-delete'));
            BK.toast('Institute deleted', 'success');
            load(BK.qs('/api/institutes', { page: 1, search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
        } catch (err) {
            BK.toast(err.message || 'Could not delete', 'error');
        }
    });

    let timer = null;
    searchEl.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            load(BK.qs('/api/institutes', { page: 1, search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
        }, 200);
    });

    statusEl.addEventListener('change', function () {
        load(BK.qs('/api/institutes', { page: 1, search: searchEl.value.trim(), center_id: centerId, status: statusEl.value }));
    });

    statusEl.value = new URLSearchParams(window.location.search).get('status') || '';
    load(BK.qs('/api/institutes', { page: 1, center_id: centerId, status: statusEl.value }));
});
</script>
