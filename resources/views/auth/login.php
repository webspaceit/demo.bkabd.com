<?php /** Login page. Posts JSON to /api/login via app.js. */ ?>
<div class="min-h-[70vh] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sm:p-8">
            <h1 class="text-xl font-bold text-slate-900">Sign in</h1>
            <p class="text-sm text-slate-500 mt-1">BKABD Scholarship Exam Management System</p>

            <div id="login-error" class="hidden mt-4 rounded border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-sm"></div>

            <form id="login-form" class="mt-6 space-y-4">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email</label>
                    <input id="email" name="email" type="email" required autocomplete="username"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="password">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <button type="submit" id="login-btn"
                        class="w-full rounded bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-medium py-2.5 text-sm transition">
                    Sign in
                </button>
            </form>
        </div>
        <p class="text-center text-xs text-slate-400 mt-4">Demo credentials are listed in README.md</p>
    </div>
</div>
<script>
(function () {
    const form = document.getElementById('login-form');
    const errBox = document.getElementById('login-error');
    const btn = document.getElementById('login-btn');
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        errBox.classList.add('hidden');
        btn.disabled = true;
        try {
            const res = await BK.post('/api/login', {
                email: form.email.value.trim(),
                password: form.password.value,
            });
            if (res && res.success) {
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
            btn.disabled = false;
        }
    });
    function showLoginError(msg) {
        errBox.textContent = msg;
        errBox.classList.remove('hidden');
    }
})();
</script>
