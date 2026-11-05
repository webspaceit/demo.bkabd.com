/**
 * Public result search page — Clear button + double-submit guard.
 * The form posts natively (original UX: server renders the result sheet
 * directly on success, redirects back with the message on failure).
 */
(function () {
    const form = document.getElementById('result-search-form');
    if (!form) { return; }

    const clearBtn = document.getElementById('result-clear');
    const submitBtn = document.getElementById('result-submit');

    const closeMenus = (except) => {
        form.querySelectorAll('.result-select-wrap.is-open').forEach((wrap) => {
            if (wrap !== except) {
                wrap.classList.remove('is-open');
                wrap.querySelector('.result-select-menu')?.setAttribute('hidden', '');
                wrap.querySelector('.result-select-button')?.setAttribute('aria-expanded', 'false');
            }
        });
    };

    form.querySelectorAll('select#session_id, select#class').forEach((select) => {
        const label = form.querySelector(`label[for="${select.id}"]`);
        const wrap = document.createElement('div');
        wrap.className = 'result-select-wrap';
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        const button = document.createElement('button');
        button.type = 'button';
        button.id = `${select.id}-button`;
        button.className = 'result-select-button';
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', label?.textContent.trim() || 'Choose an option');
        if (label) { label.htmlFor = button.id; }

        const selectedText = document.createElement('span');
        const chevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        chevron.setAttribute('viewBox', '0 0 24 24');
        chevron.setAttribute('fill', 'none');
        chevron.setAttribute('stroke', 'currentColor');
        chevron.setAttribute('stroke-width', '2');
        chevron.setAttribute('stroke-linecap', 'round');
        chevron.setAttribute('stroke-linejoin', 'round');
        chevron.setAttribute('aria-hidden', 'true');
        chevron.classList.add('result-select-chevron');
        const chevronPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        chevronPath.setAttribute('d', 'm6 9 6 6 6-6');
        chevron.appendChild(chevronPath);
        button.append(selectedText, chevron);

        const menu = document.createElement('div');
        menu.className = 'result-select-menu';
        menu.id = `${select.id}-listbox`;
        menu.hidden = true;
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', label?.textContent.trim() || 'Options');
        button.setAttribute('aria-controls', menu.id);

        const options = [];
        Array.from(select.options).forEach((option, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'result-select-option';
            item.setAttribute('role', 'option');
            item.dataset.value = option.value;
            item.textContent = option.textContent.trim();
            if (option.disabled) { item.setAttribute('aria-disabled', 'true'); }
            item.addEventListener('click', () => {
                if (option.disabled) { return; }
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                syncSelection();
                wrap.classList.remove('is-open');
                menu.hidden = true;
                button.setAttribute('aria-expanded', 'false');
                button.focus();
            });
            item.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const step = event.key === 'ArrowDown' ? 1 : -1;
                    let next = index + step;
                    while (options[next] && options[next].getAttribute('aria-disabled') === 'true') { next += step; }
                    options[next]?.focus();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    menu.hidden = true;
                    wrap.classList.remove('is-open');
                    button.setAttribute('aria-expanded', 'false');
                    button.focus();
                }
            });
            options.push(item);
            menu.appendChild(item);
        });

        wrap.append(button, menu);

        function syncSelection() {
            const current = select.options[select.selectedIndex];
            selectedText.textContent = current?.textContent.trim() || 'Select an option';
            selectedText.classList.toggle('is-placeholder', !select.value);
            options.forEach((item) => item.setAttribute('aria-selected', String(item.dataset.value === select.value)));
        }
        syncSelection();
        select.addEventListener('change', syncSelection);

        button.addEventListener('click', () => {
            const opening = menu.hidden;
            closeMenus(wrap);
            menu.hidden = !opening;
            wrap.classList.toggle('is-open', opening);
            button.setAttribute('aria-expanded', String(opening));
        });
        button.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                closeMenus(wrap);
                menu.hidden = false;
                wrap.classList.add('is-open');
                button.setAttribute('aria-expanded', 'true');
                const selected = options.find((item) => item.getAttribute('aria-selected') === 'true' && item.getAttribute('aria-disabled') !== 'true');
                (selected || options.find((item) => item.getAttribute('aria-disabled') !== 'true'))?.focus();
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.result-select-wrap')) { closeMenus(); }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            form.reset();
            form.querySelectorAll('.result-select-wrap').forEach((wrap) => {
                const select = wrap.querySelector('select');
                select?.dispatchEvent(new Event('change', { bubbles: true }));
                wrap.classList.remove('is-open');
                const menu = wrap.querySelector('.result-select-menu');
                if (menu) { menu.hidden = true; }
                wrap.querySelector('.result-select-button')?.setAttribute('aria-expanded', 'false');
            });
            BK.clearErrors(form);
        });
    }

    form.addEventListener('submit', function () {
        if (submitBtn) {
            // Original flow: "Submitted. Wait for result" while navigating.
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitted. Wait for result';
        }
    });
})();
