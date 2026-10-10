<?php
/**
 * Session list page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/sessions/index.vue —
 * columns: Sl., Session, Result (Yes/No), Result Time, Action (edit only).
 */
?>
<div class="space-y-4" id="sessions-page" data-sessions-page="index" data-perm-edit="<?= can('session-edit') ? '1' : '0' ?>">
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All Session's (<span id="session-count">0</span>)</h1>
            <?php if (can('session-create')): ?>
                <a href="/createSession" class="btn btn-primary btn-sm">Add New Session</a>
            <?php endif; ?>
        </div>
        <div class="card-body space-y-4">
            <div class="filter-bar">
                <div class="ml-auto">
                    <input type="search" id="session-search" placeholder="Search Here..." autocomplete="off">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:8px">Sl.</th>
                            <th style="text-align:center">Session</th>
                            <th style="text-align:center">Result</th>
                            <th style="text-align:center">Result Time</th>
                            <th style="width:105px;text-align:center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="session-rows">
                        <tr><td colspan="5" class="text-center py-8 text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="session-pagination" class="flex flex-wrap items-center gap-1 text-sm"></div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('sessions-page');
    if (!root || root.getAttribute('data-sessions-page') !== 'index') { return; }

    const rowsEl = document.getElementById('session-rows');
    const countEl = document.getElementById('session-count');
    const pagerEl = document.getElementById('session-pagination');
    const searchEl = document.getElementById('session-search');
    const canEdit = root.getAttribute('data-perm-edit') === '1';

    function yesno(val) {
        return String(val) === '1' ? 'Yes' : 'No';
    }

    /** Port of the Vue datetime() formatter: DD-MM-YYYY hh:mm AM/PM. */
    function fmtDateTime(input) {
        if (!input) { return ''; }
        const date = new Date(String(input).replace(' ', 'T'));
        if (isNaN(date.getTime())) { return String(input); }
        const pad = (n) => String(n).padStart(2, '0');
        let hours = date.getHours();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return pad(date.getDate()) + '-' + pad(date.getMonth() + 1) + '-' + date.getFullYear() +
            ' ' + pad(hours) + ':' + pad(date.getMinutes()) + ' ' + ampm;
    }

    function actionCell(id) {
        if (!canEdit) { return ''; }
        return '<div class="row-actions flex items-center justify-center gap-1"><a class="btn btn-sm btn-light" href="/editSession/' + encodeURIComponent(id) + '" title="Edit">✎</a></div>';
    }

    function renderRow(no, session) {
        return '<tr>' +
            '<td>' + no + '</td>' +
            '<td>' + BK.esc(session.session) + '</td>' +
            '<td>' + yesno(session.result) + '</td>' +
            '<td>' + BK.esc(fmtDateTime(session.result_time)) + '</td>' +
            '<td>' + actionCell(session.id) + '</td>' +
            '</tr>';
    }

    async function load(url) {
        try {
            const pager = await BK.get(url);
            const rows = pager.data || [];
            const start = (pager.from || 1) - 1;
            rowsEl.innerHTML = rows.length
                ? rows.map(function (row, i) { return renderRow(start + i + 1, row); }).join('')
                : '<tr><td colspan="5" class="text-center py-8 text-slate-400">No records found</td></tr>';
            countEl.textContent = pager.total;
            BK.renderPagination(pagerEl, pager);
        } catch (err) {
            rowsEl.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-red-500">Could not load the list</td></tr>';
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
            load(BK.qs('/api/sessions', { page: 1, search: searchEl.value.trim() }));
        }, 300);
    });

    load(BK.qs('/api/sessions', { page: 1 }));
});
</script>
