<style>
#login-history-pagination-footer {
    display: flex;
    width: 100%;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    overflow-x: auto;
}
#login-history-size-control {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: .6rem;
    margin: 0;
    white-space: nowrap;
}
#login-history-size {
    width: 5rem;
    min-width: 5rem;
    height: 2.4rem;
    padding: 0 .65rem;
    border: 1px solid #047253;
    border-radius: .4rem;
    background: #fff;
    color: #334155;
}
#login-history-pagination { flex: 0 0 auto; margin-left: auto; }
#login-history-pagination nav { flex-wrap: nowrap; white-space: nowrap; }
</style>
<div class="card" id="login-history-page">
    <div class="card-header flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-base font-semibold text-slate-800">Login History</h1>
            <p class="mt-1 text-sm text-slate-500">Successful sign-ins recorded after this feature is enabled.</p>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">Search
            <input id="login-history-search" type="search" class="form-control" placeholder="Name, email, or IP address" autocomplete="off">
        </label>
    </div>
    <div class="card-body">
        <div id="login-history-status" class="mb-3 text-sm text-slate-500" role="status" aria-live="polite">Loading login history…</div>
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead><tr><th>Signed in</th><th>User</th><th>Email</th><th>IP address</th><th>Browser / device</th></tr></thead>
                <tbody id="login-history-rows"><tr><td colspan="5" class="py-6 text-center text-slate-500">Loading…</td></tr></tbody>
            </table>
        </div>
        <div id="login-history-pagination-footer" class="mt-4">
            <label id="login-history-size-control" for="login-history-size" class="text-sm text-slate-600">Rows per page
                <select id="login-history-size"><option>25</option><option>50</option><option>100</option></select>
            </label>
            <div id="login-history-pagination"></div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('login-history-search');
    const size = document.getElementById('login-history-size');
    const status = document.getElementById('login-history-status');
    const rowsEl = document.getElementById('login-history-rows');
    const pagination = document.getElementById('login-history-pagination');
    let page = 1;
    let timer = null;

    function load() {
        status.textContent = 'Loading login history…';
        BK.get(BK.qs('/api/login-history', {page: page, per_page: size.value, search: search.value.trim()})).then(function (data) {
            if (data.error) throw new Error(data.error);
            rowsEl.replaceChildren();
            (data.data || []).forEach(function (record) {
                const tr = document.createElement('tr');
                const when = new Date(String(record.logged_at || '').replace(' ', 'T'));
                const values = [
                    Number.isNaN(when.getTime()) ? (record.logged_at || '—') : when.toLocaleString(),
                    record.user_name || '—', record.user_email || '—', record.ip_address || '—'
                ];
                values.forEach(function (value) {
                    const td = document.createElement('td');
                    td.textContent = String(value);
                    tr.appendChild(td);
                });
                const device = document.createElement('td');
                device.textContent = record.user_agent || '—';
                device.title = record.user_agent || '';
                device.className = 'max-w-sm truncate';
                tr.appendChild(device);
                rowsEl.appendChild(tr);
            });
            if (!(data.data || []).length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.className = 'py-6 text-center text-slate-500';
                td.textContent = data.total ? 'No sign-ins match this search.' : 'No login history yet.';
                tr.appendChild(td);
                rowsEl.appendChild(tr);
            }
            status.textContent = data.total ? 'Showing ' + data.from + '–' + data.to + ' of ' + Number(data.total).toLocaleString() + ' successful sign-ins.' : 'No login records found.';
            pagination.replaceChildren();
            BK.renderPagination(pagination, data);
        }).catch(function (error) {
            rowsEl.innerHTML = '<tr><td colspan="5" class="py-6 text-center text-rose-700"></td></tr>';
            rowsEl.querySelector('td').textContent = error.message || 'Could not load login history.';
            status.textContent = 'Login history could not be loaded.';
            pagination.replaceChildren();
        });
    }

    search.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { page = 1; load(); }, 250);
    });
    size.addEventListener('change', function () { page = 1; load(); });
    pagination.addEventListener('click', function (event) {
        const link = event.target.closest('a');
        if (!link) return;
        event.preventDefault();
        const url = new URL(link.href, window.location.origin);
        page = Number(url.searchParams.get('page') || 1);
        load();
    });
    load();
});
</script>
