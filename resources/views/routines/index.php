<?php
/**
 * Routine list page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/routines/index.vue —
 * columns: Sl., Subject, Date, Day, Time, Action (edit + delete).
 */
?>
<div class="space-y-4" id="routines-page"
     data-routines-page="index"
     data-perm-edit="<?= can('routine-edit') ? '1' : '0' ?>"
     data-perm-delete="<?= can('routine-delete') ? '1' : '0' ?>">
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All Routine's (<span id="routine-count">0</span>)</h1>
            <?php if (can('routine-create')): ?>
                <a href="/createRoutine" class="btn btn-primary btn-sm">Add New Routine</a>
            <?php endif; ?>
        </div>
        <div class="card-body space-y-4">
            <div class="filter-bar">
                <div class="ml-auto">
                    <input type="search" id="routine-search" placeholder="Search Here..." autocomplete="off">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:8px">Sl.</th>
                            <th style="text-align:center">Subject</th>
                            <th style="text-align:center">Date</th>
                            <th style="text-align:center">Day</th>
                            <th style="text-align:center">Time</th>
                            <th style="width:105px;text-align:center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="routine-rows">
                        <tr><td colspan="6" class="text-center py-8 text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="routine-pagination" class="flex flex-wrap items-center gap-1 text-sm"></div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('routines-page');
    if (!root || root.getAttribute('data-routines-page') !== 'index') { return; }

    const rowsEl = document.getElementById('routine-rows');
    const countEl = document.getElementById('routine-count');
    const pagerEl = document.getElementById('routine-pagination');
    const searchEl = document.getElementById('routine-search');
    const canEdit = root.getAttribute('data-perm-edit') === '1';
    const canDelete = root.getAttribute('data-perm-delete') === '1';

    function actionCell(id) {
        let html = '<div class="row-actions flex items-center gap-1 justify-center">';
        if (canEdit) {
            html += '<a class="btn btn-sm btn-light" href="/editRoutine/' + encodeURIComponent(id) + '" title="Edit">✎</a>';
        }
        if (canDelete) {
            html += '<button type="button" class="btn btn-sm btn-danger" data-act="delete" data-id="' +
                encodeURIComponent(id) + '" title="Delete">⨯</button>';
        }
        return html + '</div>';
    }

    function renderRow(no, routine) {
        return '<tr>' +
            '<td>' + no + '</td>' +
            '<td style="text-align:center">' + BK.esc(routine.subject) + '</td>' +
            '<td style="text-align:center">' + BK.esc(routine.date) + '</td>' +
            '<td style="text-align:center">' + BK.esc(routine.day) + '</td>' +
            '<td style="text-align:center">' + BK.esc(routine.time) + '</td>' +
            '<td>' + actionCell(routine.id) + '</td>' +
            '</tr>';
    }

    async function load(url) {
        try {
            const pager = await BK.get(url);
            const rows = pager.data || [];
            const start = (pager.from || 1) - 1;
            rowsEl.innerHTML = rows.length
                ? rows.map(function (row, i) { return renderRow(start + i + 1, row); }).join('')
                : '<tr><td colspan="6" class="text-center py-8 text-slate-400">No records found</td></tr>';
            countEl.textContent = pager.total;
            BK.renderPagination(pagerEl, pager);
        } catch (err) {
            rowsEl.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-red-500">Could not load the list</td></tr>';
            BK.toast(err.message || 'Could not load the list', 'error');
        }
    }

    pagerEl.addEventListener('click', function (event) {
        const link = event.target.closest('a');
        if (!link) { return; }
        event.preventDefault();
        load(link.getAttribute('href'));
    });

    let timer = null;
    searchEl.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            load(BK.qs('/api/routines', { page: 1, search: searchEl.value.trim() }));
        }, 300);
    });

    rowsEl.addEventListener('click', async function (event) {
        const btn = event.target.closest('[data-act="delete"]');
        if (!btn) { return; }
        const confirmed = await BK.confirm("Are you sure? You won't be able to revert this!");
        if (!confirmed) { return; }
        try {
            await BK.del('/api/routines/' + encodeURIComponent(btn.getAttribute('data-id')));
            BK.toast('Routine has been deleted.', 'success');
            await load(BK.qs('/api/routines', { page: 1, search: searchEl.value.trim() }));
        } catch (err) {
            BK.toast(err.message || 'Could not delete the routine', 'error');
        }
    });

    load(BK.qs('/api/routines', { page: 1 }));
});
</script>
