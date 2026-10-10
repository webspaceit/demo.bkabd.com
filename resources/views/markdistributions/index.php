<?php
/**
 * Mark distribution list page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/markdistributions/index.vue —
 * columns: #, Session, Total, Bangla, English, Math, Action; raw paginator
 * JSON from GET /api/markdistributions (search on session).
 */
?>
<div class="space-y-4" id="markdist-page" data-page="index"
     data-perm-create="<?= can('mark-distribution-create') ? '1' : '0' ?>"
     data-perm-edit="<?= can('mark-distribution-edit') ? '1' : '0' ?>">
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All Mark Distribution's With Details Info (<span id="markdist-count">0</span>)</h1>
            <?php if (can('mark-distribution-create')): ?>
                <a href="/createMarkDistribution" class="btn btn-primary btn-sm">Add New Mark Distribution</a>
            <?php endif; ?>
        </div>
        <div class="card-body space-y-4">
            <div class="filter-bar">
                <div class="ml-auto">
                    <input type="search" id="markdist-search" placeholder="Search Here..." autocomplete="off">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:10px">#</th>
                            <th>Session</th>
                            <th>Total</th>
                            <th>Bangla</th>
                            <th>English</th>
                            <th>Math</th>
                            <th style="width:110px;text-align:center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="markdist-rows">
                        <tr><td colspan="7" class="text-center py-8 text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="markdist-pagination" class="flex flex-wrap items-center gap-1 text-sm"></div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('markdist-page');
    if (!root || root.getAttribute('data-page') !== 'index') { return; }

    const rowsEl = document.getElementById('markdist-rows');
    const countEl = document.getElementById('markdist-count');
    const pagerEl = document.getElementById('markdist-pagination');
    const searchEl = document.getElementById('markdist-search');
    const canEdit = root.getAttribute('data-perm-edit') === '1';

    function actionCell(id) {
        if (!canEdit) { return ''; }
        return '<div class="row-actions flex items-center justify-center gap-1">' +
            '<a class="btn btn-sm btn-light" href="/editMarkDistribution/' + encodeURIComponent(id) + '" title="Edit">&#9998;</a>' +
            '<button type="button" class="btn btn-sm btn-danger" data-delete="' + encodeURIComponent(id) + '" title="Delete">&times;</button></div>';
    }

    function renderRow(no, row) {
        return '<tr>' +
            '<td>' + no + '</td>' +
            '<td>' + BK.esc(row.session) + '</td>' +
            '<td>' + BK.esc(row.total) + '</td>' +
            '<td>' + BK.esc(row.bangla) + '</td>' +
            '<td>' + BK.esc(row.english) + '</td>' +
            '<td>' + BK.esc(row.math) + '</td>' +
            '<td class="text-center">' + actionCell(row.id) + '</td>' +
            '</tr>';
    }

    async function load(url) {
        try {
            const pager = await BK.get(url);
            const rows = pager.data || [];
            const start = (pager.from || 1) - 1;
            rowsEl.innerHTML = rows.length
                ? rows.map(function (row, i) { return renderRow(start + i + 1, row); }).join('')
                : '<tr><td colspan="7" class="text-center py-8 text-slate-400">No records found</td></tr>';
            countEl.textContent = pager.total;
            BK.renderPagination(pagerEl, pager);
        } catch (err) {
            rowsEl.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-red-500">Could not load the list</td></tr>';
            BK.toast(err.message || 'Could not load the list', 'error');
        }
    }

    function currentUrl(page) {
        return BK.qs('/api/markdistributions', { page: page || 1, search: searchEl.value.trim() });
    }

    pagerEl.addEventListener('click', function (event) {
        const link = event.target.closest('a');
        if (!link) { return; }
        event.preventDefault();
        load(link.getAttribute('href'));
    });

    rowsEl.addEventListener('click', async function (event) {
        const del = event.target.closest('[data-delete]');
        if (!del) { return; }
        if (!(await BK.confirm('Are you sure? You won\'t be able to revert this!'))) { return; }
        try {
            await BK.del('/api/markdistributions/' + decodeURIComponent(del.getAttribute('data-delete')));
            BK.toast('Mark distribution has been deleted.', 'success');
            load(currentUrl(1));
        } catch (err) {
            BK.toast(err.message || 'Could not delete', 'error');
        }
    });

    let timer = null;
    searchEl.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { load(currentUrl(1)); }, 300);
    });

    load(currentUrl(1));
});
</script>
