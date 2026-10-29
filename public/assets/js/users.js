/**
 * Users pages (/user, /create-user, /edit-user/{id}) + /passchange.
 * One script for every [data-users-page] value; endpoint contracts live in
 * routes/users.php + routes/passchange.php (ported from original routes/api.php).
 *
 * The original list used DataTables (client-side paging) — reimplemented here
 * as a configurable client-side pager over GET /api/userList (a plain array,
 * exactly like the original response).
 */
(function () {
    'use strict';

    const root = document.getElementById('users-page');
    if (!root) { return; }

    const page = root.getAttribute('data-users-page');

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------
    function optionRoles(roles, selectedId) {
        let html = '<option value="">Select Role</option>';
        (roles || []).forEach(function (role) {
            const selected = selectedId !== null && selectedId !== undefined &&
                String(role.id) === String(selectedId);
            html += '<option value="' + BK.esc(String(role.id)) + '"' + (selected ? ' selected' : '') + '>' +
                BK.esc(role.name) + '</option>';
        });
        return html;
    }

    async function loadRoles(select, selectedId) {
        const json = await BK.get('/api/roleList');
        select.innerHTML = optionRoles(json.roleList || [], selectedId === undefined ? null : selectedId);
    }

    function fieldValue(form, name) {
        const el = form.querySelector('[data-field="' + name + '"]');
        return el ? el.value : '';
    }

    // -----------------------------------------------------------------
    // List page: rows + client-side pagination
    // -----------------------------------------------------------------
    if (page === 'index') {
        const rowsEl = document.getElementById('user-rows');
        const countEl = document.getElementById('user-count');
        const pagerEl = document.getElementById('user-pagination');
        const perPageEl = document.getElementById('user-per-page');
        const searchInput = document.getElementById('user-search');
        const searchReset = document.getElementById('user-search-reset');
        let perPage = 20;
        let allRows = [];
        let pageNum = 1;

        function filteredRows() {
            const term = (searchInput ? searchInput.value : '').trim().toLocaleLowerCase();
            if (!term) { return allRows; }
            return allRows.filter(function (user) {
                return [user.name, user.email, user.role_name]
                    .some(function (value) { return String(value || '').toLocaleLowerCase().includes(term); });
            });
        }

        function actionCell(id) {
            const safeId = BK.esc(String(id));
            return '<div class="row-actions flex items-center gap-1">' +
                '<a class="btn btn-sm btn-light" href="/edit-user/' + safeId + '" title="Edit">✎</a>' +
                '<button type="button" class="btn btn-sm btn-danger" data-act="delete" data-id="' + safeId + '" title="Delete">⨯</button>' +
                '</div>';
        }

        function renderPager(pages) {
            const total = filteredRows().length;
            const from = total ? ((pageNum - 1) * perPage) + 1 : 0;
            const to = Math.min(pageNum * perPage, total);
            let html = '<div class="flex flex-wrap items-center gap-1 text-sm">';
            if (pages > 1) {
                html += '<button type="button" class="px-3 py-1.5 rounded border bg-white border-slate-300 hover:bg-slate-100 disabled:opacity-50" data-page="' + Math.max(1, pageNum - 1) + '"' + (pageNum <= 1 ? ' disabled' : '') + '>Previous</button>';
                const first = Math.max(1, Math.min(pageNum - 2, pages - 4));
                const last = Math.min(pages, first + 4);
                for (let p = first; p <= last; p++) {
                    html += '<button type="button" class="px-3 py-1.5 rounded border ' +
                        (p === pageNum
                            ? 'bg-emerald-600 border-emerald-600 text-white'
                            : 'bg-white border-slate-300 hover:bg-slate-100') +
                        '" data-page="' + p + '">' + p + '</button>';
                }
                html += '<button type="button" class="px-3 py-1.5 rounded border bg-white border-slate-300 hover:bg-slate-100 disabled:opacity-50" data-page="' + Math.min(pages, pageNum + 1) + '"' + (pageNum >= pages ? ' disabled' : '') + '>Next</button>';
            }
            html += '<span class="ml-2 text-slate-500">Showing ' + from + '–' + to + ' of ' + total + '</span></div>';
            pagerEl.innerHTML = html;
        }

        function renderRows() {
            const matches = filteredRows();
            countEl.textContent = matches.length;
            const pages = Math.max(1, Math.ceil(matches.length / perPage));
            pageNum = Math.min(Math.max(1, pageNum), pages);
            const start = (pageNum - 1) * perPage;
            const slice = matches.slice(start, start + perPage);

            rowsEl.innerHTML = slice.length
                ? slice.map(function (user, i) {
                    return '<tr>' +
                        '<td>' + (start + i + 1) + '</td>' +
                        '<td>' + BK.esc(user.name) + '</td>' +
                        '<td>' + BK.esc(user.email) + '</td>' +
                        '<td>' + BK.esc(user.role_name) + '</td>' +
                        '<td>' + actionCell(user.id) + '</td>' +
                        '</tr>';
                }).join('')
                : '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No records found</td></tr>';

            renderPager(pages);
        }

        async function reload() {
            try {
                const json = await BK.get('/api/userList');
                allRows = json.userList || [];
                renderRows();
            } catch (err) {
                rowsEl.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-red-500">Could not load the list</td></tr>';
                BK.toast(err.message || 'Could not load the list', 'error');
            }
        }

        pagerEl.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-page]');
            if (!btn) { return; }
            pageNum = Number(btn.getAttribute('data-page'));
            renderRows();
        });

        if (perPageEl) {
            perPageEl.addEventListener('change', function () {
                perPage = Math.max(1, Number(perPageEl.value) || 20);
                pageNum = 1;
                renderRows();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                pageNum = 1;
                renderRows();
            });
        }
        if (searchReset) {
            searchReset.addEventListener('click', function () {
                if (searchInput) { searchInput.value = ''; }
                pageNum = 1;
                renderRows();
                if (searchInput) { searchInput.focus(); }
            });
        }

        rowsEl.addEventListener('click', async function (event) {
            const btn = event.target.closest('[data-act="delete"]');
            if (!btn) { return; }
            const id = btn.getAttribute('data-id');
            const confirmed = await BK.confirm("Are you sure? You won't be able to revert this!");
            if (!confirmed) { return; }
            try {
                await BK.del('/api/userDelete/' + encodeURIComponent(id));
                BK.toast('User has been deleted.', 'success');
                await reload();
            } catch (err) {
                BK.toast(err.message || 'Could not delete the user', 'error');
            }
        });

        reload();
        return;
    }

    // -----------------------------------------------------------------
    // Create / edit form
    // -----------------------------------------------------------------
    if (page === 'create' || page === 'edit') {
        const form = document.getElementById('user-form');
        const roleSel = document.getElementById('user-role');
        const userId = root.getAttribute('data-user-id');

        (async function () {
            try {
                if (page === 'edit') {
                    const json = await BK.get('/api/userById/' + encodeURIComponent(userId));
                    const user = json.userById || {};
                    form.querySelector('[data-field="name"]').value = user.name || '';
                    form.querySelector('[data-field="email"]').value = user.email || '';
                    await loadRoles(roleSel, user.roles);
                } else {
                    await loadRoles(roleSel, null);
                }
            } catch (err) {
                BK.toast(err.message || 'Could not load the form', 'error');
            }
        })();

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            BK.submit(form, async function () {
                const payload = {
                    name: fieldValue(form, 'name').trim(),
                    email: fieldValue(form, 'email').trim(),
                    roles: roleSel.value,
                    password: fieldValue(form, 'password')
                };
                if (page === 'create') {
                    payload.confirmpassword = fieldValue(form, 'confirmpassword');
                    await BK.post('/api/userSave', payload);
                    BK.toast('User added successfully', 'success');
                } else {
                    payload.cpassword = fieldValue(form, 'cpassword');
                    await BK.post('/api/userUpdate/' + encodeURIComponent(userId), payload);
                    BK.toast('User edited successfully', 'success');
                }
                window.location.href = '/user';
            });
        });
        return;
    }

    // -----------------------------------------------------------------
    // Password change form
    // -----------------------------------------------------------------
    if (page === 'passchange') {
        const form = document.getElementById('passchange-form');
        const profileForm = document.getElementById('account-profile-form');

        if (profileForm) {
            profileForm.addEventListener('submit', function (event) {
                event.preventDefault();
                BK.submit(profileForm, async function () {
                    await BK.post('/api/account/profile', {
                        name: fieldValue(profileForm, 'name'),
                        email: fieldValue(profileForm, 'email'),
                        current_password: fieldValue(profileForm, 'current_password')
                    });
                    BK.toast('Profile updated successfully', 'success');
                    window.location.reload();
                });
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            BK.submit(form, async function () {
                await BK.post('/api/passchange', {
                    password: fieldValue(form, 'password'),
                    npassword: fieldValue(form, 'npassword'),
                    cnpassword: fieldValue(form, 'cnpassword')
                });
                BK.toast('Password changed successfully', 'success');
                window.location.href = '/';
            });
        });
    }
})();
