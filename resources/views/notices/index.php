<?php
/**
 * Notice list page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/notices/index.vue —
 * columns: Sl, Center Name (center.user.name / "All"), Title, Description, Action.
 * The original loaded GET /api/notices ({noticeList}) into a DataTable; the
 * DataTables client-side pager/filter is reimplemented here (20 per page).
 * Action permissions: the original checked institute-edit/institute-delete
 * (copy-paste bug) — corrected to notice-edit/notice-delete.
 */
?>
<div class="space-y-4" id="notices-page"
     data-notices-page="index"
     data-perm-edit="<?= can('notice-edit') ? '1' : '0' ?>"
     data-perm-delete="<?= can('notice-delete') ? '1' : '0' ?>">
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">Notices (<span id="notice-count">0</span>)</h1>
            <?php if (can('notice-create')): ?>
                <a href="/create-notice" class="btn btn-primary btn-sm">Add New Notices</a>
            <?php endif; ?>
        </div>
        <div class="card-body space-y-4">
            <div class="filter-bar">
                <div class="ml-auto">
                    <input type="search" id="notice-search" placeholder="Search Here..." autocomplete="off">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:10px">Sl</th>
                            <th style="text-align:center">Center Name</th>
                            <th style="text-align:center">Title</th>
                            <th style="text-align:center">Description</th>
                            <th style="text-align:center;width:100px">Action</th>
                        </tr>
                    </thead>
                    <tbody id="notice-rows">
                        <tr><td colspan="5" class="text-center py-8 text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="notice-pagination" class="flex flex-wrap items-center gap-1 text-sm"></div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('notices-page');
    if (!root || root.getAttribute('data-notices-page') !== 'index') { return; }

    const rowsEl = document.getElementById('notice-rows');
    const countEl = document.getElementById('notice-count');
    const pagerEl = document.getElementById('notice-pagination');
    const searchEl = document.getElementById('notice-search');
    const canEdit = root.getAttribute('data-perm-edit') === '1';
    const canDelete = root.getAttribute('data-perm-delete') === '1';

    const PER_PAGE = 20;
    let allRows = [];
    let pageNum = 1;

    function centerName(notice) {
        if (String(notice.center_id) === '0') { return 'All'; }
        const center = notice.center;
        return center && center.user ? center.user.name : '';
    }

    function actionCell(id) {
        let html = '<div class="row-actions flex items-center gap-1 justify-center">';
        if (canEdit) {
            html += '<a class="btn btn-sm btn-light" href="/editnotice/' + encodeURIComponent(id) + '" title="Edit">✎</a>';
        }
        if (canDelete) {
            html += '<button type="button" class="btn btn-sm btn-danger" data-act="delete" data-id="' +
                encodeURIComponent(id) + '" title="Delete">⨯</button>';
        }
        return html + '</div>';
    }

    function renderRows() {
        const filtered = filterRows();
        countEl.textContent = filtered.length;
        const pages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
        pageNum = Math.min(Math.max(1, pageNum), pages);
        const start = (pageNum - 1) * PER_PAGE;
        const slice = filtered.slice(start, start + PER_PAGE);

        rowsEl.innerHTML = slice.length
            ? slice.map(function (notice, i) {
                return '<tr>' +
                    '<td>' + (start + i + 1) + '</td>' +
                    '<td style="text-align:center">' + BK.esc(centerName(notice)) + '</td>' +
                    '<td style="text-align:center">' + BK.esc(notice.title) + '</td>' +
                    '<td style="text-align:left">' + BK.esc(notice.description) + '</td>' +
                    '<td>' + actionCell(notice.id) + '</td>' +
                    '</tr>';
            }).join('')
            : '<tr><td colspan="5" class="text-center py-8 text-slate-400">No records found</td></tr>';

        renderPager(pages);
    }

    function filterRows() {
        const term = searchEl.value.trim().toLowerCase();
        if (!term) { return allRows; }
        return allRows.filter(function (n) {
            return String(n.title || '').toLowerCase().includes(term) ||
                String(n.description || '').toLowerCase().includes(term) ||
                centerName(n).toLowerCase().includes(term);
        });
    }

    function renderPager(pages) {
        if (pages <= 1) {
            pagerEl.innerHTML = '';
            return;
        }
        let html = '<nav class="flex flex-wrap items-center gap-1 text-sm">';
        for (let p = 1; p <= pages; p++) {
            html += '<button type="button" class="px-3 py-1.5 rounded border ' +
                (p === pageNum
                    ? 'bg-emerald-600 border-emerald-600 text-white'
                    : 'bg-white border-slate-300 hover:bg-slate-100') +
                '" data-page="' + p + '">' + p + '</button>';
        }
        html += '<span class="ml-3 text-slate-500">Page ' + pageNum + ' of ' + pages + '</span></nav>';
        pagerEl.innerHTML = html;
    }

    async function reload() {
        try {
            const json = await BK.get('/api/notices');
            allRows = json.noticeList || [];
            pageNum = 1;
            renderRows();
        } catch (err) {
            rowsEl.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-red-500">Could not load the list</td></tr>';
            BK.toast(err.message || 'Could not load the list', 'error');
        }
    }

    pagerEl.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-page]');
        if (!btn) { return; }
        pageNum = Number(btn.getAttribute('data-page'));
        renderRows();
    });

    let timer = null;
    searchEl.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            pageNum = 1;
            renderRows();
        }, 200);
    });

    rowsEl.addEventListener('click', async function (event) {
        const btn = event.target.closest('[data-act="delete"]');
        if (!btn) { return; }
        const confirmed = await BK.confirm("Are you sure? You won't be able to revert this!");
        if (!confirmed) { return; }
        try {
            await BK.del('/api/notices/' + encodeURIComponent(btn.getAttribute('data-id')));
            BK.toast('Notice has been deleted.', 'success');
            await reload();
        } catch (err) {
            BK.toast(err.message || 'Something wrong.', 'error');
        }
    });

    reload();
});
</script>
