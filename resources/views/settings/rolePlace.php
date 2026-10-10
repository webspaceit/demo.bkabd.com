<?php
/**
 * Roll/seat placement page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/settings/rolePlace.vue —
 * one confirm-then-run button; original called GET /api/rollplace/{year}/1,
 * here POST (state-changing).
 */
?>
<div class="space-y-4" id="role-place-page">
    <div class="card max-w-xl">
        <div class="card-header">Roll Place</div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Roll Place</th>
                        <th class="text-center" style="width:80px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Assign rolls to unplaced students of the active session</td>
                        <td class="text-center">
                            <button type="button" id="rollplace-btn" class="btn btn-sm btn-success" title="Submit">&#9745;</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('rollplace-btn');
    if (!btn) { return; }

    btn.addEventListener('click', async function () {
        if (!(await BK.confirm('Are you sure? You won\'t be able to revert this!'))) { return; }
        btn.disabled = true;
        try {
            const year = new Date().getFullYear();
            await BK.post('/api/rollplace/' + encodeURIComponent(year) + '/1', {});
            BK.toast('Roll Place done.', 'success');
        } catch (err) {
            BK.toast(err.message || 'Roll place failed', 'error');
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
