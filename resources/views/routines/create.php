<?php
/**
 * Add Routine page (behaviour inline at the bottom of this view).
 * Original Vue page: resources/js/components/routines/create.vue —
 * fields: session, class, date, day, time, subject, order.
 */
?>
<div class="space-y-4" id="routines-page" data-routines-page="create">
    <div class="card max-w-2xl">
        <div class="card-header">Add Routine</div>
        <div class="card-body">
            <p id="routine-duplicate-notice" class="hidden mb-4 rounded bg-blue-50 px-3 py-2 text-sm text-blue-800">Copied routine details loaded. Review them and submit to create the duplicate.</p>
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
                        <select class="input" id="routine-class" name="class" data-field="class" required>
                            <option value="">Select Class</option>
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
                    <div class="fl dob-field">
                        <div class="dob-field__wrap">
                            <span class="dob-field__icon" aria-hidden="true">
                                <svg fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Z"/></svg>
                            </span>
                            <input class="input" type="text" id="routine-date" data-field="date" data-dobpicker disabled
                                   data-allow-future data-default-today data-picker-label="exam date"
                                   placeholder="DD-MM-YYYY" autocomplete="off" readonly aria-haspopup="dialog" aria-expanded="false">
                        </div>
                        <input type="hidden" id="routine-date-iso" data-dobpicker-value>
                        <label for="routine-date">Date</label>
                        <p class="field-error hidden" data-error-for="date"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="routine-day" name="day" data-field="day"
                               placeholder="Day" autocomplete="off" readonly>
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
                    </div>
                    <input type="hidden" id="routine-time" name="time" data-field="time">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Subjects</label>
                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                            <?php foreach (['Bangla', 'English', 'Mathematics'] as $subjectOption): ?>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" class="accent-emerald-600" data-subject-option value="<?= e($subjectOption) ?>">
                                    <?= e($subjectOption) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <input class="input min-w-[12rem] flex-1" type="text" id="routine-new-subject"
                                   placeholder="Enter another subject" autocomplete="off">
                            <button class="btn btn-light" type="button" id="routine-add-subject">Add subject</button>
                        </div>
                        <div id="routine-custom-subjects" class="mt-2 flex flex-wrap gap-2"></div>
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
    const sessionEl = document.getElementById('routine-session');
    const dateEl = document.getElementById('routine-date');
    const dateIsoEl = document.getElementById('routine-date-iso');
    const dayEl = document.getElementById('routine-day');
    const timeEl = document.getElementById('routine-time');
    const timeStartEl = document.getElementById('routine-time-start');
    const timeEndEl = document.getElementById('routine-time-end');
    const newSubjectEl = document.getElementById('routine-new-subject');
    const customSubjectsEl = document.getElementById('routine-custom-subjects');
    const customSubjects = [];

    function syncDateToSession() {
        const year = String(sessionEl.value || '').trim();
        if (!/^\d{4}$/.test(year)) { return false; }
        const minDate = year + '-01-01';
        const maxDate = year + '-12-31';
        dateEl.setAttribute('data-default-year', year);
        if (window.DOBPicker && typeof window.DOBPicker.setRange === 'function') {
            window.DOBPicker.setRange(dateEl, minDate, maxDate);
        } else {
            dateEl.setAttribute('data-min-date', minDate);
            dateEl.setAttribute('data-max-date', maxDate);
        }
        dateEl.disabled = false;
        return true;
    }

    function addCustomSubject(value) {
        const subject = String(value || '').trim();
        if (!subject || customSubjects.some(function (item) { return item.toLowerCase() === subject.toLowerCase(); })) {
            return;
        }
        customSubjects.push(subject);
        const chip = document.createElement('span');
        chip.className = 'inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-800';
        chip.dataset.subjectChip = subject;
        const name = document.createElement('span');
        name.textContent = subject;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'font-bold text-emerald-700';
        remove.setAttribute('aria-label', 'Remove ' + subject);
        remove.textContent = '×';
        remove.addEventListener('click', function () {
            const index = customSubjects.indexOf(subject);
            if (index !== -1) { customSubjects.splice(index, 1); }
            chip.remove();
        });
        chip.append(name, remove);
        customSubjectsEl.appendChild(chip);
    }

    document.getElementById('routine-add-subject').addEventListener('click', function () {
        addCustomSubject(newSubjectEl.value);
        newSubjectEl.value = '';
        newSubjectEl.focus();
    });
    newSubjectEl.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.getElementById('routine-add-subject').click();
        }
    });

    if (window.DOBPicker && typeof window.DOBPicker.refresh === 'function') {
        window.DOBPicker.refresh();
    }
    function syncTimeRange() {
        timeEl.value = timeStartEl.value && timeEndEl.value
            ? timeStartEl.value + ' to ' + timeEndEl.value
            : '';
    }

    if (typeof window.flatpickr === 'function') {
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
        if (!timeStartEl._flatpickr) { window.flatpickr(timeStartEl, timePickerOptions); }
        if (!timeEndEl._flatpickr) { window.flatpickr(timeEndEl, timePickerOptions); }
    }

    dateIsoEl.addEventListener('change', function () {
        if (!dateIsoEl.value) {
            dayEl.value = '';
            return;
        }
        const parts = dateIsoEl.value.split('-').map(Number);
        const selectedDate = new Date(parts[0], parts[1] - 1, parts[2]);
        dayEl.value = selectedDate.toLocaleDateString('en-US', { weekday: 'long' });
    });

    (async function () {
        try {
            const json = await BK.get('/api/sessionList');
            (json.sessionList || []).forEach(function (row) {
                const option = document.createElement('option');
                option.value = row.session;
                option.textContent = row.session + (String(row.session) === String(json.activeSession) ? ' (active)' : '');
                option.selected = String(row.session) === String(json.activeSession);
                sessionEl.appendChild(option);
            });
            syncDateToSession();

            if (new URLSearchParams(window.location.search).get('duplicate') === '1') {
                let source = null;
                try {
                    source = JSON.parse(sessionStorage.getItem('routine-duplicate') || 'null');
                    sessionStorage.removeItem('routine-duplicate');
                } catch (err) {
                    source = null;
                }
                if (source) {
                    if (Array.from(sessionEl.options).some(function (option) { return option.value === String(source.session); })) {
                        sessionEl.value = String(source.session);
                        syncDateToSession();
                    }
                    const classEl = document.getElementById('routine-class');
                    classEl.value = source.class === null || source.class === undefined ? '' : String(source.class);

                    const dateMatch = String(source.date || '').match(/^(\d{2})-(\d{2})-(\d{4})$/);
                    if (dateMatch) {
                        dateEl.value = source.date;
                        dateIsoEl.value = dateMatch[3] + '-' + dateMatch[2] + '-' + dateMatch[1];
                    }
                    dayEl.value = source.day || '';

                    const times = String(source.time || '').match(/^\s*(.+?)\s+to\s+(.+?)\s*$/i);
                    if (timeStartEl._flatpickr) {
                        const startTime = times ? times[1].trim() : String(source.time || '').trim();
                        if (startTime) { timeStartEl._flatpickr.setDate(startTime, false, 'h:i K'); }
                        if (times && timeEndEl._flatpickr) {
                            timeEndEl._flatpickr.setDate(times[2].trim(), false, 'h:i K');
                        }
                    }

                    const subject = String(source.subject || '').trim();
                    const subjectOption = Array.from(form.querySelectorAll('[data-subject-option]'))
                        .find(function (checkbox) { return checkbox.value.toLowerCase() === subject.toLowerCase(); });
                    if (subjectOption) { subjectOption.checked = true; }
                    else { addCustomSubject(subject); }
                    document.getElementById('routine-order').value = source.order || 1;
                    document.getElementById('routine-duplicate-notice').classList.remove('hidden');
                }
            }
        } catch (err) {
            BK.toast(err.message || 'Could not load sessions', 'error');
            dateEl.disabled = false;
        }
    })();

    sessionEl.addEventListener('change', syncDateToSession);

    // Date may be entered from the calendar only; the paired ISO value powers
    // automatic weekday selection while the submitted date remains DD-MM-YYYY.
    dateEl.addEventListener('change', function () {
        if (!dateEl.value) { dayEl.value = ''; }
    });

    function fieldValue(name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            if (!fieldValue('class')) {
                BK.toast('Select one class for these subjects.', 'error');
                return;
            }
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
            addCustomSubject(newSubjectEl.value);
            const subjects = Array.from(form.querySelectorAll('[data-subject-option]:checked'))
                .map(function (checkbox) { return checkbox.value; })
                .concat(customSubjects);
            const uniqueSubjects = subjects.filter(function (subject, index) {
                return subjects.findIndex(function (item) { return item.toLowerCase() === subject.toLowerCase(); }) === index;
            });
            if (!uniqueSubjects.length) {
                BK.toast('Select or add at least one subject.', 'error');
                return;
            }

            const baseOrder = Number(fieldValue('order')) || 1;
            for (let i = 0; i < uniqueSubjects.length; i++) {
                await BK.post('/api/routines', {
                    session: fieldValue('session'),
                    class: fieldValue('class'),
                    date: fieldValue('date'),
                    day: fieldValue('day'),
                    time: fieldValue('time'),
                    subject: uniqueSubjects[i],
                    order: baseOrder + i
                });
            }
            BK.toast(uniqueSubjects.length + ' routine ' + (uniqueSubjects.length === 1 ? 'entry' : 'entries') + ' added successfully', 'success');
            window.location.href = '/routines';
        });
    });
});
</script>
