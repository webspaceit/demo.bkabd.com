<?php
/**
 * Edit User page (behaviour in /assets/js/users.js).
 * Original Vue page: resources/js/components/user/edit.vue —
 * fields: name, email, roles (single role id), password, cpassword.
 * $userId is passed by UserController::pageEdit().
 */
?>
<div class="space-y-4" id="users-page" data-users-page="edit" data-user-id="<?= (int) ($userId ?? 0) ?>">
    <div class="card max-w-3xl">
        <div class="card-header">Edit User</div>
        <div class="card-body">
            <form id="user-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="fl">
                        <input type="text" id="user-name" name="name" class="input" data-field="name" placeholder="Enter Name" autocomplete="off">
                        <label for="user-name">Name</label>
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>
                    <div class="fl">
                        <input type="email" id="user-email" name="email" class="input" data-field="email" placeholder="Enter Email" autocomplete="off">
                        <label for="user-email">Email</label>
                        <p class="field-error hidden" data-error-for="email"></p>
                    </div>
                    <div class="fl">
                        <select id="user-role" name="roles" class="input" data-field="roles">
                            <option value="">Select Role</option>
                        </select>
                        <label for="user-role">Select Role</label>
                        <p class="field-error hidden" data-error-for="roles"></p>
                    </div>
                    <div></div>
                    <div class="fl">
                        <input type="password" id="user-password" name="password" class="input" data-field="password" placeholder="Leave blank to keep current" autocomplete="new-password">
                        <label for="user-password">New Password</label>
                        <p class="field-error hidden" data-error-for="password"></p>
                    </div>
                    <div class="fl">
                        <input type="password" id="user-confirm" name="cpassword" class="input" data-field="cpassword" placeholder="Confirm Password" autocomplete="new-password">
                        <label for="user-confirm">Confirm New Password</label>
                        <p class="field-error hidden" data-error-for="cpassword"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <a href="/user" class="btn btn-light">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
