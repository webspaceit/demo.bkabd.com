/**
 * Roles pages (/userrole, /create-role, /edit-role/{id}).
 * One script for every [data-roles-page] value; endpoint contracts live in
 * routes/roles.php (ported from original routes/api.php).
 *
 * Permissions are rendered as a grouped checkbox grid (permission name is
 * split on the first "-", otherwise "General") — the original rendered a flat
 * list; grouping is purely presentational, values are still permission ids.
 */
(function () {
    'use strict';

    const root = document.getElementById('roles-page');
    if (!root) { return; }

    const page = root.getAttribute('data-roles-page');

    // -----------------------------------------------------------------
    // List page
    // -----------------------------------------------------------------
    if (page === 'index') {
        const rowsEl = document.getElementById('role-rows');
        const countEl = document.getElementById('role-count');
        const addBtn = document.getElementById('role-add');

        function actionCell(id) {
            const safeId = BK.esc(String(id));
            return '<div class="row-actions flex items-center gap-1">' +
                '<a class="btn btn-sm btn-light" href="/edit-role/' + safeId + '" title="Edit">✎</a>' +
                '<button type="button" class="btn btn-sm btn-danger" data-act="delete" data-id="' + safeId + '" title="Delete">⨯</button>' +
                '</div>';
        }

        async function reload() {
            try {
                const json = await BK.get('/api/roleList');
                const rows = json.roleList || [];
                countEl.textContent = rows.length;
                rowsEl.innerHTML = rows.length
                    ? rows.map(function (role, i) {
                        return '<tr>' +
                            '<td>' + (i + 1) + '</td>' +
                            '<td>' + BK.esc(role.name) + '</td>' +
                            '<td>' + actionCell(role.id) + '</td>' +
                            '</tr>';
                    }).join('')
                    : '<tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">No records found</td></tr>';
            } catch (err) {
                rowsEl.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-red-500">Could not load the list</td></tr>';
                BK.toast(err.message || 'Could not load the list', 'error');
            }
        }

        // Original list.vue only showed "Add New Role" when the signed-in
        // user's permissions include role-create.
        (async function () {
            try {
                const json = await BK.get('/api/roleWisePermissionList');
                const mine = json.roleWisePermissionList || [];
                const allowed = mine.some(function (perm) { return perm.name === 'role-create'; });
                if (allowed) { addBtn.classList.remove('hidden'); }
            } catch (err) {
                BK.toast(err.message || 'Could not load your permissions', 'error');
            }
        })();

        rowsEl.addEventListener('click', async function (event) {
            const btn = event.target.closest('[data-act="delete"]');
            if (!btn) { return; }
            const id = btn.getAttribute('data-id');
            const confirmed = await BK.confirm("Are you sure? You won't be able to revert this!");
            if (!confirmed) { return; }
            try {
                await BK.del('/api/roleDelete/' + encodeURIComponent(id));
                BK.toast('Role has been deleted.', 'success');
                await reload();
            } catch (err) {
                BK.toast(err.message || 'Could not delete the role', 'error');
            }
        });

        reload();
        return;
    }

    // -----------------------------------------------------------------
    // Create / edit form
    // -----------------------------------------------------------------
    const form = document.getElementById('role-form');
    const nameInput = document.getElementById('role-name');
    const groupsEl = document.getElementById('permission-groups');
    const allBox = document.getElementById('permission-all');
    const roleId = root.getAttribute('data-role-id');

    function groupKey(permissionName) {
        const idx = permissionName.indexOf('-');
        return (idx > 0 ? permissionName.slice(0, idx) : 'General').toLowerCase();
    }

    function groupLabel(key) {
        return key === 'general' ? 'General' : key.charAt(0).toUpperCase() + key.slice(1);
    }

    /** Draw the grouped checkbox grid (checkedIds = Set of permission ids). */
    function renderPermissions(permissions, checkedIds) {
        const groups = new Map();
        (permissions || []).forEach(function (perm) {
            const key = groupKey(perm.name);
            if (!groups.has(key)) { groups.set(key, []); }
            groups.get(key).push(perm);
        });

        if (!groups.size) {
            groupsEl.innerHTML = '<p class="text-sm text-slate-400">No permissions found</p>';
            return;
        }

        let html = '';
        groups.forEach(function (list, key) {
            html += '<div>' +
                '<p class="label">' + BK.esc(groupLabel(key)) + '</p>' +
                '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">';
            list.forEach(function (perm) {
                const checked = checkedIds.has(Number(perm.id)) ? ' checked' : '';
                html += '<label class="flex items-center gap-2 text-sm text-slate-700">' +
                    '<input type="checkbox" class="permission-box" value="' + BK.esc(String(perm.id)) + '"' + checked + '>' +
                    '<span>' + BK.esc(perm.name) + '</span>' +
                    '</label>';
            });
            html += '</div></div>';
        });
        groupsEl.innerHTML = html;
        syncSelectAll();
    }

    function boxes() {
        return Array.prototype.slice.call(groupsEl.querySelectorAll('.permission-box'));
    }

    function checkedIds() {
        return boxes()
            .filter(function (box) { return box.checked; })
            .map(function (box) { return Number(box.value); });
    }

    function syncSelectAll() {
        const all = boxes();
        allBox.checked = all.length > 0 && all.every(function (box) { return box.checked; });
    }

    allBox.addEventListener('change', function () {
        boxes().forEach(function (box) { box.checked = allBox.checked; });
    });

    groupsEl.addEventListener('change', syncSelectAll);

    (async function () {
        try {
            if (page === 'edit') {
                const results = await Promise.all([
                    BK.get('/api/permissionList'),
                    BK.get('/api/roleById/' + encodeURIComponent(roleId))
                ]);
                const permissions = results[0].permissionList || [];
                const role = results[1].role || {};
                const assigned = new Set((results[1].rolePermissions || []).map(Number));
                nameInput.value = role.name || '';
                renderPermissions(permissions, assigned);
            } else {
                const json = await BK.get('/api/permissionList');
                renderPermissions(json.permissionList || [], new Set());
            }
        } catch (err) {
            groupsEl.innerHTML = '<p class="text-sm text-red-500">Could not load permissions</p>';
            BK.toast(err.message || 'Could not load permissions', 'error');
        }
    })();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            const payload = {
                name: nameInput.value.trim(),
                permission: checkedIds()
            };
            if (page === 'create') {
                await BK.post('/api/roleSave', payload);
                BK.toast('Role added successfully', 'success');
            } else {
                await BK.post('/api/roleUpdate/' + encodeURIComponent(roleId), payload);
                BK.toast('Role edited successfully', 'success');
            }
            window.location.href = '/userrole';
        });
    });
})();
