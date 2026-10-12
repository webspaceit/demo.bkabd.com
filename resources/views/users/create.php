<?php
/**
 * Add User page (behaviour in /assets/js/users.js).
 * Original Vue page: resources/js/components/user/create.vue —
 * fields: name, email, roles (single role id), password, confirmpassword.
 */
?>
<div class="space-y-4" id="users-page" data-users-page="create">
    <div class="card max-w-3xl">
        <div class="card-header">Add User</div>
        <div class="card-body">
            <form id="user-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="fl">
                        <input type="text" id="user-name" name="name" class="input" data-field="name" placeholder="Full Name" autocomplete="off">
                        <label for="user-name">Full Name</label>
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>
                    <div class="fl">
                        <input type="email" id="user-email" name="email" class="input" data-field="email" placeholder="Email Address" autocomplete="off">
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
                        <input type="password" id="user-password" name="password" class="input" data-field="password" placeholder="Password" autocomplete="new-password">
                        <label for="user-password">Password</label>
                        <p class="field-error hidden" data-error-for="password"></p>
                    </div>
                    <div class="fl">
                        <input type="password" id="user-confirm" name="confirmpassword" class="input" data-field="confirmpassword" placeholder="Confirm Password" autocomplete="new-password">
                        <label for="user-confirm">Confirm Password</label>
                        <p class="field-error hidden" data-error-for="confirmpassword"></p>
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
