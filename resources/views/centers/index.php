<?php
/**
 * Centers list — rows + pager loaded by /assets/js/centers.js
 * (GET /api/centers paginator; name comes from the linked users row).
 * $perms = CenterController::permissionMap() (the can() helper is broken by
 * the Auth::permissions() core bug — see routes/centers.php).
 */
$perms = $perms ?? [];
$flag = fn(string $p): string => !empty($perms[$p]) ? '1' : '0';
?>
<div class="space-y-4">
    <div class="card" id="centers-list"
         data-perm-edit="<?= $flag('center-edit') ?>"
         data-perm-status="<?= $flag('center-status') ?>"
         data-perm-delete="<?= $flag('center-delete') ?>"
         data-perm-institute-list="<?= $flag('institute-list') ?>">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All Center's With Details Info (<span id="center-total">0</span>)</h1>
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
                            <th>Institute</th>
                            <th>Division</th>
                            <th>District</th>
                            <th>Upazila</th>
                            <th>Authority Person</th>
                            <th>Mobile</th>
                            <th>Email Address</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="center-rows">
                        <tr><td colspan="12" class="text-center py-8 text-slate-400">Loading…</td></tr>
                    </tbody>
                </table>
            </div>

            <div id="center-pagination"></div>
        </div>
    </div>
</div>
