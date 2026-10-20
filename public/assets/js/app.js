/**
 * BK — shared front-end helpers (fetch + CSRF + errors + toasts + pagination).
 * No build step: plain ES2017, works everywhere.
 */
window.BK = (function () {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const csrf = meta ? meta.content : '';

    /**
     * Core request. Resolves parsed JSON (or text), throws Error with
     * .status / .errors / .data on non-2xx.
     */
    async function request(url, opts = {}) {
        const method = (opts.method || 'GET').toUpperCase();
        const headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
        }, opts.headers || {});

        let body;
        if (opts.body instanceof FormData) {
            body = opts.body; // let browser set multipart boundary
        } else if (opts.body !== undefined && opts.body !== null) {
            headers['Content-Type'] = 'application/json;charset=utf-8';
            body = JSON.stringify(opts.body);
        }

        const res = await fetch(url, {
            method: method,
            headers: headers,
            body: body,
            credentials: 'same-origin'
        });

        const ct = res.headers.get('content-type') || '';
        const data = ct.includes('application/json') ? await res.json() : await res.text();

        if (!res.ok) {
            const err = new Error((data && data.message) || ('Request failed (' + res.status + ')'));
            err.status = res.status;
            err.errors = (data && data.errors) || {};
            err.data = data;
            if (res.status === 401) {
                err.message = 'Your session has expired. Please sign in again.';
            }
            throw err;
        }
        return data;
    }

    const get = (url) => request(url);
    const post = (url, body) => request(url, { method: 'POST', body: body });
    const put = (url, body) => request(url, { method: 'PUT', body: body });
    const del = (url, body) => request(url, { method: 'DELETE', body: body });

    /** Upload helper (multipart). */
    function upload(url, formData) {
        return request(url, { method: 'POST', body: formData });
    }

    // -----------------------------------------------------------------
    // Toasts
    // -----------------------------------------------------------------
    function toast(message, type, link) {
        type = type || 'success';
        const stack = document.getElementById('toast-stack');
        if (!stack) { return; }
        const colors = {
            success: 'bg-emerald-600 text-white',
            error: 'bg-red-600 text-white',
            info: 'bg-slate-800 text-white'
        };
        const el = document.createElement('div');
        el.className = 'rounded px-4 py-2.5 text-sm shadow-lg ' + (colors[type] || colors.info);
        const text = document.createElement('span');
        text.textContent = message;
        el.appendChild(text);
        // Optional action link (e.g. "View institutes"). Built from DOM nodes
        // with inline styles so no CSS rebuild is needed and values stay safe.
        let ttl = 3500;
        if (link && link.href) {
            const a = document.createElement('a');
            a.href = link.href;
            a.textContent = link.label || 'View';
            a.style.marginLeft = '8px';
            a.style.fontWeight = '600';
            a.style.textDecoration = 'underline';
            a.style.color = 'inherit';
            el.appendChild(a);
            ttl = 12000; // keep it clickable long enough
        }
        stack.appendChild(el);
        setTimeout(function () {
            el.style.transition = 'opacity .3s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 350);
        }, ttl);
    }

    // -----------------------------------------------------------------
    // Form validation errors (Laravel 422 shape: {field: [msgs]})
    // Convention: inputs carry data-field="name",
    //              error text goes in [data-error-for="name"]
    // -----------------------------------------------------------------
    function clearErrors(form) {
        if (!form) { return; }
        form.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
            el.classList.add('hidden');
        });
        form.querySelectorAll('[data-field]').forEach(function (el) {
            el.classList.remove('border-red-500');
        });
    }

    function showErrors(form, errors) {
        if (!form || !errors) { return; }
        let first = null;
        Object.keys(errors).forEach(function (field) {
            const msg = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
            const box = form.querySelector('[data-error-for="' + field + '"]');
            if (box) {
                box.textContent = msg;
                box.classList.remove('hidden');
            }
            const input = form.querySelector('[data-field="' + field + '"]');
            if (input) {
                input.classList.add('border-red-500');
                const visibleInput = input.dataset.centerPickerInputId
                    ? document.getElementById(input.dataset.centerPickerInputId)
                    : input;
                if (visibleInput) { visibleInput.classList.add('border-red-500'); }
                if (!first) { first = visibleInput || input; }
            }
        });
        if (first) {
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            first.focus({ preventScroll: true });
        }
    }

    /**
     * Standard submit handler: runs `handler`, shows validation errors,
     * returns true when resolved. Usage:
     *   BK.submit(form, async () => { await BK.post(...); location.reload(); })
     */
    async function submit(form, handler, opts) {
        opts = opts || {};
        clearErrors(form);
        const btn = opts.button || form.querySelector('[type="submit"]');
        if (btn) { btn.disabled = true; }
        try {
            await handler();
            return true;
        } catch (err) {
            if (err.status === 422 && err.errors) {
                showErrors(form, err.errors);
            } else if (err.status === 401) {
                toast(err.message, 'error');
                setTimeout(function () { window.location.href = '/login'; }, 1200);
            } else if (err.status === 403) {
                toast(err.message || 'You are not allowed to perform this action.', 'error');
            } else {
                toast(err.message || 'Something went wrong.', 'error');
            }
            return false;
        } finally {
            if (btn) { btn.disabled = false; }
        }
    }

    // -----------------------------------------------------------------
    // Confirm dialog (promise-based)
    // -----------------------------------------------------------------
    function confirm(message, title) {
        return new Promise(function (resolve) {
            resolve(window.confirm(message || 'Are you sure?'));
        });
    }

    // -----------------------------------------------------------------
    // Pagination renderer — consumes Laravel links[] from paginator JSON.
    // <div data-pagination data-href-base="/students?">
    // -----------------------------------------------------------------
    function renderPagination(container, pager) {
        if (!container || !pager) { return; }
        container.innerHTML = '';
        if (!pager.last_page || pager.last_page <= 1) { return; }

        const nav = document.createElement('nav');
        nav.className = 'flex flex-wrap items-center gap-1 text-sm';

        pager.links.forEach(function (link) {
            const a = document.createElement(link.url ? 'a' : 'span');
            a.className = 'px-3 py-1.5 rounded border ' +
                (link.active
                    ? 'bg-emerald-600 border-emerald-600 text-white'
                    : link.url
                        ? 'bg-white border-slate-300 hover:bg-slate-100'
                        : 'bg-white border-slate-200 text-slate-400');
            a.textContent = link.label;
            if (link.url) {
                a.href = link.url;
            }
            nav.appendChild(a);
        });

        const info = document.createElement('span');
        info.className = 'ml-3 text-slate-500';
        info.textContent = 'Showing ' + (pager.from || 0) + '–' + (pager.to || 0) + ' of ' + pager.total;
        nav.appendChild(info);
        container.appendChild(nav);
    }

    /** Fetch a paginated list and render rows via callback. */
    async function loadList(url, renderRow, listEl, paginationEl) {
        const pager = await get(url);
        const rows = pager.data || [];
        listEl.innerHTML = rows.length
            ? rows.map(renderRow).join('')
            : '<tr><td colspan="99" class="px-4 py-8 text-center text-slate-400">No records found</td></tr>';
        if (paginationEl) {
            renderPagination(paginationEl, pager);
        }
        return pager;
    }

    /** Escape text for safe innerHTML interpolation. */
    function esc(value) {
        if (value === null || value === undefined) { return ''; }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /** Read current query string as object. */
    function query(overrides) {
        const params = new URLSearchParams(window.location.search);
        const out = {};
        params.forEach(function (v, k) { out[k] = v; });
        return Object.assign(out, overrides || {});
    }

    /** Build /path?a=1&b=2 skipping null/empty. */
    function qs(path, params) {
        const u = new URLSearchParams();
        Object.keys(params || {}).forEach(function (k) {
            const v = params[k];
            if (v !== null && v !== undefined && v !== '') { u.set(k, v); }
        });
        const s = u.toString();
        return path + (s ? '?' + s : '');
    }

    return {
        request, get, post, put, del, upload,
        toast, clearErrors, showErrors, submit, confirm,
        renderPagination, loadList, esc, query, qs, csrf
    };
})();

