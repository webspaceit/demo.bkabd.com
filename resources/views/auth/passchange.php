<?php
/**
 * Password Change page (behaviour in /assets/js/users.js).
 * Original Vue page: resources/js/components/passchange.vue —
 * fields: password (previous), npassword (new), cnpassword (confirm).
 */
?>
<div class="space-y-4" id="users-page" data-users-page="passchange">
    <div class="card max-w-3xl">
        <div class="card-header">Profile Information</div>
        <div class="card-body">
            <form id="account-profile-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="fl">
                        <input class="input" type="text" id="account-name" name="name" data-field="name" value="<?= e($accountName ?? '') ?>" autocomplete="name" required>
                        <label for="account-name">Display Name</label>
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="email" id="account-email" name="email" data-field="email" value="<?= e($accountEmail ?? '') ?>" autocomplete="email" required>
                        <label for="account-email">Email Address</label>
                        <p class="field-error hidden" data-error-for="email"></p>
                    </div>
                    <div class="fl md:col-span-2">
                        <input class="input" type="password" id="account-current-password" name="current_password" data-field="current_password" autocomplete="current-password" required>
                        <label for="account-current-password">Current Password (confirm changes)</label>
                        <p class="field-error hidden" data-error-for="current_password"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card max-w-3xl">
        <div class="card-header">Password Change</div>
        <div class="card-body">
            <form id="passchange-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="fl">
                        <input class="input" type="password" id="pc-password" name="password" data-field="password" placeholder="Previous Password" autocomplete="current-password">
                        <label for="pc-password">Previous Password</label>
                        <p class="field-error hidden" data-error-for="password"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="password" id="pc-npassword" name="npassword" data-field="npassword" placeholder="New Password" autocomplete="new-password">
                        <label for="pc-npassword">New Password</label>
                        <p class="field-error hidden" data-error-for="npassword"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="password" id="pc-cnpassword" name="cnpassword" data-field="cnpassword" placeholder="Confirm Password" autocomplete="new-password">
                        <label for="pc-cnpassword">Confirm New Password</label>
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

    <!-- Two-factor authentication (TOTP) — driven by /assets/js/mfa.js -->
    <div class="card max-w-3xl mt-4" id="two-factor" data-mfa-enabled="<?= !empty($twoFactorEnabled) ? '1' : '0' ?>">
        <div class="card-header">Two-Factor Authentication (2FA)</div>
        <div class="card-body">
            <p class="text-sm text-slate-600" data-mfa-status>Loading&hellip;</p>

            <!-- Disabled: offer setup -->
            <div data-mfa-off class="hidden mt-4">
                <p class="text-sm text-slate-500 mb-3">
                    Add a second step to sign-in using an authenticator app
                    (Google Authenticator, Microsoft Authenticator, Authy, &hellip;).
                </p>
                <button type="button" class="btn btn-primary" data-mfa-start>Enable 2FA</button>

                <div data-mfa-setup class="hidden mt-5 border-t border-slate-200 pt-4">
                    <p class="text-sm text-slate-600 mb-2">1. Scan this QR code (or enter the key manually):</p>
                    <div class="mb-3">
                        <div data-mfa-qr class="inline-block bg-white p-2 border border-slate-200 rounded"></div>
                    </div>
                    <p class="text-sm mb-4">
                        Secret key:
                        <code class="px-2 py-1 bg-slate-100 rounded text-slate-800" data-mfa-secret></code>
                        <button type="button" class="text-xs text-emerald-700 underline ml-2" data-mfa-copy>Copy</button>
                    </p>
                    <p class="text-sm text-slate-600 mb-1">2. Enter the 6-digit code shown in the app:</p>
                    <div class="flex flex-wrap gap-2 items-start">
                        <input id="mfa-enable-code" data-mfa-enable-code inputmode="numeric" autocomplete="one-time-code"
                               maxlength="6" placeholder="123456"
                               class="w-40 rounded border border-slate-300 px-3 py-2 text-sm tracking-widest focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <button type="button" class="btn btn-primary" data-mfa-enable>Verify &amp; Enable</button>
                    </div>
                    <p class="field-error hidden mt-2" data-mfa-error></p>
                </div>
            </div>

            <!-- Enabled: offer disable -->
            <div data-mfa-on class="hidden mt-4">
                <p class="text-sm text-emerald-700 mb-3">Two-factor authentication is currently enabled.</p>
                <p class="text-sm text-slate-600 mb-1">Enter a current 6-digit code to turn it off:</p>
                <div class="flex flex-wrap gap-2 items-start">
                    <input id="mfa-disable-code" data-mfa-disable-code inputmode="numeric" autocomplete="one-time-code"
                           maxlength="6" placeholder="123456"
                           class="w-40 rounded border border-slate-300 px-3 py-2 text-sm tracking-widest focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <button type="button" class="btn btn-light" data-mfa-disable>Disable 2FA</button>
                </div>
                <p class="field-error hidden mt-2" data-mfa-error></p>
            </div>
        </div>
    </div>
</div>
