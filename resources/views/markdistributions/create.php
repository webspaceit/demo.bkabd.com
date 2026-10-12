<?php
/**
 * Add mark distribution page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/markdistributions/create.vue —
 * POST /api/markdistributions -> {status:'success'}, then back to the list.
 */
?>
<div class="space-y-4" id="markdist-page" data-page="create">
    <div class="card max-w-3xl">
        <div class="card-header">Add Mark Distribution</div>
        <div class="card-body">
            <form id="markdist-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="fl">
                        <select class="input" id="md-session" name="session" data-field="session">
                            <option value="">Select Session</option>
                        </select>
                        <label for="md-session">Session</label>
                        <p class="field-error hidden" data-error-for="session"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="number" id="md-total" name="total" data-field="total" placeholder="Total Mark" autocomplete="off">
                        <label for="md-total">Total Mark</label>
                        <p class="field-error hidden" data-error-for="total"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="number" id="md-bangla" name="bangla" data-field="bangla" placeholder="Bangla Mark" autocomplete="off">
                        <label for="md-bangla">Bangla</label>
                        <p class="field-error hidden" data-error-for="bangla"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="number" id="md-english" name="english" data-field="english" placeholder="English Mark" autocomplete="off">
                        <label for="md-english">English</label>
                        <p class="field-error hidden" data-error-for="english"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="number" id="md-math" name="math" data-field="math" placeholder="Mathematics Mark" autocomplete="off">
                        <label for="md-math">Mathematics</label>
                        <p class="field-error hidden" data-error-for="math"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <a href="/mark-distributions" class="btn btn-light">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('markdist-page');
    if (!root || root.getAttribute('data-page') !== 'create') { return; }

    const form = document.getElementById('markdist-form');
    const sessionEl = document.getElementById('md-session');

    (async function () {
        try {
            const json = await BK.get('/api/sessionList');
            (json.sessionList || []).forEach(function (row) {
                const opt = document.createElement('option');
                opt.value = row.session;
                opt.textContent = row.session;
                sessionEl.appendChild(opt);
            });
        } catch (err) {
            BK.toast(err.message || 'Could not load sessions', 'error');
        }
    })();

    function fieldValue(name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.post('/api/markdistributions', {
                session: fieldValue('session'),
                total: fieldValue('total'),
                bangla: fieldValue('bangla'),
                english: fieldValue('english'),
                math: fieldValue('math')
            });
            BK.toast('Mark Distribution added successfully', 'success');
            window.location.href = '/mark-distributions';
        });
    });
});
</script>
