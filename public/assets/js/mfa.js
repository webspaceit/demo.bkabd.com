/**
 * Two-factor authentication (TOTP) management UI — lives on /passchange.
 *
 * Endpoints (see App\Controllers\AuthController):
 *   GET  /api/mfa/setup   -> {success,data:{secret,otpauth,enabled:false}}
 *   POST /api/mfa/enable  {code} -> turns 2FA on
 *   POST /api/mfa/disable {code} -> turns 2FA off
 *
 * The initial enabled/disabled state comes from the page (data-mfa-enabled),
 * so no request is made just to render. Loaded via pageScripts AFTER app.js.
 */
(function () {
    const root = document.getElementById('two-factor');
    if (!root || typeof BK === 'undefined') { return; }

    const statusEl = root.querySelector('[data-mfa-status]');
    const offBox = root.querySelector('[data-mfa-off]');
    const onBox = root.querySelector('[data-mfa-on]');
    const setupBox = root.querySelector('[data-mfa-setup]');
    const qrBox = root.querySelector('[data-mfa-qr]');
    const secretEl = root.querySelector('[data-mfa-secret]');
    const startBtn = root.querySelector('[data-mfa-start]');
    const copyBtn = root.querySelector('[data-mfa-copy]');
    const enableBtn = root.querySelector('[data-mfa-enable]');
    const enableCode = root.querySelector('[data-mfa-enable-code]');
    const disableBtn = root.querySelector('[data-mfa-disable]');
    const disableCode = root.querySelector('[data-mfa-disable-code]');
    const errEl = root.querySelector('[data-mfa-error]');

    function showError(msg) {
        if (!errEl) { return; }
        errEl.textContent = msg || '';
        errEl.classList.toggle('hidden', !msg);
    }

    function digits(input) {
        return (input && input.value ? input.value : '').replace(/\D/g, '');
    }

    function render(enabled) {
        root.setAttribute('data-mfa-enabled', enabled ? '1' : '0');
        if (statusEl) {
            statusEl.innerHTML = enabled
                ? 'Two-factor authentication is <strong class="text-emerald-700">enabled</strong>.'
                : 'Two-factor authentication is <strong class="text-slate-700">disabled</strong>.';
        }
        if (offBox) { offBox.classList.toggle('hidden', enabled); }
        if (onBox) { onBox.classList.toggle('hidden', !enabled); }
        if (enabled && setupBox) { setupBox.classList.add('hidden'); }
        showError('');
    }

    // Initial state from the server-rendered attribute.
    render(root.getAttribute('data-mfa-enabled') === '1');

    if (startBtn) {
        startBtn.addEventListener('click', async function () {
            showError('');
            startBtn.disabled = true;
            try {
                const res = await BK.get('/api/mfa/setup');
                const secret = res && res.data ? res.data.secret : '';
                const otpauth = res && res.data ? res.data.otpauth : '';
                if (!secret) {
                    showError('Could not generate a two-factor secret.');
                    return;
                }
                if (secretEl) { secretEl.textContent = secret; }
                if (qrBox) {
                    qrBox.innerHTML = '';
                    if (typeof QRCode !== 'undefined' && otpauth) {
                        new QRCode(qrBox, { text: otpauth, width: 176, height: 176, correctLevel: QRCode.CorrectLevel.M });
                    } else {
                        qrBox.innerHTML = '<p class="text-xs text-slate-500">Scan unavailable — enter the key below manually.</p>';
                    }
                }
                if (setupBox) { setupBox.classList.remove('hidden'); }
                if (enableCode) { enableCode.value = ''; enableCode.focus(); }
            } catch (err) {
                showError(err.message || 'Could not start two-factor setup.');
            } finally {
                startBtn.disabled = false;
            }
        });
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            const text = secretEl ? secretEl.textContent : '';
            if (!text) { return; }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(
                    () => BK.toast('Secret key copied.', 'success'),
                    () => BK.toast('Copy failed — select the key manually.', 'error')
                );
            } else {
                BK.toast('Copy not supported — select the key manually.', 'info');
            }
        });
    }

    if (enableBtn) {
        enableBtn.addEventListener('click', async function () {
            const code = digits(enableCode);
            if (code.length !== 6) {
                showError('Enter the 6-digit code from your authenticator app.');
                return;
            }
            showError('');
            enableBtn.disabled = true;
            try {
                await BK.post('/api/mfa/enable', { code: code });
                render(true);
                BK.toast('Two-factor authentication enabled.', 'success');
            } catch (err) {
                showError(err.message || 'The verification code is incorrect.');
            } finally {
                enableBtn.disabled = false;
            }
        });
    }

    if (disableBtn) {
        disableBtn.addEventListener('click', async function () {
            const code = digits(disableCode);
            if (code.length !== 6) {
                showError('Enter a current 6-digit code to disable two-factor authentication.');
                return;
            }
            showError('');
            disableBtn.disabled = true;
            try {
                await BK.post('/api/mfa/disable', { code: code });
                render(false);
                BK.toast('Two-factor authentication disabled.', 'success');
            } catch (err) {
                showError(err.message || 'The verification code is incorrect.');
            } finally {
                disableBtn.disabled = false;
            }
        });
    }
})();
