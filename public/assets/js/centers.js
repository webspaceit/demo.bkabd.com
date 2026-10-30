/**
 * CENTERS pages — list (/centers) + forms (/create-center, /edit-center/{id}).
 * Contracts live in routes/centers.php:
 *   GET    /api/centers                  -> Laravel paginator JSON
 *   POST   /api/centers                  -> {status:'success'}
 *   GET    /api/centers/{id}             -> {centerById, districts, upazilas}
 *   POST   /api/centers/{id}             -> {status:'success'} (multipart PUT twin)
 *   POST   /api/centers/changestatus/{id}/{sta} -> {status:'success'}
 * Geo cascade endpoints come from routes/geo.php (divisionList / districtById / upazilaById).
 */
(function () {
    'use strict';

    // =================================================================
    // List page (/centers)
    // =================================================================
    const rowsEl = document.getElementById('center-rows');
    const formEl = document.getElementById('center-form');

    if (rowsEl) {
        const permBox = document.getElementById('centers-list');
        const pagerEl = document.getElementById('center-pagination');
        const totalEl = document.getElementById('center-total');
        const searchEl = document.getElementById('center-search');
        const perPageEl = document.getElementById('center-per-page');
        const perPagePicker = document.getElementById('center-per-page-picker');
        const perPageToggle = document.getElementById('center-per-page-toggle');
        const perPageMenu = document.getElementById('center-per-page-menu');
        const perPageValue = document.getElementById('center-per-page-value');
        const perPage = () => Number(perPageEl && perPageEl.value) || 20;
        const statusFilter = permBox ? (permBox.getAttribute('data-status-filter') || '') : '';

        let pageNum = 1;
        let searchTerm = '';
        let searchTimer = null;

        const can = (name) => !!permBox && permBox.getAttribute('data-perm-' + name) === '1';

        function rowHtml(rec, i) {
            const id = BK.esc(String(rec.id));
            const sl = (pageNum - 1) * perPage() + i + 1;
            const approved = String(rec.active) === '1';
            const referenceOnly = String(rec.has_account) === '0' || rec.has_account === false;
            const badge = referenceOnly
                ? '<span class="badge badge-gray">Synced reference</span>'
                : (approved ? '<span class="badge badge-green">Approved</span>' : '<span class="badge badge-gray">Pending</span>');

            let actions = '<div class="row-actions flex items-center gap-1">';
            if (!referenceOnly && can('edit')) {
                actions += '<a class="btn btn-sm btn-light" href="/edit-center/' + id + '" title="Edit">&#9998;</a>';
            }
            if (!referenceOnly && can('status')) {
                actions += '<button type="button" class="btn btn-sm btn-dark" data-act="status" data-id="' + id +
                    '" data-next="' + (approved ? 0 : 1) + '" title="' + (approved ? 'Set Pending' : 'Approve') + '">&#8644;</button>';
            }
            if (!referenceOnly && can('delete')) {
                actions += '<button type="button" class="btn btn-sm btn-danger" data-act="delete" data-id="' + id +
                    '" title="Delete">&#10005;</button>';
            }
            actions += '</div>';

            return '<tr><td>' + sl + '</td>' +
                '<td>' + BK.esc(rec.code) + '</td>' +
                '<td>' + BK.esc(rec.name) + '</td>' +
                '<td>' + BK.esc(rec.division) + '</td>' +
                '<td>' + BK.esc(rec.district) + '</td>' +
                '<td>' + BK.esc(rec.authority_person) + '</td>' +
                '<td>' + BK.esc(rec.phone) + '</td>' +
                '<td>' + BK.esc(rec.email) + '</td>' +
                '<td>' + badge + '</td>' +
                '<td>' + actions + '</td></tr>';
        }

        async function reload() {
            try {
                const pager = await BK.loadList(
                    BK.qs('/api/centers', { page: pageNum, per_page: perPage(), search: searchTerm, status: statusFilter }),
                    rowHtml,
                    rowsEl,
                    pagerEl
                );
                if (pager.current_page) { pageNum = pager.current_page; }
                if (totalEl) { totalEl.textContent = pager.total || 0; }
            } catch (err) {
                rowsEl.innerHTML = '<tr><td colspan="10" class="px-4 py-8 text-center text-red-500">Could not load the list</td></tr>';
                BK.toast(err.message || 'Could not load the list', 'error');
            }
        }

        // Pager links point at the JSON URL — turn clicks into AJAX page loads.
        if (pagerEl) {
            pagerEl.addEventListener('click', function (event) {
                const link = event.target.closest('a');
                if (!link) { return; }
                event.preventDefault();
                pageNum = Number(new URL(link.href).searchParams.get('page') || 1);
                reload();
            });
        }

        if (perPageEl) {
            perPageEl.addEventListener('change', function () {
                if (perPageValue) { perPageValue.textContent = String(perPage()); }
                if (perPageToggle) { perPageToggle.setAttribute('aria-label', 'Rows per page: ' + perPage()); }
                if (perPageMenu) {
                    perPageMenu.querySelectorAll('[data-per-page-option]').forEach(function (option) {
                        option.setAttribute('aria-pressed', option.getAttribute('data-per-page-option') === String(perPage()));
                    });
                }
                pageNum = 1;
                reload();
            });
        }

        if (perPagePicker && perPageToggle && perPageMenu && perPageEl) {
            perPageToggle.addEventListener('click', function () {
                const open = perPageMenu.classList.toggle('hidden') === false;
                perPageToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            perPageMenu.addEventListener('click', function (event) {
                const option = event.target.closest('[data-per-page-option]');
                if (!option) { return; }
                perPageEl.value = option.getAttribute('data-per-page-option');
                perPageEl.dispatchEvent(new Event('change', { bubbles: true }));
                perPageMenu.classList.add('hidden');
                perPageToggle.setAttribute('aria-expanded', 'false');
                perPageToggle.focus();
            });
            document.addEventListener('click', function (event) {
                if (!perPagePicker.contains(event.target)) {
                    perPageMenu.classList.add('hidden');
                    perPageToggle.setAttribute('aria-expanded', 'false');
                }
            });
            perPagePicker.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    perPageMenu.classList.add('hidden');
                    perPageToggle.setAttribute('aria-expanded', 'false');
                    perPageToggle.focus();
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
                }, 200);
            });
        }

        rowsEl.addEventListener('click', async function (event) {
            const btn = event.target.closest('[data-act]');
            if (!btn) { return; }
            const id = Number(btn.getAttribute('data-id'));
            const act = btn.getAttribute('data-act');

            if (act === 'status') {
                const confirmed = await BK.confirm('Are you sure? You want to change the center status.');
                if (!confirmed) { return; }
                try {
                    await BK.post('/api/centers/changestatus/' + id + '/' + btn.getAttribute('data-next'));
                    BK.toast('Center status has been changed.', 'success');
                    await reload();
                } catch (err) {
                    BK.toast(err.message || 'Could not change the status', 'error');
                }
                return;
            }

            if (act === 'delete') {
                const confirmed = await BK.confirm("Are you sure? You won't be able to revert this!");
                if (!confirmed) { return; }
                try {
                    await BK.del('/api/centers/' + id);
                    BK.toast('Center has been deleted.', 'success');
                    await reload();
                } catch (err) {
                    // destroy() answers 406 while institutes still reference the center.
                    if (err.status === 406) {
                        const n = (err.data && err.data.institutes) || 0;
                        const msg = n
                            ? n + ' institute' + (n === 1 ? '' : 's') + ' still registered in this center.'
                            : 'Some institute has registered in this center.';
                        const link = can('institute-list')
                            ? { label: 'View institutes', href: '/institutes?center_id=' + id }
                            : null;
                        BK.toast(msg, 'error', link);
                    } else {
                        BK.toast(err.message || 'Could not delete the center', 'error');
                    }
                }
            }
        });

        reload();
        return;
    }

    // =================================================================
    // Create / edit form
    // =================================================================
    if (!formEl) { return; }

    const centerId = Number(formEl.getAttribute('data-center-id') || 0); // 0 => create
    const divisionSel = document.getElementById('f-division_id');
    const districtSel = document.getElementById('f-district_id');
    const upazilaSel = document.getElementById('f-upazila_id');

    function optionList(items, selectedId, placeholder) {
        let html = '<option value="">' + BK.esc(placeholder) + '</option>';
        (items || []).forEach(function (item) {
            const selected = selectedId !== null && selectedId !== undefined &&
                String(item.id) === String(selectedId);
            html += '<option value="' + BK.esc(String(item.id)) + '"' + (selected ? ' selected' : '') + '>' +
                BK.esc(item.name) + '</option>';
        });
        return html;
    }

    async function loadDivisions(selectedId) {
        const json = await BK.get('/api/divisionList');
        divisionSel.innerHTML = optionList(json.divisionList || [], selectedId, 'Select Division');
    }

    async function loadDistricts(selectedId) {
        if (!divisionSel.value) {
            districtSel.innerHTML = optionList([], null, 'Select District');
            return;
        }
        const json = await BK.get('/api/districtById/' + divisionSel.value);
        districtSel.innerHTML = optionList(json.districtById || [], selectedId, 'Select District');
    }

    async function loadUpazilas(selectedId) {
        if (!districtSel.value) {
            upazilaSel.innerHTML = optionList([], null, 'Select Upazila');
            return;
        }
        const json = await BK.get('/api/upazilaById/' + districtSel.value);
        upazilaSel.innerHTML = optionList(json.upazilaById || [], selectedId, 'Select Upazila');
    }

    if (divisionSel && districtSel && upazilaSel) {
        divisionSel.addEventListener('change', function () {
            upazilaSel.innerHTML = optionList([], null, 'Select Upazila');
            loadDistricts(null).catch(function (err) {
                BK.toast(err.message || 'Could not load districts', 'error');
            });
        });
        districtSel.addEventListener('change', function () {
            loadUpazilas(null).catch(function (err) {
                BK.toast(err.message || 'Could not load upazilas', 'error');
            });
        });
    }

    function setField(name, value) {
        const el = formEl.querySelector('[name="' + name + '"]');
        if (el) { el.value = value === null || value === undefined ? '' : value; }
    }

    function showPreview(imgId, dir, file) {
        const img = document.getElementById(imgId);
        if (!img) { return; }
        if (file) {
            img.src = '/' + dir + '/' + encodeURIComponent(file);
            img.classList.remove('hidden');
        }
    }

    async function init() {
        if (centerId) {
            const json = await BK.get('/api/centers/' + centerId);
            const rec = json.centerById || {};
            ['name', 'email', 'phone', 'website', 'code', 'note',
                'authority_person', 'institute_name'].forEach(function (field) {
                setField(field, rec[field]);
            });
            await loadDivisions(rec.division_id);
            districtSel.innerHTML = optionList(json.districts || [], rec.district_id, 'Select District');
            upazilaSel.innerHTML = optionList(json.upazilas || [], rec.upazila_id, 'Select Upazila');
            showPreview('preview-photo', 'images/authorities', rec.authority_photo);
            showPreview('preview-signature', 'images/authority_signatures', rec.authority_signature);
        } else {
            await loadDivisions();
        }
    }

    formEl.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(formEl, async function () {
            await BK.request(centerId ? '/api/centers/' + centerId : '/api/centers', {
                method: 'POST',
                body: new FormData(formEl)
            });
            BK.toast(centerId ? 'Center edited successfully' : 'Center added successfully', 'success');
            window.location.href = '/centers';
        });
    });

    init().catch(function (err) {
        BK.toast(err.message || 'Could not load the form', 'error');
    });
})();
