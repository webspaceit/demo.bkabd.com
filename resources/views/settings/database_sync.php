<div class="space-y-4" id="database-sync-page">
    <div class="card">
        <div class="card-header flex flex-wrap items-center justify-between gap-3">
            <span>Database structure sync</span>
            <span id="db-sync-badge" class="rounded-full border border-slate-300 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">Checking setup…</span>
        </div>
        <div class="card-body space-y-5">
            <p class="text-sm leading-6 text-slate-600">
                This is a one-way workflow: <strong>exam.bkabd.com</strong> is only inspected for table structure, and reviewed changes are applied to
                <strong>demo.bkabd.com</strong>. No student rows or other application records are copied. The app never sends a write query to the exam connection.
            </p>

            <div id="db-sync-setup" class="hidden rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                <strong>Server setup is required before this can connect.</strong>
                <p class="mt-1">Add the settings below to the private <code>.env</code> file used by the app you are running (your local PC, in this case). Do not paste passwords here or commit them to Git.</p>
                <ul id="db-sync-missing" class="mt-2 list-disc pl-5"></ul>
                <p class="mt-2">The sync uses separate database connections, so your local app can keep its current <code>DB_*</code> settings. Set the <code>SYNC_SOURCE_*</code> connection to exam with a read-only user. Set <code>SYNC_TARGET_*</code> to demo with a user that can change demo's table structure and has no access to exam.</p>
                <p class="mt-2">Set <code>DB_SYNC_ENABLED=true</code> and <code>DB_SYNC_ALLOW_LOCAL=true</code> in your local private configuration only after checking the target database name. Your host must allow remote MySQL connections from your PC to both databases.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <h2 class="font-semibold text-emerald-900">Exam: read-only reference</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Use the database server host provided by your hosting provider. <code>localhost</code> works only if the exam database is on the same PC as this app. The comparison reads table names, column definitions, and indexes from <code>information_schema</code> inside a read-only transaction; it does not read student records or submit any SQL changes to exam.</p>
                    <p id="db-sync-source" class="mt-2 text-xs text-slate-500"></p>
                </section>
                <section class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
                    <h2 class="font-semibold text-sky-900">Demo: migration destination</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Only reviewed files in <code>database/sync_migrations/</code> can run on the separate demo connection. The runner accepts additive table, column, or index changes; it rejects data edits and destructive schema commands.</p>
                    <p id="db-sync-target" class="mt-2 text-xs text-slate-500"></p>
                </section>
            </div>

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
    let state = null;

    function message(text, error) {
        feedback.textContent = text;
        feedback.className = 'rounded-lg border p-3 text-sm ' + (error ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900');
        feedback.classList.remove('hidden');
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
                applyButton.classList.toggle('hidden', !state.pending_migrations.length);
                message('Ready. Comparing the exam connection reads schema metadata only.', false);
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
