<?php
/**
 * Edit Routine page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/routines/edit.vue —
 * form fills from GET /api/routines/{id} -> {routineById:{...}}.
 * $routineId is passed by RoutineController::pageEdit().
 */
?>
<div class="space-y-4" id="routines-page" data-routines-page="edit" data-routine-id="<?= (int) ($routineId ?? 0) ?>">
    <div class="card max-w-2xl">
        <div class="card-header">Edit Routine</div>
        <div class="card-body">
            <form id="routine-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="routine-date">Date</label>
                        <input type="text" id="routine-date" name="date" data-field="date"
                               placeholder="Date" autocomplete="off">
                        <p class="field-error hidden" data-error-for="date"></p>
                    </div>
                    <div>
                        <label class="label" for="routine-day">Day</label>
                        <input type="text" id="routine-day" name="day" data-field="day"
                               placeholder="Day" autocomplete="off">
                        <p class="field-error hidden" data-error-for="day"></p>
                    </div>
                    <div>
                        <label class="label" for="routine-time">Time</label>
                        <input type="text" id="routine-time" name="time" data-field="time"
                               placeholder="Time" autocomplete="off">
                        <p class="field-error hidden" data-error-for="time"></p>
                    </div>
                    <div>
                        <label class="label" for="routine-subject">Subject</label>
                        <input type="text" id="routine-subject" name="subject" data-field="subject"
                               placeholder="Subject" autocomplete="off">
                        <p class="field-error hidden" data-error-for="subject"></p>
                    </div>
                    <div>
                        <label class="label" for="routine-order">Order</label>
                        <input type="number" id="routine-order" name="order" data-field="order"
                               placeholder="Order" autocomplete="off">
                        <p class="field-error hidden" data-error-for="order"></p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <a href="/routines" class="btn btn-light">Back</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('routines-page');
    if (!root || root.getAttribute('data-routines-page') !== 'edit') { return; }

    const form = document.getElementById('routine-form');
    const routineId = root.getAttribute('data-routine-id');

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
            const json = await BK.get('/api/routines/' + encodeURIComponent(routineId));
            const row = json.routineById || {};
            fill('date', row.date);
            fill('day', row.day);
            fill('time', row.time);
            fill('subject', row.subject);
            fill('order', row.order);
        } catch (err) {
            BK.toast(err.message || 'Could not load the routine', 'error');
        }
    })();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.put('/api/routines/' + encodeURIComponent(routineId), {
                date: fieldValue('date'),
                day: fieldValue('day'),
                time: fieldValue('time'),
                subject: fieldValue('subject'),
                order: fieldValue('order')
            });
            BK.toast('Routine edited successfully', 'success');
            window.location.href = '/routines';
        });
    });
});
</script>
