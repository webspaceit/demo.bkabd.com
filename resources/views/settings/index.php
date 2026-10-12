<?php
/**
 * Settings page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/settings/index.vue —
 * active session switcher + instruction Title/Description form.
 * Data loads from GET /api/settings/1 -> {settingById:{...}, session}.
 */
$canEdit = can('setting-edit');
?>
<div class="space-y-4" id="settings-page" data-settings-page="index">
    <div class="card max-w-3xl">
        <div class="card-header">Active Session</div>
        <div class="card-body">
            <div class="max-w-xs fl">
                <select class="input" id="settings-session" data-field="session">
                    <option value="">Select Session</option>
                </select>
                <label for="settings-session">Session</label>
                <p class="field-error hidden" data-error-for="session"></p>
            </div>
        </div>
    </div>

    <?php if ($canEdit): ?>
        <div class="card max-w-3xl">
            <div class="card-header">Instructions</div>
            <div class="card-body">
                <form id="settings-form" novalidate>
                    <div class="space-y-4">
                        <div class="fl">
                            <input class="input" type="text" id="settings-title" name="title" data-field="title"
                                   placeholder="Title" autocomplete="off">
                            <label for="settings-title">Title</label>
                            <p class="field-error hidden" data-error-for="title"></p>
                        </div>
                        <div class="fl">
                            <textarea class="input" id="settings-description" name="description" data-field="description" rows="8"
                                      placeholder="Description"></textarea>
                            <label for="settings-description">Description</label>
                            <p class="field-error hidden" data-error-for="description"></p>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Update</button>
                        <a href="/instructions" class="btn btn-light">View Instructions</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card max-w-3xl">
            <div class="card-header">Result Sheet Signatures</div>
            <div class="card-body">
                <p class="mb-4 text-sm text-slate-500">
                    These appear at the bottom of the printed scholarship result sheet.
                    Upload a file to replace an image; leave a field empty to keep the current one.
                </p>
                <form id="signature-form" novalidate>
                    <?= csrf_field() ?>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <?php foreach ([
                            'chairman'            => 'Chairman',
                            'general_secretary'   => 'General Secretary',
                            'controller'          => 'Controller of Exam',
                            'education_secretary' => 'Education Secretary',
                        ] as $sigRole => $sigLabel): ?>
                            <div>
                                <label class="label" for="sig-<?= e($sigRole) ?>"><?= e($sigLabel) ?></label>
                                <div class="flex items-center gap-3">
                                    <img src="<?= e(result_signature($sigRole)) ?>" alt="<?= e($sigLabel) ?> signature"
                                         class="h-10 w-auto rounded bg-slate-50 p-0.5 ring-1 ring-slate-200">
                                    <input type="file" id="sig-<?= e($sigRole) ?>" name="<?= e($sigRole) ?>" data-field="<?= e($sigRole) ?>"
                                           accept="image/png,image/jpeg,image/gif" class="w-full text-xs">
                                </div>
                                <p class="field-error hidden" data-error-for="<?= e($sigRole) ?>"></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-5">
                        <button type="submit" class="btn btn-primary">Upload signatures</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('settings-page');
    if (!root || root.getAttribute('data-settings-page') !== 'index') { return; }

    const sessionEl = document.getElementById('settings-session');

    // Session switcher (original: updateSession($event.target.value) + toast).
    async function loadSessions() {
        try {
            const json = await BK.get('/api/sessionList');
            const active = String(json.activeSession || '');
            (json.sessionList || []).forEach(function (row) {
                const opt = document.createElement('option');
                opt.value = row.session;
                opt.textContent = row.session;
                opt.selected = String(row.session) === active;
                sessionEl.appendChild(opt);
            });
        } catch (err) {
            BK.toast(err.message || 'Could not load sessions', 'error');
        }
    }

    sessionEl.addEventListener('change', async function () {
        if (!sessionEl.value) { return; }
        try {
            await BK.get('/api/updateSession/' + encodeURIComponent(sessionEl.value));
            BK.toast('Session changed successfully', 'success');
            window.location.reload();
        } catch (err) {
            BK.toast(err.message || 'Could not switch session', 'error');
        }
    });

    loadSessions();

    const form = document.getElementById('settings-form');
    if (!form) { return; }

    function fieldValue(name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    (async function () {
        try {
            const json = await BK.get('/api/settings/1');
            const row = json.settingById || {};
            form.querySelector('[data-field="title"]').value = row.title || '';
            form.querySelector('[data-field="description"]').value = row.description || '';
        } catch (err) {
            BK.toast(err.message || 'Could not load settings', 'error');
        }
    })();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.put('/api/settings/1', {
                title: fieldValue('title'),
                description: fieldValue('description')
            });
            BK.toast('Setting edited successfully', 'success');
        });
    });

    const sigForm = document.getElementById('signature-form');
    if (sigForm) {
        sigForm.addEventListener('submit', function (event) {
            event.preventDefault();
            BK.submit(sigForm, async function () {
                await BK.upload('/api/settings/signatures', new FormData(sigForm));
                BK.toast('Signatures updated successfully', 'success');
                window.location.reload();
            });
        });
    }
});
</script>
