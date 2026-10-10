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
                    <div>
                        <label class="label" for="user-name">Name</label>
                        <input type="text" id="user-name" name="name" data-field="name" placeholder="Enter Name" autocomplete="off">
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>
                    <div>
                        <label class="label" for="user-email">Email</label>
                        <input type="email" id="user-email" name="email" data-field="email" placeholder="Enter Email" autocomplete="off">
                        <p class="field-error hidden" data-error-for="email"></p>
                    </div>
                    <div>
                        <label class="label" for="user-role">Select Role</label>
                        <select id="user-role" name="roles" data-field="roles">
                            <option value="">Select Role</option>
                        </select>
                        <p class="field-error hidden" data-error-for="roles"></p>
                    </div>
                    <div></div>
                    <div>
                        <label class="label" for="user-password">New Password</label>
                        <input type="password" id="user-password" name="password" data-field="password" placeholder="Leave blank to keep current" autocomplete="new-password">
                        <p class="field-error hidden" data-error-for="password"></p>
                    </div>
                    <div>
                        <label class="label" for="user-confirm">Confirm New Password</label>
                        <input type="password" id="user-confirm" name="cpassword" data-field="cpassword" placeholder="Confirm Password" autocomplete="new-password">
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
