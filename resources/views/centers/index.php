<?php
/**
 * Centers list — rows + pager loaded by /assets/js/centers.js
 * (GET /api/centers paginator; name comes from the linked users row).
 * $perms = CenterController::permissionMap() (the can() helper is broken by
 * the Auth::permissions() core bug — see routes/centers.php).
 */
$perms = $perms ?? [];
$pendingOnly = !empty($pendingOnly);
$flag = fn(string $p): string => !empty($perms[$p]) ? '1' : '0';
?>
<div class="space-y-4">
    <style>
        #center-pagination-footer { display:flex; width:100%; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; }
        #center-per-page-control { margin-left:auto; white-space:nowrap; }
        #center-per-page-picker { position:relative; }
        #center-per-page-toggle { display:flex; width:5rem; min-width:5rem; align-items:center; justify-content:space-between; border:1px solid #047253; border-radius:.25rem; background:#fff; padding:.5rem .75rem; color:#334155; line-height:1.25rem; }
        #center-per-page-menu { position:absolute; right:0; bottom:calc(100% + 2px); z-index:50; display:flex; min-width:5rem; flex-direction:column; overflow:hidden; border:1px solid #94a3b8; background:#fff; box-shadow:0 4px 10px rgba(15,23,42,.18); }
        #center-per-page-menu.hidden { display:none; }
        .center-per-page-option { border:0; background:#fff; padding:.25rem 1rem; color:#475569; text-align:left; }
        .center-per-page-option:hover { background:#e2e8f0; }
        .center-per-page-option[aria-pressed="true"] { background:#2563eb; color:#fff; }
    </style>
    <div class="card" id="centers-list"
         data-perm-edit="<?= $flag('center-edit') ?>"
         data-perm-status="<?= $flag('center-status') ?>"
         data-perm-delete="<?= $flag('center-delete') ?>"
         data-perm-institute-list="<?= $flag('institute-list') ?>"
         data-status-filter="<?= $pendingOnly ? 'pending' : '' ?>">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800"><?= $pendingOnly ? 'Pending Centers' : 'List of All Center\'s With Details Info' ?> (<span id="center-total">0</span>)</h1>
            <?php if (!empty($perms['center-create'])): ?>
                <a href="/create-center" class="btn btn-primary btn-sm">Add New Center</a>
            <?php endif; ?>
        </div>
        <div class="card-body space-y-4">
            <div class="fl">
                <input type="search" id="center-search" class="max-w-md" placeholder="Search Here...">
                <label for="center-search" class="f-center-search">Search</label>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sl.</th>
                            <th>Code</th>
                            <th>Center Name</th>
                            <th>Division</th>
                            <th>District</th>
                            <th>Authority Person</th>
                            <th>Mobile</th>
                            <th>Email Address</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="center-rows">
                        <tr><td colspan="10" class="text-center py-8 text-slate-400">Loading…</td></tr>
                    </tbody>
                </table>
            </div>

            <div id="center-pagination-footer" class="mt-4">
                <div id="center-pagination" class="flex flex-wrap items-center gap-2"></div>
                <div id="center-per-page-control" class="flex items-center gap-2 text-sm text-slate-500">
                    Rows per page
                    <select id="center-per-page" class="sr-only" aria-hidden="true" tabindex="-1">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <div id="center-per-page-picker">
                        <button id="center-per-page-toggle" type="button" aria-label="Rows per page: 20" aria-haspopup="true" aria-expanded="false">
                            <span id="center-per-page-value">20</span>
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div id="center-per-page-menu" class="hidden" role="group" aria-label="Rows per page options">
                            <button type="button" class="center-per-page-option" data-per-page-option="10" aria-pressed="false">10</button>
                            <button type="button" class="center-per-page-option" data-per-page-option="20" aria-pressed="true">20</button>
                            <button type="button" class="center-per-page-option" data-per-page-option="50" aria-pressed="false">50</button>
                            <button type="button" class="center-per-page-option" data-per-page-option="100" aria-pressed="false">100</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
