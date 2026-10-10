<?php
/**
 * Edit mark distribution page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/markdistributions/edit.vue —
 * form fills from GET /api/markdistributions/{id} -> {markdistributionById:{...}},
 * saves via PUT /api/markdistributions/{id} -> {status:'success'}.
 * $markDistributionId is passed by MarkDistributionController::pageEdit().
 */
?>
<div class="space-y-4" id="markdist-page" data-page="edit"
     data-id="<?= (int) ($markDistributionId ?? 0) ?>">
    <div class="card max-w-3xl">
        <div class="card-header">Edit Mark Distribution</div>
        <div class="card-body">
            <form id="markdist-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="md-session">Session</label>
                        <select id="md-session" name="session" data-field="session">
                            <option value="">Select Session</option>
                        </select>
                        <p class="field-error hidden" data-error-for="session"></p>
                    </div>
                    <div>
                        <label class="label" for="md-total">Total Mark</label>
                        <input type="number" id="md-total" name="total" data-field="total" placeholder="Total Mark" autocomplete="off">
                        <p class="field-error hidden" data-error-for="total"></p>
                    </div>
                    <div>
                        <label class="label" for="md-bangla">Bangla</label>
                        <input type="number" id="md-bangla" name="bangla" data-field="bangla" placeholder="Bangla Mark" autocomplete="off">
                        <p class="field-error hidden" data-error-for="bangla"></p>
                    </div>
                    <div>
                        <label class="label" for="md-english">English</label>
                        <input type="number" id="md-english" name="english" data-field="english" placeholder="English Mark" autocomplete="off">
                        <p class="field-error hidden" data-error-for="english"></p>
                    </div>
                    <div>
                        <label class="label" for="md-math">Mathematics</label>
                        <input type="number" id="md-math" name="math" data-field="math" placeholder="Mathematics Mark" autocomplete="off">
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
    if (!root || root.getAttribute('data-page') !== 'edit') { return; }

    const form = document.getElementById('markdist-form');
    const sessionEl = document.getElementById('md-session');
    const id = root.getAttribute('data-id');

    function fill(name, value) {
        const el = form.querySelector('[data-field="' + name + '"]');
        if (el) { el.value = value === null || value === undefined ? '' : value; }
    }

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

        try {
            const json = await BK.get('/api/markdistributions/' + encodeURIComponent(id));
            const row = json.markdistributionById || {};
            fill('session', row.session);
            fill('total', row.total);
            fill('bangla', row.bangla);
            fill('english', row.english);
            fill('math', row.math);
        } catch (err) {
            BK.toast(err.message || 'Could not load the mark distribution', 'error');
        }
    })();

    function fieldValue(name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.put('/api/markdistributions/' + encodeURIComponent(id), {
                session: fieldValue('session'),
                total: fieldValue('total'),
                bangla: fieldValue('bangla'),
                english: fieldValue('english'),
                math: fieldValue('math')
            });
            BK.toast('Mark Distribution edited successfully', 'success');
            window.location.href = '/mark-distributions';
        });
    });
});
</script>
