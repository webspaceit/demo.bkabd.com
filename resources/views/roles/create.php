<?php
/**
 * Add Role page (behaviour in /assets/js/roles.js).
 * Original Vue page: resources/js/components/role/add.vue — role name +
 * permission checkbox grid (values = permission ids).
 */
?>
<div class="space-y-4" id="roles-page" data-roles-page="create">
    <div class="card">
        <div class="card-header">Add New Role</div>
        <div class="card-body">
            <form id="role-form" novalidate>
                <div class="fl max-w-md">
                    <input type="text" id="role-name" name="name" class="input" data-field="name" placeholder="Enter Role Name" autocomplete="off">
                    <label for="role-name">Role Name</label>
                    <p class="field-error hidden" data-error-for="name"></p>
                </div>

                <div class="mt-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                        <h2 class="text-sm font-semibold text-slate-700">Permissions</h2>
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" id="permission-all">
                            <span>Select All</span>
                        </label>
                    </div>
                    <div id="permission-groups" class="space-y-4">
                        <p class="text-sm text-slate-400">Loading permissions...</p>
                    </div>
                    <p class="field-error hidden" data-error-for="permission"></p>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <a href="/userrole" class="btn btn-light">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
