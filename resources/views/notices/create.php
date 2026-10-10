<?php
/**
 * Add Notice page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/notices/create.vue —
 * fields: center_id (Select Center / All / center list from GET /api/center),
 * title, description.
 */
?>
<div class="space-y-4" id="notices-page" data-notices-page="create">
    <div class="card max-w-3xl">
        <div class="card-header">Notice</div>
        <div class="card-body">
            <form id="notice-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="notice-center">Select Center</label>
                        <select id="notice-center" name="center_id" data-field="center_id">
                            <option value="">Select Center</option>
                            <option value="0">All</option>
                        </select>
                        <p class="field-error hidden" data-error-for="center_id"></p>
                    </div>
                    <div>
                        <label class="label" for="notice-title">Title</label>
                        <input type="text" id="notice-title" name="title" data-field="title"
                               placeholder="Title" autocomplete="off">
                        <p class="field-error hidden" data-error-for="title"></p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="label" for="notice-description">Description</label>
                        <textarea id="notice-description" name="description" data-field="description"
                                  rows="5" placeholder="Description"></textarea>
                        <p class="field-error hidden" data-error-for="description"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <a href="/notices" class="btn btn-light">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('notices-page');
    if (!root || root.getAttribute('data-notices-page') !== 'create') { return; }

    const form = document.getElementById('notice-form');
    const centerSel = document.getElementById('notice-center');

    function fieldValue(name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    (async function () {
        try {
            const json = await BK.get('/api/center');
            (json.centerList || []).forEach(function (center) {
                const opt = document.createElement('option');
                opt.value = String(center.id);
                opt.textContent = String(center.name || '').toUpperCase();
                centerSel.appendChild(opt);
            });
        } catch (err) {
            BK.toast(err.message || 'Could not load centers', 'error');
        }
    })();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.post('/api/notices', {
                center_id: fieldValue('center_id'),
                title: fieldValue('title'),
                description: fieldValue('description')
            });
            BK.toast('Notice created successfully', 'success');
            window.location.href = '/notices';
        });
    });
});
</script>
