<?php
/**
 * All User Access page — list + create/edit/delete (behaviour in /assets/js/users.js).
 * Original Vue page: resources/js/components/user/index.vue (columns kept 1:1).
 */
?>
<div class="space-y-4" id="users-page" data-users-page="index">
    <style>
        #user-pagination-footer { display:flex; width:100%; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; }
        #user-per-page-control { margin-left:auto; }
    </style>
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All User's With Details Info (<span id="user-count">0</span>)</h1>
            <a href="/create-user" class="btn btn-primary btn-sm">Add New User</a>
        </div>
        <div class="card-body space-y-4">
            <div class="flex flex-wrap items-end gap-3">
                <label class="block w-full max-w-md">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Search users</span>
                    <input id="user-search" type="search" class="form-control w-full" placeholder="Name, email, or role…" autocomplete="off">
                </label>
                <button id="user-search-reset" type="button" class="btn btn-light">Reset</button>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:10px">Sl.</th>
                            <th>User Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="user-rows">
                        <tr><td colspan="5" class="text-center py-8 text-slate-400">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="user-pagination-footer" class="mt-4">
                <div id="user-pagination" class="flex flex-wrap items-center gap-2"></div>
                <label id="user-per-page-control" for="user-per-page" class="flex items-center gap-2 text-sm text-slate-500">
                    Rows per page
                    <select id="user-per-page" class="input" style="width:auto;min-width:5rem">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>
    </div>
</div>
