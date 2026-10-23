<?php
/** Seat Plan room assignment and print page. */
?>
<div class="space-y-4" id="seatplan-page">
    <section class="card">
        <div class="card-header"><h1 class="text-lg font-semibold">Seat Plan</h1></div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="fl">
                    <select class="input" id="seatplan-session" required><option value="">Select Session</option></select>
                    <label for="seatplan-session">Exam Session <span class="text-red-500">*</span></label>
                </div>
                <div class="fl">
                    <select class="input" id="seatplan-center" required><option value="">Select Center</option></select>
                    <label for="seatplan-center">Center <span class="text-red-500">*</span></label>
                </div>
                <div class="fl">
                    <select class="input" id="seatplan-class" required>
                        <option value="">Select Class</option>
                        <option value="6">KG</option><option value="1">One</option>
                        <option value="2">Two</option><option value="3">Three</option>
                        <option value="4">Four</option><option value="5">Five</option>
                    </select>
                    <label for="seatplan-class">Class <span class="text-red-500">*</span></label>
                </div>
            </div>
            <p id="seatplan-scope-help" class="mt-3 text-sm text-slate-500">Select a session, center, and class to manage room assignments.</p>
        </div>
    </section>

    <section id="seatplan-assignment-panel" class="card hidden">
        <div class="card-header flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold">Room Assignments</h2>
                <p class="text-sm text-slate-500">Assign students by roll range or let room capacity fill remaining rolls in order.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="seatplan-add-room" class="btn btn-light">Add Room</button>
                <button type="button" id="seatplan-save" class="btn btn-primary">Save Assignments</button>
                <button type="button" id="seatplan-print" class="btn btn-light">View / Print Seat Plan</button>
                <button type="button" id="seatplan-print-cards" class="btn btn-light">Print Seat Arrangement Cards</button>
            </div>
        </div>
        <div class="card-body">
            <div class="overflow-x-auto">
                <table class="table min-w-[850px]">
                    <thead><tr>
                        <th>Building Name</th><th>Floor Number</th><th>Room Number</th>
                        <th>Assign By</th><th>Roll Start</th><th>Roll End</th><th>Capacity</th><th>Action</th>
                    </tr></thead>
                    <tbody id="seatplan-room-rows"></tbody>
                </table>
            </div>
            <p id="seatplan-empty" class="py-5 text-center text-sm text-slate-500">No rooms added yet. Add at least one room assignment.</p>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('seatplan-page');
    if (!page) { return; }

    const sessionEl = document.getElementById('seatplan-session');
    const centerEl = document.getElementById('seatplan-center');
    const classEl = document.getElementById('seatplan-class');
    const panelEl = document.getElementById('seatplan-assignment-panel');
    const rowsEl = document.getElementById('seatplan-room-rows');
    const emptyEl = document.getElementById('seatplan-empty');
    const helpEl = document.getElementById('seatplan-scope-help');
    const saveBtn = document.getElementById('seatplan-save');
    const printBtn = document.getElementById('seatplan-print');
    const printCardsBtn = document.getElementById('seatplan-print-cards');

    function currentScope() {
        return { session: sessionEl.value, center_id: centerEl.value, class: classEl.value };
    }
    function hasScope(scope) { return !!(scope.session && scope.center_id && scope.class); }
    function syncEmpty() { emptyEl.classList.toggle('hidden', rowsEl.children.length > 0); }

    function addRoomRow(assignment) {
        assignment = assignment || {};
        const row = document.createElement('tr');
        row.innerHTML = '<td><input class="input" data-room="building_name" maxlength="120" placeholder="Building name"></td>' +
            '<td><input class="input" data-room="floor_number" maxlength="50" placeholder="Floor"></td>' +
            '<td><input class="input" data-room="room_number" maxlength="50" placeholder="Room"></td>' +
            '<td><select class="input" data-room-mode><option value="range">Roll range</option><option value="capacity">Capacity</option></select></td>' +
            '<td><input class="input" data-room="roll_start" type="number" min="1" placeholder="From"></td>' +
            '<td><input class="input" data-room="roll_end" type="number" min="1" placeholder="To"></td>' +
            '<td><input class="input hidden" data-room="room_capacity" type="number" min="1" placeholder="Seats"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger" data-remove-room aria-label="Remove room">×</button></td>';
        rowsEl.appendChild(row);
        row.querySelector('[data-room="building_name"]').value = assignment.building_name || '';
        row.querySelector('[data-room="floor_number"]').value = assignment.floor_number || '';
        row.querySelector('[data-room="room_number"]').value = assignment.room_number || '';
        const mode = assignment.roll_start !== null && assignment.roll_start !== undefined ? 'range' : 'capacity';
        row.querySelector('[data-room-mode]').value = assignment.id ? mode : 'range';
        row.querySelector('[data-room="roll_start"]').value = assignment.roll_start ?? '';
        row.querySelector('[data-room="roll_end"]').value = assignment.roll_end ?? '';
        row.querySelector('[data-room="room_capacity"]').value = assignment.room_capacity ?? '';

        function syncMode() {
            const byCapacity = row.querySelector('[data-room-mode]').value === 'capacity';
            row.querySelector('[data-room="roll_start"]').classList.toggle('hidden', byCapacity);
            row.querySelector('[data-room="roll_end"]').classList.toggle('hidden', byCapacity);
            row.querySelector('[data-room="room_capacity"]').classList.toggle('hidden', !byCapacity);
        }
        row.querySelector('[data-room-mode]').addEventListener('change', syncMode);
        row.querySelector('[data-remove-room]').addEventListener('click', function () {
            row.remove();
            syncEmpty();
        });
        syncMode();
        syncEmpty();
    }

    function collectAssignments() {
        return Array.from(rowsEl.querySelectorAll('tr')).map(function (row) {
            const value = function (name) { return row.querySelector('[data-room="' + name + '"]').value.trim(); };
            const mode = row.querySelector('[data-room-mode]').value;
            return {
                building_name: value('building_name'),
                floor_number: value('floor_number'),
                room_number: value('room_number'),
                mode: mode,
                roll_start: mode === 'range' ? value('roll_start') : '',
                roll_end: mode === 'range' ? value('roll_end') : '',
                room_capacity: mode === 'capacity' ? value('room_capacity') : ''
            };
        });
    }

    async function loadAssignments() {
        const scope = currentScope();
        if (!hasScope(scope)) {
            panelEl.classList.add('hidden');
            helpEl.textContent = 'Select a session, center, and class to manage room assignments.';
            return;
        }
        helpEl.textContent = 'Loading room assignments…';
        panelEl.classList.remove('hidden');
        rowsEl.innerHTML = '';
        try {
            const result = await BK.get(BK.qs('/api/seatplan/assignments', scope));
            if (result.schema_ready === false) {
                saveBtn.disabled = true;
                helpEl.textContent = 'Room assignments are unavailable until the Seat Plan database update is applied.';
                return;
            }
            saveBtn.disabled = false;
            (result.assignments || []).forEach(addRoomRow);
            helpEl.textContent = 'Assignments are saved for this session, center, and class.';
        } catch (err) {
            panelEl.classList.add('hidden');
            helpEl.textContent = 'Could not load room assignments.';
            BK.toast(err.message || 'Could not load room assignments.', 'error');
        }
    }

    Promise.all([BK.get('/api/sessionList'), BK.get('/api/centerList')]).then(function (results) {
        const sessions = results[0];
        (sessions.sessionList || []).forEach(function (item) {
            const option = document.createElement('option');
            option.value = item.session;
            option.textContent = item.session + (String(item.session) === String(sessions.activeSession) ? ' (active)' : '');
            sessionEl.appendChild(option);
        });
        sessionEl.value = String(sessions.activeSession || '');

        const centerResponse = results[1];
        const centers = centerResponse.data?.centerList || centerResponse.centerList || [];
        centers.forEach(function (center) {
            const option = document.createElement('option');
            option.value = center.id;
            option.textContent = String(center.center_name || center.name || '').toUpperCase();
            centerEl.appendChild(option);
        });
        if (centers.length === 1) { centerEl.value = String(centers[0].id); }
        loadAssignments();
    }).catch(function (err) {
        BK.toast(err.message || 'Could not load sessions and centers.', 'error');
    });

    [sessionEl, centerEl, classEl].forEach(function (select) {
        select.addEventListener('change', loadAssignments);
    });
    document.getElementById('seatplan-add-room').addEventListener('click', function () { addRoomRow(); });

    saveBtn.addEventListener('click', async function () {
        const scope = currentScope();
        const assignments = collectAssignments();
        if (!hasScope(scope)) {
            BK.toast('Select a session, center, and class first.', 'error');
            return;
        }
        if (!assignments.length) {
            BK.toast('Add at least one room assignment.', 'error');
            return;
        }
        saveBtn.disabled = true;
        try {
            const result = await BK.post('/api/seatplan/assignments', Object.assign({}, scope, { assignments: assignments }));
            BK.toast((result.saved || assignments.length) + ' room assignment(s) saved.', 'success');
            await loadAssignments();
        } catch (err) {
            BK.toast(err.message || 'Could not save room assignments.', 'error');
        } finally {
            saveBtn.disabled = false;
        }
    });

    printBtn.addEventListener('click', function () {
        const scope = currentScope();
        if (!hasScope(scope)) { return; }
        window.open('/api/seatplan_print/' + encodeURIComponent(scope.center_id) + '/' + encodeURIComponent(scope.class)
            + '?session=' + encodeURIComponent(scope.session), '_blank', 'scrollbars=yes,width=1000,height=700');
    });

    printCardsBtn.addEventListener('click', function () {
        const scope = currentScope();
        if (!hasScope(scope)) { return; }
        window.open('/api/seatplan_cards/' + encodeURIComponent(scope.center_id) + '/' + encodeURIComponent(scope.class)
            + '?session=' + encodeURIComponent(scope.session), '_blank', 'scrollbars=yes,width=1000,height=700');
    });
});
</script>
