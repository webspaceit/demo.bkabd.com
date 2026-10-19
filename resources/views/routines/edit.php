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
                    <div class="fl">
                        <select class="input" id="routine-session" name="session" data-field="session" required>
                            <option value="">Select Session</option>
                        </select>
                        <label for="routine-session">Exam Session</label>
                        <p class="field-error hidden" data-error-for="session"></p>
                    </div>
                    <div class="fl">
                        <select class="input" id="routine-class" name="class" data-field="class">
                            <option value="">All Classes</option>
                            <option value="6">KG</option>
                            <option value="1">One</option>
                            <option value="2">Two</option>
                            <option value="3">Three</option>
                            <option value="4">Four</option>
                            <option value="5">Five</option>
                        </select>
                        <label for="routine-class">Class</label>
                        <p class="field-error hidden" data-error-for="class"></p>
                    </div>
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
                        <input class="input" type="text" id="routine-time-start" placeholder="Select start time"
                               autocomplete="off" readonly>
                        <label for="routine-time-start">Start Time</label>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="routine-time-end" placeholder="Select end time"
                               autocomplete="off" readonly>
                        <label for="routine-time-end">End Time</label>
                        <p class="field-error hidden" data-error-for="time"></p>
                        <p class="hidden text-sm text-amber-700" id="routine-time-legacy-note">This routine only has a saved start time. Select an end time.</p>
                    </div>
                    <input type="hidden" id="routine-time" data-field="time">
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
    if (!root || root.getAttribute('data-routines-page') !== 'edit') { return; }

    const form = document.getElementById('routine-form');
    const routineId = root.getAttribute('data-routine-id');
    const sessionEl = document.getElementById('routine-session');
    const timeFieldEl = document.getElementById('routine-time');
    const timeStartEl = document.getElementById('routine-time-start');
    const timeEndEl = document.getElementById('routine-time-end');

    function syncTimeRange() {
        timeFieldEl.value = timeStartEl.value && timeEndEl.value
            ? timeStartEl.value + ' to ' + timeEndEl.value
            : '';
    }

    const timePickerOptions = {
        noCalendar: true,
        enableTime: true,
        time_24hr: false,
        dateFormat: 'h:i K',
        minuteIncrement: 5,
        allowInput: false,
        disableMobile: true,
        appendTo: document.body,
        onChange: syncTimeRange
    };
    if (typeof window.flatpickr === 'function') {
        window.flatpickr(timeStartEl, timePickerOptions);
        window.flatpickr(timeEndEl, timePickerOptions);
    }

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
            const sessions = await BK.get('/api/sessionList');
            (sessions.sessionList || []).forEach(function (session) {
                const option = document.createElement('option');
                option.value = session.session;
                option.textContent = session.session + (String(session.session) === String(sessions.activeSession) ? ' (active)' : '');
                sessionEl.appendChild(option);
            });

            const json = await BK.get('/api/routines/' + encodeURIComponent(routineId));
            const row = json.routineById || {};
            fill('session', row.session || sessions.activeSession);
            fill('class', row.class);
            fill('date', row.date);
            fill('day', row.day);
            const timeRange = String(row.time || '').match(/^\s*(.+?)\s+to\s+(.+?)\s*$/i);
            if (timeRange && timeStartEl._flatpickr && timeEndEl._flatpickr) {
                timeStartEl._flatpickr.setDate(timeRange[1].trim(), false, 'h:i K');
                timeEndEl._flatpickr.setDate(timeRange[2].trim(), false, 'h:i K');
            } else if (row.time && timeStartEl._flatpickr) {
                timeStartEl._flatpickr.setDate(String(row.time).trim(), false, 'h:i K');
                document.getElementById('routine-time-legacy-note').classList.remove('hidden');
            } else if (row.time) {
                BK.toast('This routine has an older time format. Select a start and end time to update it.', 'info');
            }
            fill('subject', row.subject);
            fill('order', row.order);
        } catch (err) {
            BK.toast(err.message || 'Could not load the routine', 'error');
        }
    })();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            if (!timeStartEl.value || !timeEndEl.value) {
                BK.toast('Select both a start time and an end time.', 'error');
                return;
            }
            const parseTimeMinutes = function (value) {
                const match = /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i.exec(value);
                if (!match) { return NaN; }
                let hour = Number(match[1]) % 12;
                if (match[3].toUpperCase() === 'PM') { hour += 12; }
                return hour * 60 + Number(match[2]);
            };
            if (parseTimeMinutes(timeEndEl.value) <= parseTimeMinutes(timeStartEl.value)) {
                BK.toast('End time must be later than start time.', 'error');
                return;
            }
            syncTimeRange();
            await BK.put('/api/routines/' + encodeURIComponent(routineId), {
                session: fieldValue('session'),
                class: fieldValue('class'),
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
