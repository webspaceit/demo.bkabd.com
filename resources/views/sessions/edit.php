<?php
/**
 * Edit Session page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/sessions/edit.vue —
 * form fills from GET /api/sessions/{id} -> {sessionById:{...}}.
 * $sessionId is passed by SessionController::pageEdit().
 */
?>
<div class="space-y-4" id="sessions-page" data-sessions-page="edit" data-session-id="<?= (int) ($sessionId ?? 0) ?>">
    <div class="card max-w-3xl">
        <div class="card-header">Edit Session</div>
        <div class="card-body">
            <form id="session-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="fl">
                        <input type="number" id="session-year" name="session" class="input" data-field="session"
                               min="2020" max="2050" placeholder="Session" autocomplete="off">
                        <label for="session-year">Session</label>
                        <p class="field-error hidden" data-error-for="session"></p>
                    </div>
                    <div class="fl">
                        <select id="session-result" name="result" class="input" data-field="result">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                        <label for="session-result">Result Published?</label>
                        <p class="field-error hidden" data-error-for="result"></p>
                    </div>
                    <div class="fl">
                        <input type="datetime-local" id="session-result-time" name="result_time" class="input" data-field="result_time" placeholder="Result Time">
                        <label for="session-result-time">Result Time</label>
                        <p class="field-error hidden" data-error-for="result_time"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <a href="/sessions" class="btn btn-light">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('sessions-page');
    if (!root || root.getAttribute('data-sessions-page') !== 'edit') { return; }

    const form = document.getElementById('session-form');
    const sessionId = root.getAttribute('data-session-id');

    function fieldValue(name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    function fill(name, value) {
        const el = form.querySelector('[data-field="' + name + '"]');
        if (el) { el.value = value === null || value === undefined ? '' : value; }
    }

    (async function () {
        try {
            const json = await BK.get('/api/sessions/' + encodeURIComponent(sessionId));
            const row = json.sessionById || {};
            fill('session', row.session);
            fill('result', String(row.result === 1 || row.result === '1' ? '1' : '0'));
            // Stored values are 'YYYY-MM-DD HH:MM[:SS]', datetime-local wants 'YYYY-MM-DDTHH:MM'.
            fill('result_time', row.result_time ? String(row.result_time).replace(' ', 'T').slice(0, 16) : '');
        } catch (err) {
            BK.toast(err.message || 'Could not load the session', 'error');
        }
    })();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.put('/api/sessions/' + encodeURIComponent(sessionId), {
                session: fieldValue('session'),
                result: fieldValue('result'),
                result_time: fieldValue('result_time')
            });
            BK.toast('Session edited successfully', 'success');
            window.location.href = '/sessions';
        });
    });
});
</script>