// ---------------------------------------------------------------------
// Shell behaviors (sidebar toggle, session switcher)
// ---------------------------------------------------------------------
(function () {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (toggle && sidebar) {
        const close = function () {
            sidebar.classList.add('-translate-x-full');
            if (overlay) { overlay.classList.add('hidden'); }
        };
        toggle.addEventListener('click', function () {
            const hidden = sidebar.classList.contains('-translate-x-full');
            if (hidden) {
                sidebar.classList.remove('-translate-x-full');
                if (overlay) { overlay.classList.remove('hidden'); }
            } else {
                close();
            }
        });
        if (overlay) { overlay.addEventListener('click', close); }
    }

    const switcher = document.getElementById('session-switcher');
    if (switcher) {
        switcher.addEventListener('change', async function () {
            try {
                await BK.get('/api/updateSession/' + encodeURIComponent(switcher.value));
                window.location.reload();
            } catch (err) {
                BK.toast(err.message || 'Could not switch session', 'error');
            }
        });
    }

    // Top-right profile dropdown.
    const profToggle = document.getElementById('profile-toggle');
    const profMenu = document.getElementById('profile-dropdown');
    if (profToggle && profMenu) {
        const closeProfile = function () {
            profMenu.classList.add('hidden');
            profToggle.setAttribute('aria-expanded', 'false');
        };
        profToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const hidden = profMenu.classList.toggle('hidden');
            profToggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
        });
        document.addEventListener('click', function (e) {
            if (profMenu.classList.contains('hidden')) { return; }
            if (!profMenu.contains(e.target) && !profToggle.contains(e.target)) {
                closeProfile();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeProfile(); }
        });
    }

    // Sidebar: collapsible groups + client-side menu filter (no backend call).
    const navSearch = document.getElementById('nav-search');
    const navEl = document.getElementById('sidebar-nav');

    function setGroupOpen(group, open, persist) {
        group.classList.toggle('hidden', !open);
        group.dataset.open = open ? '1' : '0';
        const toggle = navEl.querySelector('[data-nav-group-toggle][aria-controls="' + group.id + '"]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            const chev = toggle.querySelector('[data-nav-chevron]');
            if (chev) { chev.style.transform = open ? 'rotate(90deg)' : 'rotate(0deg)'; }
        }
        if (persist) {
            try { localStorage.setItem('nav-group:' + group.id, open ? '1' : '0'); } catch (e) { /* ignore */ }
        }
    }

    if (navEl) {
        const groups = Array.from(navEl.querySelectorAll('[data-nav-group]'));
        groups.forEach(function (group) {
            let open = group.dataset.open !== '0';
            try {
                const saved = localStorage.getItem('nav-group:' + group.id);
                if (saved !== null) { open = saved === '1'; }
            } catch (e) { /* ignore */ }
            setGroupOpen(group, open, false);
            const toggle = navEl.querySelector('[data-nav-group-toggle][aria-controls="' + group.id + '"]');
            if (toggle) {
                toggle.addEventListener('click', function () {
                    setGroupOpen(group, group.classList.contains('hidden'), true);
                });
            }
        });

        if (navSearch) {
            const empty = navEl.querySelector('[data-nav-empty]');
            navSearch.addEventListener('input', function () {
                const q = navSearch.value.trim().toLowerCase();
                navEl.querySelectorAll('[data-nav-item]').forEach(function (el) {
                    const match = q === '' || (el.textContent || '').toLowerCase().indexOf(q) !== -1;
                    el.classList.toggle('hidden', !match);
                });
                // Reveal collapsed groups holding a match; restore the user's
                // open/closed choice once the box is cleared.
                groups.forEach(function (group) {
                    if (q === '') {
                        setGroupOpen(group, group.dataset.open === '1', false);
                        return;
                    }
                    const hasMatch = Array.prototype.some.call(
                        group.querySelectorAll('[data-nav-item]'),
                        function (it) { return !it.classList.contains('hidden'); }
                    );
                    setGroupOpen(group, hasMatch, false);
                });
                // Hide headers (incl. the group toggle) with no visible items.
                navEl.querySelectorAll('[data-nav-header]').forEach(function (el) {
                    if (q === '') { el.classList.remove('hidden'); return; }
                    let has = false;
                    if (el.hasAttribute('data-nav-group-toggle')) {
                        const group = document.getElementById(el.getAttribute('aria-controls'));
                        has = !!group && Array.prototype.some.call(
                            group.querySelectorAll('[data-nav-item]'),
                            function (it) { return !it.classList.contains('hidden'); }
                        );
                    }
                    el.classList.toggle('hidden', !has);
                });
                const anyVisible = Array.prototype.some.call(
                    navEl.querySelectorAll('[data-nav-item]'),
                    function (el) { return !el.classList.contains('hidden'); }
                );
                if (empty) { empty.classList.toggle('hidden', anyVisible || q === ''); }
            });
        }
    }
})();

