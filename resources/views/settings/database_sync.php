<div class="space-y-4" id="database-sync-page">
    <div class="card">
        <div class="card-header flex flex-wrap items-center justify-between gap-3">
            <span>Database structure sync</span>
            <span id="db-sync-badge" class="rounded-full border border-slate-300 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">Checking setup…</span>
        </div>
        <div class="card-body space-y-5">
            <p class="text-sm leading-6 text-slate-600">
                This page compares table structures and can import missing student registrations from <strong>exam.bkabd.com</strong> into <strong>demo.bkabd.com</strong>.
                The exam connection is read-only. Student imports add rows only; photo sync is limited to the current session; center/institute references and center display names are copied to demo only after preview and confirmation.
            </p>

            <div id="db-sync-setup" class="hidden rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                <strong>Server setup is required before this can connect.</strong>
                <p class="mt-1">Add the settings below to the private <code>.env</code> file used by the app you are running (your local PC, in this case). Do not paste passwords here or commit them to Git.</p>
                <ul id="db-sync-missing" class="mt-2 list-disc pl-5"></ul>
                <p class="mt-2">The sync uses separate database connections, so your local app can keep its current <code>DB_*</code> settings. Set the <code>SYNC_SOURCE_*</code> connection to exam with a read-only user. Set <code>SYNC_TARGET_*</code> to demo with a user that can read demo, add student rows, and update center display names, and has no access to exam.</p>
                <p class="mt-2">Set <code>DB_SYNC_ENABLED=true</code> and <code>DB_SYNC_ALLOW_LOCAL=true</code> in your local private configuration only after checking the target database name. Your host must allow remote MySQL connections from your PC to both databases.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <h2 class="font-semibold text-emerald-900">Exam: read-only reference</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Use the database server host provided by your hosting provider. <code>localhost</code> works only if the exam database is on the same PC as this app. Schema comparison reads metadata; student preview and import read source rows through a read-only transaction.</p>
                    <p class="mt-2 text-xs text-slate-500">Exam needs read-only SELECT access to students, centers, institutes, users, divisions, districts, upozilas, and unions, plus schema metadata. For photos, configure <code>SYNC_SOURCE_PHOTO_DIR</code> to the exam upload folder when both apps share a VPS; otherwise source photos must be reachable over HTTPS. This app never inserts, updates, or deletes exam data.</p>
                    <p id="db-sync-source" class="mt-2 text-xs text-slate-500"></p>
                </section>
                <section class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                    <h2 class="font-semibold text-sky-900">Demo: migration destination</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Imports use a separate demo connection. The demo account needs SELECT on student/reference tables, INSERT and UPDATE on <code>students</code> for imports and photo paths, INSERT on <code>student_duplicate_keys</code> when present, and permission to apply the reviewed reference migrations plus INSERT on center, institute, and location tables. It also needs UPDATE on <code>centers</code> for center-name sync and write access to <code>public/uploads/students</code>. It does not need write access to exam.</p>
                    <p id="db-sync-target" class="mt-2 text-xs text-slate-500"></p>
                </section>
            </div>

            <section class="rounded-xl border border-emerald-200 bg-white p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-800">Latest student registrations on exam</h2>
                        <p class="mt-1 text-sm text-slate-500">Shows the newest 20 records currently stored on exam. Use the import section below to add missing registrations to demo.</p>
                    </div>
                    <button id="db-sync-refresh-students" type="button" class="btn btn-light" disabled>Refresh latest entries</button>
                </div>
                <div id="db-sync-students-status" class="text-sm text-slate-500">Waiting for connection setup…</div>
                <div id="db-sync-students-table" class="mt-3 hidden overflow-x-auto"></div>
            </section>

            <section class="rounded-xl border border-amber-300 bg-amber-50/60 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-amber-950">Import missing exam students into demo</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-700">Preview first. The import adds registrations missing from demo, skips duplicate registrations and unsafe center/institute/location or roll conflicts, and leaves existing demo rows untouched. Student photos can be synced separately below; user accounts and marks are not copied.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="db-sync-preview-import" type="button" class="btn btn-light" disabled>Preview import</button>
                        <button id="db-sync-start-import" type="button" class="btn btn-primary hidden" disabled>Import missing students</button>
                    </div>
                </div>
                <div id="db-sync-import-preview" class="mt-3 hidden rounded-lg border border-amber-200 bg-white p-3 text-sm text-slate-700" aria-live="polite"></div>
                <div id="db-sync-import-progress" class="mt-2 hidden text-sm text-slate-600" aria-live="polite"></div>
            </section>

            <section class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-sky-950">Sync student photos from exam</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-700">Matches by registration number and syncs only students in the current session, including newly imported students from that session. Photos are center-cropped and resized to 60 × 70 px before saving in demo. An available exam photo replaces the demo photo; missing or unsafe source photos leave the demo photo unchanged. Exam stays read-only.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="db-sync-refresh-photo-counts" type="button" class="btn btn-light" disabled>Refresh counts</button>
                        <button id="db-sync-start-photos" type="button" class="btn btn-primary" disabled>Sync student photos</button>
                    </div>
                </div>
                <div id="db-sync-photo-summary" class="mt-3 hidden rounded-lg border border-sky-200 bg-white p-3 text-sm text-slate-700" aria-live="polite"></div>
                <div id="db-sync-photo-progress" class="mt-3 hidden text-sm text-slate-600" aria-live="polite"></div>
                <div class="mt-4 rounded-lg border border-sky-200 bg-white p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-slate-800">Recent photo syncs</h3>
                        <button id="db-sync-refresh-photo-history" type="button" class="btn btn-light">Refresh history</button>
                    </div>
                    <div id="db-sync-photo-history" class="overflow-x-auto text-sm text-slate-600" aria-live="polite">Loading sync history…</div>
                    <div id="db-sync-photo-run-details" class="mt-3 hidden overflow-x-auto rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-sm text-slate-700" aria-live="polite"></div>
                </div>
            </section>

            <section class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-indigo-950">Sync center display names from exam</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-700">Matches centers only by a unique center code. Apply the pending center display-name schema migration first. Preview then shows exact changes; sync stores names on the matching demo center record, even if its linked account is missing. Accounts and passwords are not copied, and exam stays read-only.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="db-sync-preview-centers" type="button" class="btn btn-light" disabled>Preview center names</button>
                        <button id="db-sync-start-centers" type="button" class="btn btn-primary hidden" disabled>Sync center names</button>
                    </div>
                </div>
                <div id="db-sync-centers-preview" class="mt-3 hidden overflow-x-auto rounded-lg border border-indigo-200 bg-white p-3 text-sm text-slate-700" aria-live="polite"></div>
            </section>

            <section class="rounded-xl border border-teal-200 bg-teal-50/50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-teal-950">Sync missing centers, institutes, and locations</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-700">Preview missing exam reference records, then add safe matches to demo, including division, district, upazila, and union hierarchy. Existing records are not changed. Creates reference rows without login accounts and copies no passwords, photos, or private contact information. Exam stays read-only.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="db-sync-preview-references" type="button" class="btn btn-light" disabled>Preview reference sync</button>
                        <button id="db-sync-start-references" type="button" class="btn btn-primary hidden" disabled>Sync missing references</button>
                    </div>
                </div>
                <div id="db-sync-references-preview" class="mt-3 hidden overflow-x-auto rounded-lg border border-teal-200 bg-white p-3 text-sm text-slate-700" aria-live="polite"></div>
            </section>

            <dialog id="db-sync-references-confirm" class="rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/50" style="width:40vw; min-width:min(90vw, 22rem); max-width:calc(100vw - 2rem)">
                <form method="dialog" class="p-6">
                    <h2 class="text-lg font-semibold text-slate-900">Add missing center and institute references?</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Only the missing reference records shown in the preview will be added to demo. Existing records remain unchanged. No login accounts, passwords, or private contact details are copied. Exam remains read-only.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="submit" value="cancel" class="btn btn-light">Cancel</button>
                        <button type="submit" value="confirm" class="btn btn-primary">Continue sync</button>
                    </div>
                </form>
            </dialog>

            <dialog id="db-sync-centers-confirm" class="rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/50" style="width:40vw; min-width:min(90vw, 22rem); max-width:calc(100vw - 2rem)">
                <form method="dialog" class="p-6">
                    <h2 class="text-lg font-semibold text-slate-900">Sync center names?</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Update center display names in demo to match exam for centers matched by a unique code? No accounts or passwords will be copied. Exam remains read-only.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="submit" value="cancel" class="btn btn-light">Cancel</button>
                        <button type="submit" value="confirm" class="btn btn-primary">Continue sync</button>
                    </div>
                </form>
            </dialog>

            <dialog id="db-sync-import-confirm" class="rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/50" style="width:40vw; min-width:min(90vw, 22rem); max-width:calc(100vw - 2rem)">
                <form method="dialog" class="p-6">
                    <h2 class="text-lg font-semibold text-slate-900">Import missing students?</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Add missing student registrations to the configured demo database? Existing demo rows will be left unchanged. Exam remains read-only. Photos sync separately; user accounts and marks are not copied.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="submit" value="cancel" class="btn btn-light">Cancel</button>
                        <button type="submit" value="confirm" class="btn btn-primary">Continue import</button>
                    </div>
                </form>
            </dialog>

            <dialog id="db-sync-photos-confirm" class="rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/50" style="width:40vw; min-width:min(90vw, 22rem); max-width:calc(100vw - 2rem)">
                <form method="dialog" class="p-6">
                    <h2 class="text-lg font-semibold text-slate-900">Start photo sync?</h2>
                    <p id="db-sync-photos-confirm-details" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-slate-700" role="status">Each attempt starts at the beginning; it does not resume previous progress.</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Copy available exam photos from the current session into demo for matching registration numbers. Photos will be center-cropped and resized to 60 × 70 px, replacing the current demo photo. Students outside the current session and students without a usable exam photo will be left unchanged. Exam remains read-only.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="submit" value="cancel" class="btn btn-light">Cancel</button>
                        <button type="submit" value="confirm" class="btn btn-primary">Continue photo sync</button>
                    </div>
                </form>
            </dialog>

            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-800">Process</h2>
                <ol class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <li class="rounded-lg border border-emerald-200 p-3"><span class="text-xs font-bold text-emerald-700">1 · Compare</span><p class="mt-1 text-sm text-slate-600">Check exam structure against demo. This step is read-only.</p></li>
                    <li class="rounded-lg border border-emerald-200 p-3"><span class="text-xs font-bold text-emerald-700">2 · Review</span><p class="mt-1 text-sm text-slate-600">A developer writes a small migration for the intended change. The tool does not guess SQL from differences.</p></li>
                    <li class="rounded-lg border border-emerald-200 p-3"><span class="text-xs font-bold text-emerald-700">3 · Back up & apply</span><p class="mt-1 text-sm text-slate-600">Back up demo, then an authorized admin can apply the pending migration.</p></li>
                    <li class="rounded-lg border border-emerald-200 p-3"><span class="text-xs font-bold text-emerald-700">4 · Verify</span><p class="mt-1 text-sm text-slate-600">Recheck the schema and confirm demo works. Exam remains unchanged.</p></li>
                </ol>
            </section>

            <div class="flex flex-wrap gap-2">
                <button id="db-sync-compare" type="button" class="btn btn-light" disabled>Compare table structure</button>
                <button id="db-sync-apply" type="button" class="btn btn-primary hidden">Apply pending demo migrations</button>
            </div>
            <div id="db-sync-feedback" class="hidden rounded-lg border p-3 text-sm" role="status" aria-live="polite"></div>
            <div id="db-sync-results" class="hidden overflow-x-auto"></div>
            <p class="text-xs leading-5 text-slate-500">“Automatic” means the deployment/apply process runs version-controlled migrations. It does not continuously copy the exam database. This page applies migrations only after an admin clicks the button.</p>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const badge = document.getElementById('db-sync-badge');
    const setup = document.getElementById('db-sync-setup');
    const missingList = document.getElementById('db-sync-missing');
    const compareButton = document.getElementById('db-sync-compare');
    const applyButton = document.getElementById('db-sync-apply');
    const feedback = document.getElementById('db-sync-feedback');
    const results = document.getElementById('db-sync-results');
    const refreshStudents = document.getElementById('db-sync-refresh-students');
    const previewImportButton = document.getElementById('db-sync-preview-import');
    const startImportButton = document.getElementById('db-sync-start-import');
    const importPreview = document.getElementById('db-sync-import-preview');
    const importProgress = document.getElementById('db-sync-import-progress');
    const importConfirmDialog = document.getElementById('db-sync-import-confirm');
    const syncPhotosButton = document.getElementById('db-sync-start-photos');
    const refreshPhotoCountsButton = document.getElementById('db-sync-refresh-photo-counts');
    const photoSummary = document.getElementById('db-sync-photo-summary');
    const photoProgress = document.getElementById('db-sync-photo-progress');
    const photoHistory = document.getElementById('db-sync-photo-history');
    const photoRunDetails = document.getElementById('db-sync-photo-run-details');
    const refreshPhotoHistoryButton = document.getElementById('db-sync-refresh-photo-history');
    const photosConfirmDialog = document.getElementById('db-sync-photos-confirm');
    const photosConfirmDetails = document.getElementById('db-sync-photos-confirm-details');
    const previewCentersButton = document.getElementById('db-sync-preview-centers');
    const syncCentersButton = document.getElementById('db-sync-start-centers');
    const centersPreview = document.getElementById('db-sync-centers-preview');
    const centersConfirmDialog = document.getElementById('db-sync-centers-confirm');
    const previewReferencesButton = document.getElementById('db-sync-preview-references');
    const syncReferencesButton = document.getElementById('db-sync-start-references');
    const referencesPreview = document.getElementById('db-sync-references-preview');
    const referencesConfirmDialog = document.getElementById('db-sync-references-confirm');
    const studentsStatus = document.getElementById('db-sync-students-status');
    const studentsTable = document.getElementById('db-sync-students-table');
    let state = null;
    let photoSession = 0;

    function message(text, error) {
        feedback.textContent = text;
        feedback.className = 'rounded-lg border p-3 text-sm ' + (error ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900');
        feedback.classList.remove('hidden');
    }

    function confirmStudentImport() {
        return new Promise(function (resolve) {
            importConfirmDialog.returnValue = '';
            importConfirmDialog.addEventListener('close', function () {
                resolve(importConfirmDialog.returnValue === 'confirm');
            }, { once: true });
            importConfirmDialog.showModal();
        });
    }

    async function loadStatus() {
        try {
            state = await BK.get('/api/database-sync/status');
            photoSession = Number(state.active_session || 0);
            document.getElementById('db-sync-source').textContent = state.source.host ? state.source.host + ' / ' + state.source.database + ' · read-only' : 'Source connection not configured';
            document.getElementById('db-sync-target').textContent = state.target.host ? state.target.host + ' / ' + state.target.database + ' · dedicated demo connection' : 'Demo target is not explicitly confirmed';
            if (state.ready) {
                badge.textContent = 'Connections configured';
                badge.className = 'rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800';
                compareButton.disabled = false;
                refreshStudents.disabled = false;
                previewImportButton.disabled = false;
                previewCentersButton.disabled = false;
                previewReferencesButton.disabled = false;
                syncPhotosButton.disabled = photoSession <= 0;
                refreshPhotoCountsButton.disabled = photoSession <= 0;
                applyButton.classList.toggle('hidden', !state.pending_migrations.length);
                message('Ready. Comparing the exam connection reads schema metadata only.', false);
                await loadLatestStudents();
                previewPhotoCounts();
                loadPhotoSyncHistory();
            } else {
                badge.textContent = 'Setup required';
                badge.className = 'rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800';
                setup.classList.remove('hidden');
                state.missing.forEach(function (item) {
                    const li = document.createElement('li');
                    li.textContent = item;
                    missingList.appendChild(li);
                });
                message('No database connection was opened. Configure the private demo-server settings to enable this workflow.', true);
            }
        } catch (error) {
            badge.textContent = 'Could not check setup';
            message(error.message || 'Could not read database sync setup.', true);
        }
    }

    async function loadLatestStudents() {
        refreshStudents.disabled = true;
        studentsStatus.textContent = 'Loading latest registrations from exam…';
        studentsTable.replaceChildren();
        studentsTable.classList.add('hidden');
        try {
            const data = await BK.get('/api/database-sync/latest-students?limit=20');
            if (data.error) { studentsStatus.textContent = data.error; return; }
            const rows = data.students || [];
            if (!rows.length) { studentsStatus.textContent = 'No student registrations were found on exam.'; return; }
            const table = document.createElement('table');
            table.className = 'table w-full';
            table.innerHTML = '<thead><tr><th>Submitted</th><th>Registration</th><th>Student</th><th>Class</th><th>Session</th><th>Center</th><th>Institute</th><th>Status</th></tr></thead>';
            const tbody = document.createElement('tbody');
            rows.forEach(function (row) {
                const tr = document.createElement('tr');
                const submitted = row.created_at ? new Date(String(row.created_at).replace(' ', 'T')) : null;
                const values = [
                    submitted && !Number.isNaN(submitted.getTime()) ? submitted.toLocaleString() : (row.created_at || '—'),
                    row.registration || '—', row.name || '—', row.class || '—', row.session || '—',
                    row.center_name || '—', row.institute_name || '—', Number(row.active) === 1 ? 'Active' : 'Pending'
                ];
                values.forEach(function (value) {
                    const td = document.createElement('td');
                    td.textContent = String(value);
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            studentsTable.appendChild(table);
            studentsTable.classList.remove('hidden');
            studentsStatus.textContent = 'Showing the newest ' + rows.length + ' student registration(s) currently stored on exam. This preview does not change either database.';
        } catch (error) {
            studentsStatus.textContent = error.message || 'Could not load latest exam registrations.';
        } finally {
            refreshStudents.disabled = false;
        }
    }

    refreshStudents.addEventListener('click', loadLatestStudents);

    async function previewPhotoCounts() {
        if (photoSession <= 0) return;
        photoSummary.classList.remove('hidden');
        photoSummary.textContent = 'Counting current-session student photos…';
        try {
            const data = await BK.get('/api/database-sync/student-photo-preview');
            if (data.error) { photoSummary.textContent = data.error; return; }
            photoSummary.textContent = 'Session ' + data.session + ': ' + Number(data.source_photos || 0) + ' exam student(s) have a photo; ' + Number(data.uploaded || 0) + ' already synced, ' + Number(data.pending || 0) + ' pending sync, ' + Number(data.missing_demo || 0) + ' have no unique demo match, and ' + Number(data.without_registration || 0) + ' have no registration number. ' + (data.note || '');
        } catch (error) {
            photoSummary.textContent = error.message || 'Could not count current-session student photos.';
        }
    }

    refreshPhotoCountsButton.addEventListener('click', previewPhotoCounts);
    refreshPhotoHistoryButton.addEventListener('click', loadPhotoSyncHistory);

    function formatDhakaDateTime(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/.exec(String(value));
        if (!match) return value;
        const date = new Date(match[1] + '-' + match[2] + '-' + match[3] + 'T' + match[4] + ':' + match[5] + ':' + match[6] + '+06:00');
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString(undefined, {timeZone: 'Asia/Dhaka'});
    }

    async function loadPhotoSyncHistory() {
        photoHistory.textContent = 'Loading sync history…';
        try {
            const data = await BK.get('/api/database-sync/student-photo-history');
            if (data.error) { photoHistory.textContent = data.error; return; }
            if (data.requires_migration) {
                photoHistory.textContent = 'Apply the pending student-photo sync history migration using the migration controls above. Sync runs will be recorded after it is applied.';
                return;
            }
            const runs = data.runs || [];
            if (!runs.length) { photoHistory.textContent = 'No student-photo sync runs have been recorded yet.'; return; }
            const columns = [
                ['Started (GMT+6)', 'started_at'], ['Session', 'session'], ['Status', 'status'],
                ['Checked', 'checked_count'], ['Copied', 'copied_count'], ['Already synced', 'already_synced_count'],
                ['No demo match', 'missing_demo_count'], ['Unavailable', 'unavailable_count']
            ];
            const table = document.createElement('table');
            table.className = 'min-w-full border-collapse text-left';
            const thead = document.createElement('thead');
            const headRow = document.createElement('tr');
            columns.forEach(function (column) {
                const th = document.createElement('th');
                th.className = 'border-b px-3 py-2 font-semibold text-slate-700';
                th.textContent = column[0];
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            table.appendChild(thead);
            const tbody = document.createElement('tbody');
            runs.forEach(function (run) {
                const row = document.createElement('tr');
                columns.forEach(function (column) {
                    const td = document.createElement('td');
                    td.className = 'border-b px-3 py-2';
                    let value = run[column[1]];
                    if (column[1] === 'started_at' && value) {
                        value = formatDhakaDateTime(value);
                    }
                    td.textContent = value == null ? '—' : String(value);
                    row.appendChild(td);
                });
                const action = document.createElement('td');
                action.className = 'border-b px-3 py-2';
                if (Number(run.unavailable_count || 0) > 0 || run.status === 'interrupted') {
                    const review = document.createElement('button');
                    review.type = 'button';
                    review.className = 'btn btn-light whitespace-nowrap';
                    review.textContent = 'Review students';
                    review.dataset.reviewPhotoRun = run.run_id;
                    action.appendChild(review);
                } else {
                    action.textContent = '—';
                }
                row.appendChild(action);
                tbody.appendChild(row);
                if (run.failure_message) {
                    const detailRow = document.createElement('tr');
                    const detail = document.createElement('td');
                    detail.colSpan = columns.length;
                    detail.className = 'border-b px-3 py-2 text-rose-700';
                    detail.textContent = 'Stopped: ' + run.failure_message;
                    detailRow.appendChild(detail);
                    tbody.appendChild(detailRow);
                }
            });
            table.appendChild(tbody);
            photoHistory.replaceChildren(table);
            photoHistory.querySelectorAll('[data-review-photo-run]').forEach(function (button) {
                button.addEventListener('click', function () { reviewPhotoSyncRun(button.dataset.reviewPhotoRun); });
            });
        } catch (error) {
            photoHistory.textContent = error.message || 'Could not load sync history.';
        }
    }

    async function reviewPhotoSyncRun(runId) {
        photoRunDetails.classList.remove('hidden');
        photoRunDetails.textContent = 'Comparing processed students with current demo photos…';
        try {
            const data = await BK.get('/api/database-sync/student-photo-run-details?run_id=' + encodeURIComponent(runId));
            if (data.error) { photoRunDetails.textContent = data.error; return; }
            const candidates = data.candidates || [];
            photoRunDetails.replaceChildren();
            const heading = document.createElement('div');
            heading.className = 'mb-2 flex flex-wrap items-center justify-between gap-3';
            const title = document.createElement('p');
            title.className = 'font-semibold text-slate-800';
            title.textContent = candidates.length + ' processed student(s) currently need photo attention. This list is reconstructed from exam records and demo photo paths; the original run did not save per-student failure reasons.';
            heading.appendChild(title);
            photoRunDetails.appendChild(heading);
            if (!candidates.length) {
                const empty = document.createElement('p');
                empty.textContent = 'All processed students currently have their synced photo file in demo.';
                photoRunDetails.appendChild(empty);
                return;
            }
            const exportControls = document.createElement('div');
            exportControls.className = 'mb-3 flex flex-wrap items-end gap-2';
            const centerSelect = createPhotoExportSelect('Center', 'All centers', candidates, 'center_id', 'center');
            const instituteSelect = createPhotoExportSelect('Institute', 'All institutes', candidates, 'institute_id', 'institute');
            const recipientLabel = document.createElement('label');
            recipientLabel.className = 'flex flex-col gap-1 text-xs font-medium text-slate-700';
            recipientLabel.style.minWidth = '16rem';
            recipientLabel.textContent = 'Authority email';
            const recipientInput = document.createElement('input');
            recipientInput.type = 'email';
            recipientInput.required = true;
            recipientInput.autocomplete = 'email';
            recipientInput.placeholder = 'authority@example.com';
            recipientInput.className = 'rounded border border-slate-300 bg-white px-3 py-2 text-sm font-normal';
            recipientLabel.appendChild(recipientInput);
            const exportButton = document.createElement('button');
            exportButton.type = 'button';
            exportButton.className = 'btn btn-primary whitespace-nowrap';
            const emailButton = document.createElement('button');
            emailButton.type = 'button';
            emailButton.className = 'btn btn-light whitespace-nowrap';
            const updateExportLabel = function () {
                const count = filterPhotoAttentionCandidates(candidates, centerSelect.value, instituteSelect.value).length;
                exportButton.textContent = 'Download CSV (' + count + ')';
                exportButton.disabled = count === 0;
                emailButton.textContent = 'Email CSV (' + count + ')';
                emailButton.disabled = count === 0;
            };
            centerSelect.addEventListener('change', updateExportLabel);
            instituteSelect.addEventListener('change', updateExportLabel);
            exportButton.addEventListener('click', function () {
                downloadPhotoAttentionCsv(data.run, filterPhotoAttentionCandidates(candidates, centerSelect.value, instituteSelect.value));
            });
            emailButton.addEventListener('click', async function () {
                if (!recipientInput.reportValidity()) { return; }
                emailButton.disabled = true;
                emailButton.textContent = 'Sending…';
                try {
                    const result = await BK.post('/api/database-sync/email-photo-attention-csv', {
                        run_id: runId,
                        session: Number(data.run.session),
                        center_id: centerSelect.value === '' ? null : Number(centerSelect.value),
                        institute_id: instituteSelect.value === '' ? null : Number(instituteSelect.value),
                        recipient: recipientInput.value.trim()
                    });
                    if (result.error) { throw new Error(result.error); }
                    BK.toast('Emailed the CSV with ' + Number(result.count || 0).toLocaleString() + ' student(s) to ' + result.recipient + '.', 'success');
                } catch (error) {
                    BK.toast(error.message || 'Could not email the CSV report.', 'error');
                } finally {
                    updateExportLabel();
                }
            });
            updateExportLabel();
            exportControls.appendChild(centerSelect.parentElement);
            exportControls.appendChild(instituteSelect.parentElement);
            exportControls.appendChild(recipientLabel);
            exportControls.appendChild(exportButton);
            exportControls.appendChild(emailButton);
            photoRunDetails.appendChild(exportControls);
            const mailHint = document.createElement('p');
            mailHint.className = 'mb-3 text-xs text-slate-500';
            mailHint.textContent = 'Email sends from reports@bkabd.com. Configure its SMTP password in demo’s private .env; never put it in this page or Git.';
            photoRunDetails.appendChild(mailHint);
            const columns = ['Registration', 'Student', 'Class', 'Center', 'Institute', 'Current status', 'Exam photo file', 'Exam photo'];
            const table = document.createElement('table');
            table.className = 'min-w-full border-collapse text-left';
            const head = document.createElement('thead');
            const headRow = document.createElement('tr');
            columns.forEach(function (label) {
                const th = document.createElement('th');
                th.className = 'border-b px-3 py-2 font-semibold text-slate-700';
                th.textContent = label;
                headRow.appendChild(th);
            });
            head.appendChild(headRow);
            table.appendChild(head);
            const body = document.createElement('tbody');
            candidates.forEach(function (student) {
                const row = document.createElement('tr');
                [student.registration, student.name, student.class, student.center, student.institute, student.reason, student.exam_photo_file].forEach(function (value) {
                    const td = document.createElement('td');
                    td.className = 'border-b px-3 py-2';
                    td.textContent = value || '—';
                    row.appendChild(td);
                });
                const photoCell = document.createElement('td');
                photoCell.className = 'border-b px-3 py-2';
                if (student.source_photo_url) {
                    const link = document.createElement('a');
                    link.href = student.source_photo_url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = 'View / save photo';
                    link.className = 'text-sky-700 underline';
                    photoCell.appendChild(link);
                } else {
                    photoCell.textContent = 'Source path unavailable';
                }
                row.appendChild(photoCell);
                body.appendChild(row);
            });
            table.appendChild(body);
            const helper = document.createElement('p');
            helper.className = 'mb-2 text-slate-600';
            helper.textContent = 'Use the registration number to locate a student in demo. “View / save photo” opens the current exam image in a new tab; you can save it and upload it from that student’s edit page if needed.';
            photoRunDetails.appendChild(helper);
            photoRunDetails.appendChild(table);
        } catch (error) {
            photoRunDetails.textContent = error.message || 'Could not load student details for this sync.';
        }
    }

    function createPhotoExportSelect(label, allLabel, candidates, idKey, nameKey) {
        const wrapper = document.createElement('label');
        wrapper.className = 'flex flex-col gap-1 text-xs font-medium text-slate-700';
        wrapper.style.minWidth = '12rem';
        wrapper.textContent = label;
        const select = document.createElement('select');
        select.className = 'rounded border border-slate-300 bg-white px-3 py-2 text-sm font-normal';
        select.setAttribute('aria-label', label + ' filter for CSV export');
        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = allLabel;
        select.appendChild(allOption);
        const choices = new Map();
        candidates.forEach(function (student) {
            const id = Number(student[idKey] || 0);
            const key = String(id);
            if (!choices.has(key)) {
                choices.set(key, String(student[nameKey] || (id ? label + ' #' + id : 'Unassigned')));
            }
        });
        const nameCounts = new Map();
        choices.forEach(function (name) {
            const key = name.toLocaleLowerCase();
            nameCounts.set(key, (nameCounts.get(key) || 0) + 1);
        });
        Array.from(choices.entries()).sort(function (a, b) {
            return a[1].localeCompare(b[1]) || Number(a[0]) - Number(b[0]);
        }).forEach(function (choice) {
            const option = document.createElement('option');
            option.value = choice[0];
            option.textContent = nameCounts.get(choice[1].toLocaleLowerCase()) > 1 ? choice[1] + ' (#' + choice[0] + ')' : choice[1];
            select.appendChild(option);
        });
        wrapper.appendChild(select);
        return select;
    }

    function filterPhotoAttentionCandidates(candidates, centerId, instituteId) {
        return candidates.filter(function (student) {
            return (!centerId || String(Number(student.center_id || 0)) === centerId)
                && (!instituteId || String(Number(student.institute_id || 0)) === instituteId);
        });
    }

    function downloadPhotoAttentionCsv(run, candidates) {
        const rows = candidates.slice().sort(function (a, b) {
            return String(a.center || '').localeCompare(String(b.center || ''))
                || String(a.institute || '').localeCompare(String(b.institute || ''))
                || String(a.name || '').localeCompare(String(b.name || ''))
                || String(a.registration || '').localeCompare(String(b.registration || ''));
        });
        const csvRows = [
            ['Session', 'Registration', 'Student', 'Class', 'Center', 'Institute', 'Current status', 'Exam photo file'],
            ...rows.map(function (student) {
                return [run.session, student.registration, student.name, student.class, student.center, student.institute, student.reason, student.exam_photo_file];
            })
        ];
        const csv = '\uFEFF' + csvRows.map(function (row) {
            return row.map(function (value) {
                const text = value == null ? '' : String(value);
                return '"' + text.replace(/"/g, '""') + '"';
            }).join(',');
        }).join('\r\n');
        const blobUrl = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = 'student-photo-attention-session-' + Number(run.session || 0) + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(blobUrl);
    }

    async function confirmPhotoSync() {
        photosConfirmDetails.textContent = 'Already synced photo files are skipped. If a previous sync was interrupted, you can continue from its saved checkpoint.';
        let resumable = null;
        try {
            const history = await BK.get('/api/database-sync/student-photo-history');
            const runs = history && history.runs || [];
            resumable = runs.find(function (run) {
                return Number(run.session) === photoSession
                    && Number(run.last_source_id || 0) > 0
                    && ['running', 'interrupted', 'failed'].includes(String(run.status));
            }) || null;
            if (resumable) {
                const started = resumable.started_at ? formatDhakaDateTime(resumable.started_at) : 'the previous attempt';
                photosConfirmDetails.textContent = 'Continue the interrupted sync from its saved position (' + Number(resumable.checked_count || 0).toLocaleString() + ' source records checked since ' + started + '). Synced files will be skipped, and remaining photos will be retried.';
            } else if (runs.length && Number(runs[0].session) === photoSession && runs[0].status === 'completed') {
                photosConfirmDetails.textContent = 'The last sync completed. A new pass starts at the beginning to retry photos that were unavailable or missing, while valid photos already synced by this tool are skipped.';
            }
        } catch (error) {
            // The static note still explains that interrupted runs can resume.
        }
        return new Promise(function (resolve) {
            photosConfirmDialog.returnValue = '';
            photosConfirmDialog.addEventListener('close', function () {
                resolve(photosConfirmDialog.returnValue === 'confirm' ? resumable : false);
            }, { once: true });
            photosConfirmDialog.showModal();
        });
    }

    syncPhotosButton.addEventListener('click', async function () {
        const resumeRun = await confirmPhotoSync();
        if (resumeRun === false) return;
        syncPhotosButton.disabled = true;
        photoProgress.classList.remove('hidden');
        let afterId = resumeRun ? Number(resumeRun.last_source_id || 0) : 0;
        let checked = resumeRun ? Number(resumeRun.checked_count || 0) : 0;
        let updated = resumeRun ? Number(resumeRun.copied_count || 0) : 0;
        let alreadySynced = resumeRun ? Number(resumeRun.already_synced_count || 0) : 0;
        let missingDemo = resumeRun ? Number(resumeRun.missing_demo_count || 0) : 0;
        let unavailable = resumeRun ? Number(resumeRun.unavailable_count || 0) : 0;
        const runId = resumeRun ? resumeRun.run_id : ((window.crypto && typeof window.crypto.randomUUID === 'function')
            ? window.crypto.randomUUID()
            : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                const r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            }));
        try {
            while (true) {
                const data = await BK.post('/api/database-sync/sync-student-photos', { after_id: afterId, session: photoSession, run_id: runId });
                if (data.error) throw new Error(data.error);
                checked += Number(data.checked || 0);
                updated += Number(data.updated || 0);
                alreadySynced += Number(data.already_synced || 0);
                missingDemo += Number(data.missing_demo || 0);
                unavailable += Number(data.unavailable || 0);
                afterId = Number(data.next_after_id || afterId);
                photoProgress.textContent = 'Session ' + photoSession + ': checked ' + checked + ' exam photo record(s)… copied ' + updated + ', already synced ' + alreadySynced + ', no demo match ' + missingDemo + ', unavailable or invalid photo ' + unavailable + '.';
                if (data.done) break;
            }
            photoProgress.textContent = 'Session ' + photoSession + ' photo sync finished. Copied ' + updated + ' photo(s), skipped ' + alreadySynced + ' already synced, no demo match ' + missingDemo + ', unavailable or invalid ' + unavailable + '. Exam was read-only.';
            message('Student-photo sync complete. Updated ' + updated + ' demo student photo(s).', false);
            BK.toast('Copied ' + updated + ' student photo(s) to demo.', 'success');
        } catch (error) {
            photoProgress.textContent = 'Photo sync stopped after checking ' + checked + ' source record(s). ' + error.message + ' You can safely retry; valid photos already synced by this tool will be skipped.';
            message(error.message || 'Could not sync student photos.', true);
            BK.toast(error.message || 'Could not sync student photos.', 'error');
        } finally {
            syncPhotosButton.disabled = false;
            previewPhotoCounts();
            loadPhotoSyncHistory();
        }
    });

    function confirmCenterSync() {
        return new Promise(function (resolve) {
            centersConfirmDialog.returnValue = '';
            centersConfirmDialog.addEventListener('close', function () {
                resolve(centersConfirmDialog.returnValue === 'confirm');
            }, { once: true });
            centersConfirmDialog.showModal();
        });
    }

    async function previewCenterNames() {
        previewCentersButton.disabled = true;
        syncCentersButton.classList.add('hidden');
        centersPreview.classList.remove('hidden');
        centersPreview.textContent = 'Comparing center codes and names…';
        try {
            const data = await BK.get('/api/database-sync/center-name-preview');
            if (data.error) { centersPreview.textContent = data.error; return; }
            centersPreview.replaceChildren();
            const summary = document.createElement('p');
            summary.className = 'mb-3';
            summary.textContent = data.changes + ' center name(s) would change; ' + data.matched + ' unique code match(es), ' + data.ambiguous + ' ambiguous, ' + data.unmatched + ' unmatched. ' + (data.note || '');
            centersPreview.appendChild(summary);
            if ((data.examples || []).length) {
                const table = document.createElement('table');
                table.className = 'table w-full';
                const thead = document.createElement('thead');
                const header = document.createElement('tr');
                ['Center code', 'Current demo name', 'Exam name'].forEach(function (label) {
                    const th = document.createElement('th'); th.textContent = label; header.appendChild(th);
                });
                thead.appendChild(header); table.appendChild(thead);
                const tbody = document.createElement('tbody');
                data.examples.forEach(function (row) {
                    const tr = document.createElement('tr');
                    [row.code || '—', row.from || '—', row.to || '—'].forEach(function (value) {
                        const td = document.createElement('td'); td.textContent = String(value); tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
                table.appendChild(tbody); centersPreview.appendChild(table);
            }
            syncCentersButton.classList.toggle('hidden', Number(data.changes) < 1);
            syncCentersButton.disabled = Number(data.changes) < 1;
        } catch (error) {
            centersPreview.textContent = error.message || 'Could not preview center-name sync.';
        } finally {
            previewCentersButton.disabled = false;
        }
    }

    previewCentersButton.addEventListener('click', previewCenterNames);
    syncCentersButton.addEventListener('click', async function () {
        if (!(await confirmCenterSync())) return;
        syncCentersButton.disabled = true;
        previewCentersButton.disabled = true;
        try {
            const data = await BK.post('/api/database-sync/sync-center-names', {});
            if (data.error) throw new Error(data.error);
            message('Center-name sync complete. Updated ' + Number(data.updated || 0) + ' linked demo center name(s). Exam was read-only.', false);
            BK.toast('Updated ' + Number(data.updated || 0) + ' center name(s).', 'success');
            await previewCenterNames();
        } catch (error) {
            message(error.message || 'Could not sync center names.', true);
            BK.toast(error.message || 'Could not sync center names.', 'error');
        } finally {
            syncCentersButton.disabled = false;
            previewCentersButton.disabled = false;
        }
    });

    function confirmReferenceSync() {
        return new Promise(function (resolve) {
            referencesConfirmDialog.returnValue = '';
            referencesConfirmDialog.addEventListener('close', function () { resolve(referencesConfirmDialog.returnValue === 'confirm'); }, { once: true });
            referencesConfirmDialog.showModal();
        });
    }

    async function previewReferences() {
        previewReferencesButton.disabled = true;
        syncReferencesButton.classList.add('hidden');
        referencesPreview.classList.remove('hidden');
        referencesPreview.textContent = 'Comparing center and institute references…';
        try {
            const data = await BK.get('/api/database-sync/reference-preview');
            if (data.error) { referencesPreview.textContent = data.error; return; }
            referencesPreview.replaceChildren();
            const summary = document.createElement('p');
            summary.className = 'mb-3';
            summary.textContent = Number(data.centers || 0) + ' center(s), ' + Number(data.institutes || 0) + ' institute(s), and ' + Number(data.locations || 0) + ' location(s) would be added. Ambiguous centers: ' + Number(data.ambiguous_centers || 0) + '; ambiguous institutes: ' + Number(data.ambiguous_institutes || 0) + '; ambiguous locations: ' + Number(data.ambiguous_locations || 0) + '; unmatched locations: ' + Number(data.unmatched_locations || 0) + '; institutes without a safe center match: ' + Number(data.unmatched_institutes || 0) + '. ' + (data.note || '');
            referencesPreview.appendChild(summary);
            [['Centers to add', data.examples && data.examples.centers, ['code','name','venue']], ['Institutes to add', data.examples && data.examples.institutes, ['name','certificate_no','center']], ['Locations to add', Object.values(data.examples && data.examples.locations || {}).flat(), ['name']]].forEach(function (group) {
                const rows = group[1] || [];
                if (!rows.length) return;
                const heading = document.createElement('h3'); heading.className = 'mt-3 font-semibold'; heading.textContent = group[0]; referencesPreview.appendChild(heading);
                const table = document.createElement('table'); table.className = 'table w-full';
                const head = document.createElement('tr'); group[2].forEach(function (key) { const th=document.createElement('th'); th.textContent=key.replace('_',' '); head.appendChild(th); });
                const thead=document.createElement('thead'); thead.appendChild(head); table.appendChild(thead);
                const tbody=document.createElement('tbody'); rows.forEach(function (row) { const tr=document.createElement('tr'); group[2].forEach(function (key) { const td=document.createElement('td'); td.textContent=String(row[key] || '—'); tr.appendChild(td); }); tbody.appendChild(tr); });
                table.appendChild(tbody); referencesPreview.appendChild(table);
            });
            const count = Number(data.centers || 0) + Number(data.institutes || 0) + Number(data.locations || 0);
            syncReferencesButton.classList.toggle('hidden', count < 1);
            syncReferencesButton.disabled = count < 1;
        } catch (error) {
            referencesPreview.textContent = error.message || 'Could not preview reference sync.';
        } finally { previewReferencesButton.disabled = false; }
    }
    previewReferencesButton.addEventListener('click', previewReferences);
    syncReferencesButton.addEventListener('click', async function () {
        if (!(await confirmReferenceSync())) return;
        syncReferencesButton.disabled = true; previewReferencesButton.disabled = true;
        try {
            const data = await BK.post('/api/database-sync/sync-references', {});
            if (data.error) throw new Error(data.error);
            const text = 'Reference sync complete. Added ' + Number(data.centers_added || 0) + ' center(s), ' + Number(data.institutes_added || 0) + ' institute(s), and ' + Number(data.locations_added || 0) + ' location(s).';
            message(text + ' Exam was read-only.', false); BK.toast(text, 'success');
            await previewReferences();
        } catch (error) { message(error.message || 'Could not sync references.', true); BK.toast(error.message || 'Could not sync references.', 'error'); }
        finally { syncReferencesButton.disabled = false; previewReferencesButton.disabled = false; }
    });

    previewImportButton.addEventListener('click', async function () {
        previewImportButton.disabled = true;
        startImportButton.classList.add('hidden');
        importPreview.classList.remove('hidden');
        importPreview.textContent = 'Counting registrations on both databases…';
        importProgress.classList.add('hidden');
        try {
            const data = await BK.get('/api/database-sync/student-import-preview');
            if (data.error) { importPreview.textContent = data.error; return; }
            importPreview.textContent = 'Exam has ' + data.source_total + ' student record(s). ' + data.missing_in_demo + ' registration(s) are not in demo; ' + data.already_in_demo + ' are already present. ' + data.missing_registration + ' source record(s) have no registration number and will be skipped. ' + (data.note || '');
            startImportButton.classList.toggle('hidden', Number(data.missing_in_demo) < 1);
            startImportButton.disabled = Number(data.missing_in_demo) < 1;
        } catch (error) {
            importPreview.textContent = error.message || 'Could not preview the import.';
        } finally {
            previewImportButton.disabled = false;
        }
    });

    function showImportResult(summary, skippedDetails, alreadyPresentDetails) {
        importPreview.replaceChildren();
        const paragraph = document.createElement('p');
        paragraph.textContent = summary;
        importPreview.appendChild(paragraph);
        function appendStudentDetails(title, students) {
            if (!students.length) return;
            const heading = document.createElement('h3');
            heading.className = 'mt-3 font-semibold text-slate-800';
            heading.textContent = title + ' (' + students.length + ')';
            importPreview.appendChild(heading);
            const wrapper = document.createElement('div');
            wrapper.className = 'mt-2 overflow-x-auto';
            const table = document.createElement('table');
            table.className = 'min-w-full border-collapse text-left text-xs';
            const columns = [
                ['Registration', 'registration'], ['Student', 'name'], ['Class / session', null],
                ['Roll', 'roll'], ['Exam center', 'center'], ['Exam institute', 'institute'], ['Reason', 'reason']
            ];
            const thead = document.createElement('thead');
            const headRow = document.createElement('tr');
            columns.forEach(function (column) {
                const th = document.createElement('th');
                th.className = 'border-b px-2 py-2 font-semibold';
                th.textContent = column[0];
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            table.appendChild(thead);
            const tbody = document.createElement('tbody');
            students.forEach(function (student) {
                const row = document.createElement('tr');
                columns.forEach(function (column) {
                    const td = document.createElement('td');
                    td.className = 'border-b px-2 py-2 align-top';
                    td.textContent = column[1] === null
                        ? String(student.class || '—') + ' / ' + String(student.session || '—')
                        : (student[column[1]] || '—');
                    row.appendChild(td);
                });
                tbody.appendChild(row);
            });
            table.appendChild(tbody);
            wrapper.appendChild(table);
            importPreview.appendChild(wrapper);
        }
        appendStudentDetails('Already present student details', alreadyPresentDetails || []);
        appendStudentDetails('Skipped student details', skippedDetails || []);
    }

    startImportButton.addEventListener('click', async function () {
        if (!(await confirmStudentImport())) return;
        startImportButton.disabled = true;
        previewImportButton.disabled = true;
        importProgress.classList.remove('hidden');
        let afterId = 0;
        let totalImported = 0;
        let totalExisting = 0;
        let totalSkipped = 0;
        let processed = 0;
        const skippedDetails = [];
        const alreadyPresentDetails = [];
        try {
            while (true) {
                const data = await BK.post('/api/database-sync/import-students', { after_id: afterId });
                if (data.error) throw new Error(data.error);
                totalImported += Number(data.imported || 0);
                totalExisting += Number(data.already_present || 0);
                totalSkipped += Number(data.skipped || 0);
                skippedDetails.push(...(data.skipped_details || []));
                alreadyPresentDetails.push(...(data.already_present_details || []));
                processed += Number(data.processed || 0);
                afterId = Number(data.next_after_id || afterId);
                importProgress.textContent = 'Checked ' + processed + ' missing registration candidate(s)… Added ' + totalImported + ', already present ' + totalExisting + ', skipped ' + totalSkipped + '.';
                if (data.done) break;
            }
            showImportResult('Import finished. Added ' + totalImported + ' student(s) to demo; ' + totalExisting + ' already existed; ' + totalSkipped + ' were skipped because registration or reference data did not match safely. Reload the Students page on demo to see its updated count.', skippedDetails, alreadyPresentDetails);
            BK.toast('Import finished. Added ' + totalImported + ' student(s) to demo.', 'success');
            message('Import complete. Only the demo database received new student rows; exam was read-only.', false);
            startImportButton.classList.add('hidden');
            await loadLatestStudents();
        } catch (error) {
            importProgress.textContent = 'Import stopped after checking ' + processed + ' missing candidate(s). ' + error.message + ' You can preview again; already imported rows will be skipped on retry.';
            if (skippedDetails.length || alreadyPresentDetails.length) showImportResult('Import stopped after checking ' + processed + ' missing candidate(s). Details below list students already present or skipped in completed batches; already imported rows will be skipped on retry.', skippedDetails, alreadyPresentDetails);
            BK.toast('Import stopped after checking ' + processed + ' missing candidate(s): ' + error.message, 'error');
        } finally {
            startImportButton.disabled = false;
            previewImportButton.disabled = false;
        }
    });

    compareButton.addEventListener('click', async function () {
        compareButton.disabled = true;
        results.classList.add('hidden');
        try {
            const data = await BK.post('/api/database-sync/compare', {});
            if (data.error) { message(data.error, true); return; }
            const rows = data.differences || [];
            results.replaceChildren();
            const ignored = data.ignored || [];
            if (ignored.length) {
                const skipped = document.createElement('p');
                skipped.className = 'mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600';
                skipped.textContent = 'Skipped ' + ignored.length + ' configured difference(s): ' + ignored.map(function (row) {
                    return row.table + ' — ' + row.detail;
                }).join('; ');
                results.appendChild(skipped);
            }
            if (!rows.length) {
                const p = document.createElement('p');
                p.className = 'rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900';
                p.textContent = ignored.length ? 'No other exam-side table, column, or index differences found in demo.' : 'No exam-side table, column, or index differences found in demo.';
                results.appendChild(p);
            } else {
                const table = document.createElement('table');
                table.className = 'table w-full';
                table.innerHTML = '<thead><tr><th>Difference</th><th>Table</th><th>Details</th></tr></thead>';
                const tbody = document.createElement('tbody');
                rows.forEach(function (row) {
                    const tr = document.createElement('tr');
                    [row.kind.replaceAll('_', ' '), row.table, row.detail].forEach(function (value) {
                        const td = document.createElement('td'); td.textContent = value; tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
                table.appendChild(tbody);
                results.appendChild(table);
            }
            results.classList.remove('hidden');
            message(data.note || 'Comparison complete. No database was changed.', false);
        } catch (error) {
            message(error.message || 'Could not compare table structure.', true);
        } finally {
            compareButton.disabled = false;
        }
    });

    applyButton.addEventListener('click', async function () {
        const names = (state.pending_migrations || []).join(', ');
        if (!window.confirm('Apply these reviewed schema migrations to the configured demo database only?\n\n' + names + '\n\nExam database will not be modified. Back up demo first.')) return;
        applyButton.disabled = true;
        try {
            const data = await BK.post('/api/database-sync/apply', {});
            if (data.error) { message(data.error, true); return; }
            message(data.message || 'Demo migrations complete.', false);
            await loadStatus();
        } catch (error) {
            message(error.message || 'Could not apply demo migrations.', true);
        } finally {
            applyButton.disabled = false;
        }
    });

    loadStatus();
});
</script>
