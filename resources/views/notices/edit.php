<?php
/**
 * Edit Notice page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/notices/edit.vue —
 * form fills from GET /api/notices/{id} -> {noticeById:{...}}, centers from
 * GET /api/center -> {centerList:[...]}.
 * $noticeId is passed by NoticeController::pageEdit().
 */
?>
<div class="space-y-4" id="notices-page" data-notices-page="edit" data-notice-id="<?= (int) ($noticeId ?? 0) ?>">
    <div class="card max-w-3xl">
        <div class="card-header">Edit Notice</div>
        <div class="card-body">
            <form id="notice-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="fl">
                        <select class="input" id="notice-center" name="center_id" data-field="center_id">
                            <option value="">Select Center</option>
                            <option value="0">All</option>
                        </select>
                        <label for="notice-center">Select Center</label>
                        <p class="field-error hidden" data-error-for="center_id"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="notice-title" name="title" data-field="title"
                               placeholder="Title" autocomplete="off">
                        <label for="notice-title">Title</label>
                        <p class="field-error hidden" data-error-for="title"></p>
                    </div>
                    <div class="md:col-span-2 fl">
                        <textarea class="input" id="notice-description" name="description" data-field="description"
                                  rows="5" placeholder="Description"></textarea>
                        <label for="notice-description">Description</label>
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
    if (!root || root.getAttribute('data-notices-page') !== 'edit') { return; }

    const form = document.getElementById('notice-form');
    const centerSel = document.getElementById('notice-center');
    const noticeId = root.getAttribute('data-notice-id');

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
            const json = await BK.get('/api/center');
            (json.centerList || []).forEach(function (center) {
                const opt = document.createElement('option');
                opt.value = String(center.id);
                opt.textContent = String(center.name || '').toUpperCase();
                centerSel.appendChild(opt);
            });
            const notice = (await BK.get('/api/notices/' + encodeURIComponent(noticeId))).noticeById || {};
            fill('center_id', notice.center_id);
            fill('title', notice.title);
            fill('description', notice.description);
        } catch (err) {
            BK.toast(err.message || 'Could not load the notice', 'error');
        }
    })();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.put('/api/notices/' + encodeURIComponent(noticeId), {
                center_id: fieldValue('center_id'),
                title: fieldValue('title'),
                description: fieldValue('description')
            });
            BK.toast('Notice edited successfully', 'success');
            window.location.href = '/notices';
        });
    });
});
</script>