// ---------------------------------------------------------------------
// Searchable Center fields. The native select stays in the DOM so existing
// forms and filters keep reading the same value and receiving change events.
// ---------------------------------------------------------------------
(function () {
    let generatedId = 0;
    const selector = 'select[name="center_id"], select[id*="center"]';
    const valueDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');

    function enhance(select) {
        if (select.dataset.centerPickerEnhanced === 'true' || select.hidden ||
            select.classList.contains('hidden') || select.getAttribute('aria-hidden') === 'true') {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'bk-center-picker';
        const input = document.createElement('input');
        input.type = 'search';
        input.className = select.className.replace(/\bhidden\b/g, '').trim();
        input.classList.add('bk-center-picker-input');
        input.placeholder = 'Search centers...';
        input.autocomplete = 'off';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');

        if (select.id) {
            input.id = select.id + '-search';
            document.querySelectorAll('label[for]').forEach(function (label) {
                if (label.htmlFor === select.id) { label.htmlFor = input.id; }
            });
        } else {
            input.id = 'bk-center-search-' + (++generatedId);
        }

        const options = document.createElement('div');
        options.className = 'bk-center-picker-options';
        options.hidden = true;
        options.setAttribute('role', 'listbox');
        options.id = input.id + '-options';
        input.setAttribute('aria-controls', options.id);

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(input);
        wrapper.appendChild(options);
        wrapper.appendChild(select);
        select.classList.add('bk-center-picker-native');
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;
        const required = select.required;
        select.required = false;
        input.required = required;
        select.dataset.centerPickerEnhanced = 'true';
        select.dataset.centerPickerInputId = input.id;

        let dirty = false;
        let activeIndex = -1;

        function selectedOption() {
            return Array.from(select.options).find(function (option) {
                return option.value === select.value;
            }) || null;
        }

        function isSelectedOption(option) {
            if (option.value !== '') { return option.value === select.value; }
            const label = option.textContent.trim();
            return select.value === '' && /all centers/i.test(label);
        }

        function sync() {
            if (dirty) { return; }
            const selected = selectedOption();
            input.value = selected ? selected.textContent.trim() : '';
            const valid = selected && (select.value !== '' || /all centers/i.test(selected.textContent));
            input.setCustomValidity(required && !valid ? 'Choose a center from the search results.' : '');
            input.disabled = select.disabled;
            input.classList.toggle('border-red-500', select.classList.contains('border-red-500'));
        }

        function close() {
            options.hidden = true;
            activeIndex = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        }

        function setActive(index) {
            const choices = Array.from(options.querySelectorAll('[role="option"]'));
            if (!choices.length) { activeIndex = -1; return; }
            activeIndex = (index + choices.length) % choices.length;
            choices.forEach(function (choice, choiceIndex) {
                choice.dataset.active = choiceIndex === activeIndex ? 'true' : 'false';
            });
            input.setAttribute('aria-activedescendant', choices[activeIndex].id);
            choices[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        function choose(option) {
            if (!option || option.disabled) { return; }
            dirty = false;
            select.value = option.value;
            input.value = option.textContent.trim();
            input.setCustomValidity('');
            close();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function render(query) {
            const normalizedQuery = String(query || '').trim().toLocaleLowerCase();
            const matches = Array.from(select.options).filter(function (option) {
                return option.textContent.trim().toLocaleLowerCase().includes(normalizedQuery);
            });
            options.textContent = '';
            activeIndex = -1;
            matches.forEach(function (option, index) {
                const choice = document.createElement('button');
                choice.type = 'button';
                choice.className = 'bk-center-picker-option';
                choice.tabIndex = -1;
                choice.id = input.id + '-option-' + index;
                choice.setAttribute('role', 'option');
                choice.setAttribute('aria-selected', isSelectedOption(option) ? 'true' : 'false');
                choice.dataset.value = option.value;
                choice.disabled = option.disabled;
                choice.textContent = option.textContent.trim() || 'Select Center';
                options.appendChild(choice);
            });
            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'bk-center-picker-empty';
                empty.textContent = 'No centers found';
                options.appendChild(empty);
            }
            options.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        input.addEventListener('focus', function () {
            dirty = false;
            input.value = '';
            render('');
        });
        input.addEventListener('input', function () {
            dirty = true;
            input.setCustomValidity(required ? 'Choose a center from the search results.' : '');
            render(input.value);
        });
        input.addEventListener('keydown', function (event) {
            const choices = options.querySelectorAll('[role="option"]');
            if (event.key === 'ArrowDown' && !options.hidden) {
                event.preventDefault();
                setActive(activeIndex + 1);
            } else if (event.key === 'ArrowUp' && !options.hidden) {
                event.preventDefault();
                setActive(activeIndex < 0 ? choices.length - 1 : activeIndex - 1);
            } else if (event.key === 'Enter' && !options.hidden) {
                event.preventDefault();
                const choice = activeIndex >= 0 ? choices[activeIndex] : choices[0];
                if (choice) {
                    const option = Array.from(select.options).find(function (item) {
                        return item.value === choice.dataset.value;
                    });
                    choose(option);
                }
            } else if (event.key === 'Escape') {
                dirty = false;
                sync();
                close();
            }
        });
        options.addEventListener('mousedown', function (event) {
            if (event.target.closest('[role="option"]')) { event.preventDefault(); }
        });
        options.addEventListener('click', function (event) {
            const choice = event.target.closest('[role="option"]');
            if (!choice) { return; }
            const option = Array.from(select.options).find(function (item) {
                return item.value === choice.dataset.value;
            });
            choose(option);
        });
        input.addEventListener('blur', function () {
            window.setTimeout(function () {
                if (document.activeElement === input) { return; }
                dirty = false;
                sync();
                close();
            }, 0);
        });
        select.addEventListener('change', function () {
            dirty = false;
            sync();
            close();
        });
        if (select.form) {
            select.form.addEventListener('reset', function () {
                window.setTimeout(function () {
                    dirty = false;
                    sync();
                    close();
                }, 0);
            });
        }

        // Existing view scripts sometimes set select.value without dispatching
        // change; mirror those updates in the visible search field as well.
        if (valueDescriptor && valueDescriptor.get && valueDescriptor.set) {
            try {
                Object.defineProperty(select, 'value', {
                    configurable: true,
                    enumerable: valueDescriptor.enumerable,
                    get: function () { return valueDescriptor.get.call(select); },
                    set: function (value) {
                        valueDescriptor.set.call(select, value);
                        dirty = false;
                        sync();
                    }
                });
            } catch (error) { /* Keep normal select behavior if accessors are restricted. */ }
        }

        new MutationObserver(function () {
            dirty = false;
            sync();
        }).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'disabled', 'selected'] });
        sync();
    }

    document.querySelectorAll(selector).forEach(enhance);
    new MutationObserver(function (records) {
        records.forEach(function (record) {
            record.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) { return; }
                if (node.matches && node.matches(selector)) { enhance(node); }
                if (node.querySelectorAll) { node.querySelectorAll(selector).forEach(enhance); }
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
})();

// ---------------------------------------------------------------------
// Row-action kebab: tapping/clicking the "⋮" area pins the action
// buttons visible (helps touch/hybrid devices where there is no
// persistent hover). Clicking anywhere else unpins.
// ---------------------------------------------------------------------
(function () {
    document.addEventListener('click', function (event) {
        const wa = event.target.closest ? event.target.closest('.row-actions') : null;
        const pinned = document.querySelectorAll('.row-actions.pinned');
        if (!wa) {
            pinned.forEach(function (el) { el.classList.remove('pinned'); });
            return;
        }
        pinned.forEach(function (el) { if (el !== wa) { el.classList.remove('pinned'); } });
        wa.classList.add('pinned');
    });
})();

// ---------------------------------------------------------------------
// Server-side flash messages -> toasts.
// Views render a pending flash as <div data-flash="success" hidden>.
// app.js loads at the end of <body>, so those nodes already exist here.
// ---------------------------------------------------------------------
(function () {
    document.querySelectorAll('[data-flash]').forEach(function (el) {
        const msg = (el.textContent || '').trim();
        if (msg) { BK.toast(msg, el.getAttribute('data-flash') || 'success'); }
        el.remove();
    });
})();
