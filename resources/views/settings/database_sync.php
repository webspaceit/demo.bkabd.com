<div class="space-y-4" id="database-sync-page">
    <div class="card">
        <div class="card-header flex flex-wrap items-center justify-between gap-3">
            <span>Database structure sync</span>
            <span id="db-sync-badge" class="rounded-full border border-slate-300 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">Checking setup…</span>
        </div>
        <div class="card-body space-y-5">
            <p class="text-sm leading-6 text-slate-600">
                This page compares table structures and can import missing student registrations from <strong>exam.bkabd.com</strong> into <strong>demo.bkabd.com</strong>.
                The exam connection is read-only. Imports add rows only to demo; existing demo records are not changed.
            </p>

            <div id="db-sync-setup" class="hidden rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                <strong>Server setup is required before this can connect.</strong>
                <p class="mt-1">Add the settings below to the private <code>.env</code> file used by the app you are running (your local PC, in this case). Do not paste passwords here or commit them to Git.</p>
                <ul id="db-sync-missing" class="mt-2 list-disc pl-5"></ul>
                <p class="mt-2">The sync uses separate database connections, so your local app can keep its current <code>DB_*</code> settings. Set the <code>SYNC_SOURCE_*</code> connection to exam with a read-only user. Set <code>SYNC_TARGET_*</code> to demo with a user that can read demo and add student rows, and has no access to exam.</p>
                <p class="mt-2">Set <code>DB_SYNC_ENABLED=true</code> and <code>DB_SYNC_ALLOW_LOCAL=true</code> in your local private configuration only after checking the target database name. Your host must allow remote MySQL connections from your PC to both databases.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <h2 class="font-semibold text-emerald-900">Exam: read-only reference</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Use the database server host provided by your hosting provider. <code>localhost</code> works only if the exam database is on the same PC as this app. Schema comparison reads metadata; student preview and import read source rows through a read-only transaction.</p>
                    <p class="mt-2 text-xs text-slate-500">Exam needs read-only SELECT access to students, centers, institutes, divisions, districts, upozilas, and unions, plus schema metadata. This app never inserts, updates, or deletes exam data.</p>
                    <p id="db-sync-source" class="mt-2 text-xs text-slate-500"></p>
                </section>
                <section class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                    <h2 class="font-semibold text-sky-900">Demo: migration destination</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Imports use a separate demo connection. The demo account needs SELECT on student/reference tables and INSERT on <code>students</code> (and <code>student_duplicate_keys</code> when present). It does not need access to exam. Schema migrations remain separate.</p>
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
                        <p class="mt-1 text-sm leading-6 text-slate-700">Preview first. The import adds registrations missing from demo, skips duplicate registrations and unsafe center/institute/location or roll conflicts, and leaves existing demo rows untouched. Photos, user accounts, and marks are not copied.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button id="db-sync-preview-import" type="button" class="btn btn-light" disabled>Preview import</button>
                        <button id="db-sync-start-import" type="button" class="btn btn-primary hidden" disabled>Import missing students</button>
                    </div>
                </div>
                <div id="db-sync-import-preview" class="mt-3 hidden rounded-lg border border-amber-200 bg-white p-3 text-sm text-slate-700" aria-live="polite"></div>
                <div id="db-sync-import-progress" class="mt-2 hidden text-sm text-slate-600" aria-live="polite"></div>
            </section>

            <dialog id="db-sync-import-confirm" class="rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/50" style="width:40vw; min-width:min(90vw, 22rem); max-width:calc(100vw - 2rem)">
                <form method="dialog" class="p-6">
                    <h2 class="text-lg font-semibold text-slate-900">Import missing students?</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Add missing student registrations to the configured demo database? Existing demo rows will be left unchanged. Exam remains read-only. Photos, user accounts, and marks will not be copied.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="submit" value="cancel" class="btn btn-light">Cancel</button>
                        <button type="submit" value="confirm" class="btn btn-primary">Continue import</button>
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
    const studentsStatus = document.getElementById('db-sync-students-status');
    const studentsTable = document.getElementById('db-sync-students-table');
    let state = null;

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
            document.getElementById('db-sync-source').textContent = state.source.host ? state.source.host + ' / ' + state.source.database + ' · read-only' : 'Source connection not configured';
            document.getElementById('db-sync-target').textContent = state.target.host ? state.target.host + ' / ' + state.target.database + ' · dedicated demo connection' : 'Demo target is not explicitly confirmed';
            if (state.ready) {
                badge.textContent = 'Connections configured';
                badge.className = 'rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800';
                compareButton.disabled = false;
                refreshStudents.disabled = false;
                previewImportButton.disabled = false;
                applyButton.classList.toggle('hidden', !state.pending_migrations.length);
                message('Ready. Comparing the exam connection reads schema metadata only.', false);
                await loadLatestStudents();
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
        try {
            while (true) {
                const data = await BK.post('/api/database-sync/import-students', { after_id: afterId });
                if (data.error) throw new Error(data.error);
                totalImported += Number(data.imported || 0);
                totalExisting += Number(data.already_present || 0);
                totalSkipped += Number(data.skipped || 0);
                processed += Number(data.processed || 0);
                afterId = Number(data.next_after_id || afterId);
                importProgress.textContent = 'Processed ' + processed + ' source record(s)… Added ' + totalImported + ', already present ' + totalExisting + ', skipped ' + totalSkipped + '.';
                if (data.done) break;
            }
            importPreview.textContent = 'Import finished. Added ' + totalImported + ' student(s) to demo; ' + totalExisting + ' already existed; ' + totalSkipped + ' were skipped because registration or reference data did not match safely. Reload the Students page on demo to see its updated count.';
            BK.toast('Import finished. Added ' + totalImported + ' student(s) to demo.', 'success');
            message('Import complete. Only the demo database received new student rows; exam was read-only.', false);
            startImportButton.classList.add('hidden');
            await loadLatestStudents();
        } catch (error) {
            importProgress.textContent = 'Import stopped after processing ' + processed + ' source record(s). ' + error.message + ' You can preview again; already imported rows will be skipped on retry.';
            BK.toast('Import stopped after ' + processed + ' source record(s): ' + error.message, 'error');
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
