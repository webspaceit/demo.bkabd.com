<?php
/**
 * All User Access page — list + create/edit/delete (behaviour in /assets/js/users.js).
 * Original Vue page: resources/js/components/user/index.vue (columns kept 1:1).
 */
?>
<div class="space-y-4" id="users-page" data-users-page="index">
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">List of All User's With Details Info (<span id="user-count">0</span>)</h1>
            <a href="/create-user" class="btn btn-primary btn-sm">Add New User</a>
        </div>
        <div class="card-body space-y-4">
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
            <div id="user-pagination"></div>
        </div>
    </div>
</div>
