/**
 * GEO pages (/divisions, /districts, /upazilas) — list + inline create/edit form.
 * One script for all three pages; behaviour selected by [data-geo-page].
 * Endpoint contracts live in routes/geo.php (ported from original routes/api.php).
 */
(function () {
    'use strict';

    const root = document.getElementById('geo-page');
    if (!root) { return; }

    const listEl = document.getElementById('geo-rows');
    const countEl = document.getElementById('geo-count');
    const form = document.getElementById('geo-form');
    const formTitle = document.getElementById('geo-form-title');
    const cancelBtn = document.getElementById('geo-cancel');
    const newBtn = document.getElementById('geo-new');
    const parentSel = document.getElementById('geo-parent'); // division select (districts / upazilas)
    const childSel = document.getElementById('geo-child');   // district select (upazilas)
    const searchEl = document.getElementById('geo-search');  // upazilas only
    const pagerEl = document.getElementById('geo-pagination');
    const nameInput = document.getElementById('geo-name');

    let editingId = null;      // null => create mode
    let divisionCache = null;  // divisions for the parent select (loaded once)
    let pageNum = 1;
    let searchTerm = '';
    let searchTimer = null;

    function actionsCell(id) {
        const safeId = BK.esc(String(id));
        return '<div class="row-actions flex items-center gap-1">' +
            '<button type="button" class="btn btn-sm btn-light" data-act="edit" data-id="' + safeId + '" title="Edit">✎</button>' +
            '<button type="button" class="btn btn-sm btn-danger" data-act="delete" data-id="' + safeId + '" title="Delete">⨯</button>' +
            '</div>';
    }

    function optionList(items, selectedId, placeholder) {
        let html = '<option value="">' + BK.esc(placeholder) + '</option>';
        (items || []).forEach(function (item) {
            const selected = selectedId !== null && selectedId !== undefined && String(item.id) === String(selectedId);
            html += '<option value="' + BK.esc(String(item.id)) + '"' + (selected ? ' selected' : '') + '>' +
                BK.esc(item.name) + '</option>';
        });
        return html;
    }

    const PAGES = {
        divisions: {
            cols: 3,
            addTitle: 'Add Division',
            editTitle: 'Edit Division',
            fields: ['name'],
            added: 'Division added successfully',
            edited: 'Division edited successfully',
            deleted: 'Division has been deleted.',
            load: async function () {
                const json = await BK.get('/api/divisionList');
                return { rows: json.divisionList || [], total: (json.divisionList || []).length };
            },
            byId: async function (id) {
                const json = await BK.get('/api/divisionById/' + id);
                return json.divisionById;
            },
            fill: function (rec) {
                nameInput.value = rec.name || '';
            },
            save: function (id, payload) {
                return id === null
                    ? BK.post('/api/divisionSave', payload)
                    : BK.post('/api/divisionUpdate/' + id, payload);
            },
            remove: function (id) {
                return BK.del('/api/divisionDelete/' + id);
            },
            row: function (rec, i) {
                return '<td>' + (i + 1) + '</td><td>' + BK.esc(rec.name) + '</td><td>' + actionsCell(rec.id) + '</td>';
            }
        },
        districts: {
            cols: 4,
            addTitle: 'Add District',
            editTitle: 'Edit District',
            fields: ['name', 'division_id'],
            added: 'Districts added successfully',
            edited: 'Districts edited successfully',
            deleted: 'District has been deleted.',
            load: async function () {
                const json = await BK.get('/api/districts');
                return { rows: json.districtList || [], total: (json.districtList || []).length };
            },
            byId: async function (id) {
                const json = await BK.get('/api/districtById2/' + id);
                return json.districtById;
            },
            fill: async function (rec) {
                nameInput.value = rec.name || '';
                if (!divisionCache) { await loadDivisions(); }
                parentSel.value = String(rec.division_id || '');
            },
            save: function (id, payload) {
                return id === null
                    ? BK.post('/api/districts', payload)
                    : BK.post('/api/districtUpdate/' + id, payload);
            },
            remove: function (id) {
                return BK.del('/api/districts/' + id);
            },
            row: function (rec, i) {
                const division = rec.division ? rec.division.name : '';
                return '<td>' + (i + 1) + '</td><td>' + BK.esc(rec.name) + '</td><td>' + BK.esc(division) +
                    '</td><td>' + actionsCell(rec.id) + '</td>';
            }
        },
        upazilas: {
            cols: 4,
            addTitle: 'Add Upazila',
            editTitle: 'Edit Upazila',
            fields: ['name', 'district_id'],
            added: 'Upazilas added successfully',
            edited: 'Upazilas edited successfully',
            deleted: 'Upazila has been deleted.',
            load: async function () {
                const url = BK.qs('/api/upazilas', {
                    page: pageNum,
                    per_page: 20,
                    search: searchTerm
                });
                const pager = await BK.get(url);
                return { rows: pager.data || [], total: pager.total, pager: pager };
            },
            byId: async function (id) {
                const json = await BK.get('/api/upazilas/' + id);
                return {
                    name: json.upazilaById ? json.upazilaById.name : '',
                    district_id: json.upazilaById ? json.upazilaById.district_id : '',
                    division_id: json.division ? json.division.id : '',
                    districts: json.districts || []
                };
            },
            fill: async function (rec) {
                nameInput.value = rec.name || '';
                if (!divisionCache) { await loadDivisions(); }
                parentSel.value = String(rec.division_id || '');
                childSel.innerHTML = optionList(rec.districts, rec.district_id, 'Select District');
            },
            save: function (id, payload) {
                return id === null
                    ? BK.post('/api/upazilas', payload)
                    : BK.put('/api/upazilas/' + id, payload);
            },
            remove: function (id) {
                return BK.del('/api/upazilas/' + id);
            },
            row: function (rec, i) {
                const district = rec.district ? rec.district.name : '';
                return '<td>' + (i + 1) + '</td><td>' + BK.esc(rec.name) + '</td><td>' + BK.esc(district) +
                    '</td><td>' + actionsCell(rec.id) + '</td>';
            }
        }
    };

    const cfg = PAGES[root.getAttribute('data-geo-page')];
    if (!cfg) { return; }

    // ---------------------------------------------------------------
    // List
    // ---------------------------------------------------------------
    async function reload() {
        try {
            const res = await cfg.load();
            listEl.innerHTML = res.rows.length
                ? res.rows.map(function (rec, i) {
                    return '<tr data-id="' + BK.esc(String(rec.id)) + '">' + cfg.row(rec, i) + '</tr>';
                }).join('')
                : '<tr><td colspan="' + cfg.cols + '" class="px-4 py-8 text-center text-slate-400">No records found</td></tr>';
            if (countEl) { countEl.textContent = res.total; }
            if (res.pager) {
                pageNum = res.pager.current_page;
                BK.renderPagination(pagerEl, res.pager);
            }
        } catch (err) {
            listEl.innerHTML = '<tr><td colspan="' + cfg.cols +
                '" class="px-4 py-8 text-center text-red-500">Could not load the list</td></tr>';
            BK.toast(err.message || 'Could not load the list', 'error');
        }
    }

    // ---------------------------------------------------------------
    // Form (create / edit)
    // ---------------------------------------------------------------
    function resetForm() {
        editingId = null;
        form.reset();
        BK.clearErrors(form);
        formTitle.textContent = cfg.addTitle;
        cancelBtn.classList.add('hidden');
        if (childSel) { childSel.innerHTML = optionList([], null, 'Select District'); }
    }

    async function loadDivisions(selectedId) {
        const json = await BK.get('/api/divisionList');
        divisionCache = json.divisionList || [];
        parentSel.innerHTML = optionList(divisionCache, selectedId === undefined ? null : selectedId, 'Select Division');
    }

    async function startEdit(id) {
        try {
            const rec = await cfg.byId(id);
            if (!rec) {
                BK.toast('Record not found', 'error');
                return;
            }
            editingId = Number(id);
            BK.clearErrors(form);
            formTitle.textContent = cfg.editTitle;
            cancelBtn.classList.remove('hidden');
            await cfg.fill(rec);
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (err) {
            BK.toast(err.message || 'Could not load the record', 'error');
        }
    }

    newBtn.addEventListener('click', function () {
        resetForm();
        nameInput.focus();
    });

    cancelBtn.addEventListener('click', resetForm);

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            const payload = {};
            cfg.fields.forEach(function (field) {
                const el = form.querySelector('[data-field="' + field + '"]');
                payload[field] = el ? el.value : '';
            });
            const isEdit = editingId !== null;
            await cfg.save(editingId, payload);
            BK.toast(isEdit ? cfg.edited : cfg.added, 'success');
            resetForm();
            await reload();
        });
    });

    // ---------------------------------------------------------------
    // Row actions (edit / delete) + pagination
    // ---------------------------------------------------------------
    listEl.addEventListener('click', async function (event) {
        const btn = event.target.closest('[data-act]');
        if (!btn) { return; }
        const id = Number(btn.getAttribute('data-id'));
        if (btn.getAttribute('data-act') === 'edit') {
            await startEdit(id);
            return;
        }
        const confirmed = await BK.confirm("Are you sure? You won't be able to revert this!");
        if (!confirmed) { return; }
        try {
            await cfg.remove(id);
            BK.toast(cfg.deleted, 'success');
            if (editingId === id) { resetForm(); }
            await reload();
        } catch (err) {
            BK.toast(err.message || 'Could not delete the record', 'error');
        }
    });

    if (pagerEl) {
        pagerEl.addEventListener('click', function (event) {
            const link = event.target.closest('a');
            if (!link) { return; }
            event.preventDefault();
            pageNum = Number(new URL(link.href).searchParams.get('page') || 1);
            reload();
        });
    }

    // ---------------------------------------------------------------
    // Cascading selects + search (upazilas)
    // ---------------------------------------------------------------
    if (parentSel && childSel) {
        parentSel.addEventListener('change', async function () {
            if (!parentSel.value) {
                childSel.innerHTML = optionList([], null, 'Select District');
                return;
            }
            try {
                const json = await BK.get('/api/districtById/' + parentSel.value);
                childSel.innerHTML = optionList(json.districtById, null, 'Select District');
            } catch (err) {
                BK.toast(err.message || 'Could not load districts', 'error');
            }
        });
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                searchTerm = searchEl.value.trim();
                pageNum = 1;
                reload();
            }, 300);
        });
    }

    // ---------------------------------------------------------------
    // Init
    // ---------------------------------------------------------------
    (async function () {
        if (parentSel) {
            try {
                await loadDivisions();
            } catch (err) {
                BK.toast(err.message || 'Could not load divisions', 'error');
            }
        }
        await reload();
    })();
})();
