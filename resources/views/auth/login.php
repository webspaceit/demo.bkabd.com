<?php /** Login page. Posts JSON to /api/login (and /api/login/mfa for 2FA) via app.js. */ ?>
<div class="min-h-[70vh] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <h1 class="text-xl font-bold text-slate-900">Sign in</h1>
            <p class="text-sm text-slate-500 mt-1"><?= e(brand('app_name', 'BKABD Exam')) ?> Management System</p>

            <div id="login-error" class="hidden mt-4 rounded border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-sm"></div>

            <form id="login-form" class="mt-6 space-y-4" novalidate>
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">

                <!-- Step 1: email + password -->
                <div id="login-step1" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email</label>
                        <input id="email" name="email" type="email" placeholder="you@example.com" autocomplete="username"
                               class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="password">Password</label>
                        <input id="password" name="password" type="password" placeholder="Password" autocomplete="current-password"
                               class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <button type="submit" id="login-btn"
                            class="w-full rounded bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-medium py-2.5 text-sm transition">
                        Sign in
                    </button>
                </div>

                <!-- Step 2: two-factor code (shown only when the account has 2FA) -->
                <div id="login-step2" class="hidden space-y-4">
                    <p class="text-sm text-slate-600">
                        Enter the 6-digit code from your authenticator app to finish signing in.
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" for="mfa-code">Authentication code</label>
                        <input id="mfa-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                               placeholder="123456"
                               class="w-full rounded border border-slate-300 px-3 py-2 text-sm tracking-widest focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <button type="submit" id="mfa-btn"
                            class="w-full rounded bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-medium py-2.5 text-sm transition">
                        Verify &amp; sign in
                    </button>
                    <button type="button" id="mfa-back"
                            class="w-full rounded border border-slate-300 hover:bg-slate-50 text-slate-600 font-medium py-2 text-sm transition">
                        Back
                    </button>
                </div>
            </form>
        </div>
        <p class="text-center text-xs text-slate-400 mt-4">Demo credentials are listed in README.md</p>
    </div>
</div>
<script>
(function () {
    const form = document.getElementById('login-form');
    const errBox = document.getElementById('login-error');
    const loginBtn = document.getElementById('login-btn');
    const mfaBtn = document.getElementById('mfa-btn');
    const step1 = document.getElementById('login-step1');
    const step2 = document.getElementById('login-step2');
    const codeInput = document.getElementById('mfa-code');
    const backBtn = document.getElementById('mfa-back');

    // '' = step 1 (credentials); 'mfa' = step 2 (verification code).
    let mode = '';
    let mfaToken = '';

    function showLoginError(msg) {
        errBox.textContent = msg;
        errBox.classList.remove('hidden');
    }

    function enterMfa(token) {
        mode = 'mfa';
        mfaToken = token;
        step1.classList.add('hidden');
        step2.classList.remove('hidden');
        codeInput.value = '';
        codeInput.focus();
    }

    function backToCredentials() {
        mode = '';
        mfaToken = '';
        step2.classList.add('hidden');
        step1.classList.remove('hidden');
        errBox.classList.add('hidden');
        document.getElementById('email').focus();
    }

    async function submitCredentials() {
        const email = form.email.value.trim();
        const password = form.password.value;
        if (!email || !password) {
            showLoginError('Please enter your email and password.');
            return;
        }
        loginBtn.disabled = true;
        try {
            const res = await BK.post('/api/login', { email: email, password: password });
            if (res && res.success) {
                if (res.data && res.data.mfa_required) {
                    enterMfa(res.data.mfa_token);
                    return;
                }
                window.location.href = '/';
                return;
            }
            showLoginError('Invalid response from server.');
        } catch (err) {
            if (err.status === 403) {
                showLoginError('Your center account is not active.');
            } else if (err.status === 401) {
                showLoginError('Invalid email or password.');
            } else {
                showLoginError(err.message || 'Login failed. Please try again.');
            }
        } finally {
            loginBtn.disabled = false;
        }
    }

    async function submitMfa() {
        const code = (codeInput.value || '').replace(/\D/g, '');
        if (code.length !== 6) {
            showLoginError('Enter the 6-digit code from your authenticator app.');
            return;
        }
        mfaBtn.disabled = true;
        try {
            const res = await BK.post('/api/login/mfa', { mfa_token: mfaToken, code: code });
            if (res && res.success) {
                window.location.href = '/';
                return;
            }
            showLoginError('Invalid response from server.');
        } catch (err) {
            if (err.status === 422) {
                showLoginError(err.message || 'The verification code is incorrect. Please try again.');
            } else if (err.status === 429) {
                showLoginError('Too many attempts. Please wait a few minutes and try again.');
            } else {
                showLoginError(err.message || 'Verification failed. Please sign in again.');
            }
        } finally {
            mfaBtn.disabled = false;
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.classList.add('hidden');
        if (mode === 'mfa') {
            submitMfa();
        } else {
            submitCredentials();
        }
    });

    backBtn.addEventListener('click', backToCredentials);
})();
</script>
