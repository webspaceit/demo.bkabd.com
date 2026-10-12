<?php
/**
 * Add Routine page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/routines/create.vue —
 * fields: date, day, time, subject, order.
 */
?>
<div class="space-y-4" id="routines-page" data-routines-page="create">
    <div class="card max-w-2xl">
        <div class="card-header">Add Routine</div>
        <div class="card-body">
            <form id="routine-form" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="fl">
                        <input class="input" type="text" id="routine-date" name="date" data-field="date"
                               placeholder="Date" autocomplete="off">
                        <label for="routine-date">Date</label>
                        <p class="field-error hidden" data-error-for="date"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="routine-day" name="day" data-field="day"
                               placeholder="Day" autocomplete="off">
                        <label for="routine-day">Day</label>
                        <p class="field-error hidden" data-error-for="day"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="routine-time" name="time" data-field="time"
                               placeholder="Time" autocomplete="off">
                        <label for="routine-time">Time</label>
                        <p class="field-error hidden" data-error-for="time"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="routine-subject" name="subject" data-field="subject"
                               placeholder="Subject" autocomplete="off">
                        <label for="routine-subject">Subject</label>
                        <p class="field-error hidden" data-error-for="subject"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="number" id="routine-order" name="order" data-field="order"
                               placeholder="Order" autocomplete="off">
                        <label for="routine-order">Order</label>
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
    if (!root || root.getAttribute('data-routines-page') !== 'create') { return; }

    const form = document.getElementById('routine-form');

    function fieldValue(name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.post('/api/routines', {
                date: fieldValue('date'),
                day: fieldValue('day'),
                time: fieldValue('time'),
                subject: fieldValue('subject'),
                order: fieldValue('order')
            });
            BK.toast('Routines added successfully', 'success');
            window.location.href = '/routines';
        });
    });
});
</script>
