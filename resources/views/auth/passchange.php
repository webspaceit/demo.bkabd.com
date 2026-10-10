<?php
/**
 * Password Change page (behaviour in /assets/js/users.js).
 * Original Vue page: resources/js/components/passchange.vue —
 * fields: password (previous), npassword (new), cnpassword (confirm).
 */
?>
<div class="space-y-4" id="users-page" data-users-page="passchange">
    <div class="card max-w-3xl">
        <div class="card-header">Password Change</div>
        <div class="card-body">
            <form id="passchange-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="label" for="pc-password">Previous Password</label>
                        <input type="password" id="pc-password" name="password" data-field="password" placeholder="Previous Password" autocomplete="current-password">
                        <p class="field-error hidden" data-error-for="password"></p>
                    </div>
                    <div>
                        <label class="label" for="pc-npassword">New Password</label>
                        <input type="password" id="pc-npassword" name="npassword" data-field="npassword" placeholder="New Password" autocomplete="new-password">
                        <p class="field-error hidden" data-error-for="npassword"></p>
                    </div>
                    <div>
                        <label class="label" for="pc-cnpassword">Confirm New Password</label>
                        <input type="password" id="pc-cnpassword" name="cnpassword" data-field="cnpassword" placeholder="Confirm Password" autocomplete="new-password">
                        <p class="field-error hidden" data-error-for="cnpassword"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <a href="/" class="btn btn-light">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
