<?php
/**
 * Add Session page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/sessions/create.vue —
 * fields: session (year), result (Yes/No), result_time (datetime-local).
 */
?>
<div class="space-y-4" id="sessions-page" data-sessions-page="create">
    <div class="card max-w-3xl">
        <div class="card-header">Add Session</div>
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
    if (!root || root.getAttribute('data-sessions-page') !== 'create') { return; }

    const form = document.getElementById('session-form');

    function fieldValue(name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.post('/api/sessions', {
                session: fieldValue('session'),
                result: fieldValue('result'),
                result_time: fieldValue('result_time')
            });
            BK.toast('Sessions added successfully', 'success');
            window.location.href = '/sessions';
        });
    });
});
</script>
