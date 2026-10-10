<?php
/**
 * Instructions page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/settings/instructions.vue —
 * shows Title + Description from GET /api/settings/1 when active === 1.
 */
?>
<div class="space-y-4" id="instructions-page">
    <div class="card">
        <div class="card-header">Instructions</div>
        <div class="card-body">
            <div id="instructions-empty" class="text-center text-slate-400">Loading...</div>
            <div id="instructions-body" class="hidden text-center space-y-2">
                <p class="text-lg font-semibold" id="instructions-title" style="color:rgb(114 102 237)"></p>
                <p class="text-sm whitespace-pre-line text-left" id="instructions-description"></p>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const empty = document.getElementById('instructions-empty');
    const body = document.getElementById('instructions-body');
    if (!body) { return; }

    (async function () {
        try {
            const json = await BK.get('/api/settings/1');
            const row = json.settingById || {};
            if (Number(row.active) === 1 && row.title) {
                document.getElementById('instructions-title').textContent = row.title;
                document.getElementById('instructions-description').textContent = row.description || '';
                body.classList.remove('hidden');
                empty.classList.add('hidden');
            } else {
                empty.textContent = 'No instructions published.';
            }
        } catch (err) {
            empty.textContent = 'Could not load instructions.';
            BK.toast(err.message || 'Could not load instructions', 'error');
        }
    })();
});
</script>
