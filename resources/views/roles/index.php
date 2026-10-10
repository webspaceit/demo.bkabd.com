<?php
/**
 * Manage User (roles) page — list + delete (behaviour in /assets/js/roles.js).
 * Original Vue page: resources/js/components/role/list.vue.
 * "Add New Role" only appears when /api/roleWisePermissionList returns
 * role-create for the signed-in user (same check the original made).
 */
?>
<div class="space-y-4" id="roles-page" data-roles-page="index">
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All Role's With Details Info (<span id="role-count">0</span>)</h1>
            <a href="/create-role" class="btn btn-primary btn-sm hidden" id="role-add">Add New Role</a>
        </div>
        <div class="card-body">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:10px">#</th>
                            <th>Role Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="role-rows">
                        <tr><td colspan="3" class="text-center py-8 text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
