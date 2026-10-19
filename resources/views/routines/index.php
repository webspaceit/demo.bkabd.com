<?php
/**
 * Routine list page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/routines/index.vue —
 * columns: Sl., Session, Class, Subject, Date, Day, Time, Action (duplicate, edit + delete).
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
                <?php if (can('routine-delete')): ?>
                    <div class="flex items-center gap-2">
                        <button type="button" id="routine-bulk-delete" class="btn btn-danger btn-sm" disabled>Delete Selected</button>
                        <span id="routine-selection-count" class="text-sm text-slate-500">0 selected on this page</span>
                    </div>
                <?php endif; ?>
                <div class="ml-auto">
                    <input type="search" id="routine-search" placeholder="Search Here..." autocomplete="off">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <?php if (can('routine-delete')): ?>
                                <th style="width:32px;text-align:center"><input type="checkbox" id="routine-select-all" aria-label="Select all routines on this page"></th>
                            <?php endif; ?>
                            <th style="width:8px">Sl.</th>
                            <th style="text-align:center">Session</th>
                            <th style="text-align:center">Class</th>
                            <th style="text-align:center">Subject</th>
                            <th style="text-align:center">Date</th>
                            <th style="text-align:center">Day</th>
                            <th style="text-align:center">Time</th>
                            <th style="width:105px;text-align:center">Status</th>
                            <th style="width:135px;text-align:center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="routine-rows">
                        <tr><td colspan="<?= can('routine-delete') ? 10 : 9 ?>" class="text-center py-8 text-slate-400">Loading...</td></tr>
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
    const canCreate = <?= can('routine-create') ? 'true' : 'false' ?>;
    const routinesById = new Map();
    const selectedIds = new Set();
    const selectAllEl = document.getElementById('routine-select-all');
    const bulkDeleteEl = document.getElementById('routine-bulk-delete');
    const selectionCountEl = document.getElementById('routine-selection-count');
    const emptyColspan = canDelete ? 10 : 9;

    function syncSelection() {
        const count = selectedIds.size;
        if (bulkDeleteEl) { bulkDeleteEl.disabled = count === 0; }
        if (selectionCountEl) { selectionCountEl.textContent = count + ' selected on this page'; }
        if (!selectAllEl) { return; }
        const checkboxes = Array.from(rowsEl.querySelectorAll('[data-act="select"]'));
        const checkedCount = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
        selectAllEl.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
        selectAllEl.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
    }

    function actionCell(id) {
        let html = '<div class="row-actions flex items-center gap-1 justify-center">';
        if (canCreate) {
            html += '<button type="button" class="btn btn-sm btn-light" data-act="duplicate" data-id="' +
                encodeURIComponent(id) + '" title="Duplicate">⧉</button>';
        }
        if (canEdit) {
            html += '<a class="btn btn-sm btn-light" href="/editRoutine/' + encodeURIComponent(id) + '" title="Edit">✎</a>';
        }
        if (canDelete) {
            html += '<button type="button" class="btn btn-sm btn-danger" data-act="delete" data-id="' +
                encodeURIComponent(id) + '" title="Delete">⨯</button>';
        }
        return html + '</div>';
    }

    function statusCell(routine) {
        const active = Number(routine.active) === 1;
        if (!canEdit) {
            return '<span class="badge ' + (active ? 'badge-green' : 'badge-gray') + '">' +
                (active ? 'Active' : 'Inactive') + '</span>';
        }
        return '<button type="button" class="btn btn-sm ' + (active ? 'btn-primary' : 'btn-light') +
            '" data-act="toggle" data-id="' + encodeURIComponent(routine.id) +
            '" data-active="' + (active ? '0' : '1') + '" title="Click to ' +
            (active ? 'deactivate' : 'activate') + '">' + (active ? 'Active' : 'Inactive') + '</button>';
    }

    function renderRow(no, routine) {
        routinesById.set(String(routine.id), routine);
        const classLabels = { 6: 'KG', 1: 'One', 2: 'Two', 3: 'Three', 4: 'Four', 5: 'Five' };
        const routineClass = routine.class === null || routine.class === '' || routine.class === undefined
            ? 'All Classes'
            : (classLabels[routine.class] || routine.class);
        return '<tr>' +
            (canDelete ? '<td style="text-align:center"><input type="checkbox" data-act="select" data-id="' +
                encodeURIComponent(routine.id) + '" aria-label="Select routine ' + BK.esc(routine.subject) + '"></td>' : '') +
            '<td>' + no + '</td>' +
            '<td style="text-align:center">' + BK.esc(routine.session || '—') + '</td>' +
            '<td style="text-align:center">' + BK.esc(routineClass) + '</td>' +
            '<td style="text-align:center">' + BK.esc(routine.subject) + '</td>' +
            '<td style="text-align:center">' + BK.esc(routine.date) + '</td>' +
            '<td style="text-align:center">' + BK.esc(routine.day) + '</td>' +
            '<td style="text-align:center">' + BK.esc(routine.time) + '</td>' +
            '<td style="text-align:center">' + statusCell(routine) + '</td>' +
            '<td>' + actionCell(routine.id) + '</td>' +
            '</tr>';
    }

    async function load(url) {
        try {
            const pager = await BK.get(url);
            const rows = pager.data || [];
            const start = (pager.from || 1) - 1;
            selectedIds.clear();
            routinesById.clear();
            rowsEl.innerHTML = rows.length
                ? rows.map(function (row, i) { return renderRow(start + i + 1, row); }).join('')
                : '<tr><td colspan="' + emptyColspan + '" class="text-center py-8 text-slate-400">No records found</td></tr>';
            countEl.textContent = pager.total;
            BK.renderPagination(pagerEl, pager);
            if (selectAllEl) { selectAllEl.checked = false; selectAllEl.indeterminate = false; }
            syncSelection();
        } catch (err) {
            rowsEl.innerHTML = '<tr><td colspan="' + emptyColspan + '" class="text-center py-8 text-red-500">Could not load the list</td></tr>';
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

    if (selectAllEl) {
        selectAllEl.addEventListener('change', function () {
            rowsEl.querySelectorAll('[data-act="select"]').forEach(function (checkbox) {
                checkbox.checked = selectAllEl.checked;
                if (selectAllEl.checked) { selectedIds.add(checkbox.getAttribute('data-id')); }
                else { selectedIds.delete(checkbox.getAttribute('data-id')); }
            });
            syncSelection();
        });
    }

    rowsEl.addEventListener('change', function (event) {
        const checkbox = event.target.closest('[data-act="select"]');
        if (!checkbox) { return; }
        if (checkbox.checked) { selectedIds.add(checkbox.getAttribute('data-id')); }
        else { selectedIds.delete(checkbox.getAttribute('data-id')); }
        syncSelection();
    });

    if (bulkDeleteEl) {
        bulkDeleteEl.addEventListener('click', async function () {
            const ids = Array.from(selectedIds);
            if (!ids.length) { return; }
            const confirmed = await BK.confirm('Delete ' + ids.length + ' selected routine' +
                (ids.length === 1 ? '' : 's') + '? You will not be able to undo this.');
            if (!confirmed) { return; }
            bulkDeleteEl.disabled = true;
            let deleted = 0;
            let failed = 0;
            for (const id of ids) {
                try {
                    await BK.del('/api/routines/' + encodeURIComponent(id));
                    deleted++;
                } catch (err) {
                    failed++;
                }
            }
            selectedIds.clear();
            if (failed === 0) {
                BK.toast(deleted + ' routine' + (deleted === 1 ? '' : 's') + ' deleted.', 'success');
            } else {
                BK.toast(deleted + ' deleted; ' + failed + ' could not be deleted.', 'error');
            }
            await load(BK.qs('/api/routines', { page: 1, search: searchEl.value.trim() }));
        });
    }

    rowsEl.addEventListener('click', async function (event) {
        const duplicateBtn = event.target.closest('[data-act="duplicate"]');
        if (duplicateBtn) {
            const routine = routinesById.get(String(duplicateBtn.getAttribute('data-id')));
            if (!routine) {
                BK.toast('Could not find this routine to duplicate.', 'error');
                return;
            }
            try {
                sessionStorage.setItem('routine-duplicate', JSON.stringify(routine));
                window.location.href = '/createRoutine?duplicate=1';
            } catch (err) {
                BK.toast('Could not prepare the routine copy.', 'error');
            }
            return;
        }
        const toggleBtn = event.target.closest('[data-act="toggle"]');
        if (toggleBtn) {
            const active = Number(toggleBtn.getAttribute('data-active'));
            toggleBtn.disabled = true;
            try {
                await BK.put('/api/routines/' + encodeURIComponent(toggleBtn.getAttribute('data-id')) + '/active', { active: active });
                BK.toast(active ? 'Routine activated.' : 'Routine deactivated.', 'success');
                await load(BK.qs('/api/routines', { page: 1, search: searchEl.value.trim() }));
            } catch (err) {
                toggleBtn.disabled = false;
                BK.toast(err.message || 'Could not update routine status', 'error');
            }
            return;
        }
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
