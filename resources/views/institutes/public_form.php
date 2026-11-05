<?php
/**
 * Public institute self-registration form (no auth).
 * Port of the original resources/views/test.blade.php (InstituteController::
 * institutecreate + store2). Posts multipart to /api/ins-store; district/upazila
 * cascade through the public geo lookups.
 *
 * @var array $divisions rows: id, name
 */
$val = fn(string $f, string $d = '') => (string) ($old[$f] ?? $d);
$mainWebsite = trim(explode(',', brand('website'))[0] ?? '');
if ($mainWebsite !== '' && !preg_match('~^https?://~i', $mainWebsite)) { $mainWebsite = 'https://' . $mainWebsite; }
?>
<style>
.membership-intro {
    text-align: center!important;
    padding: 1%!important;
}
    .membership-intro .membership-kicker { margin-bottom: .35rem; }
    
    .membership-intro > p {max-width: 100%!important;margin-top: 0rem!important; }
    .membership-kicker { display: inline-flex; align-items: center; gap: .5rem; margin-bottom: .75rem; padding: .35rem .75rem; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; background: rgba(255,255,255,.1); color: #d1fae5; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .membership-hero > h1 { margin: 0; font-size: clamp(1.5rem, 3vw, 1.25rem); line-height: 1.15; font-weight: bold; letter-spacing: -.025em;text-transform: uppercase; }
    .membership-hero > p { max-width: 54rem; margin: .75rem 0 0; color: #ecfdf5; font-size: .95rem; line-height: 1.65; }
    .membership-card { overflow: visible; border: 1px solid #d1fae5; border-radius: 1.5rem; background: linear-gradient(145deg,#fff 0%,#fbfffd 100%); box-shadow: 0 16px 42px rgba(15,23,42,.08); }
    .membership-card-body { padding: clamp(1rem, 2.5vw, 2.25rem); }
    .membership-form { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 1.2rem 1rem; }
    .membership-form > .membership-full { grid-column: 1 / -1; }
    .membership-file-row { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }
    .membership-two { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }
    .membership-triple { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 1rem; }
    .membership-financial-row { display: grid; grid-template-columns: minmax(0,2fr) minmax(0,2fr) minmax(8rem,1fr) minmax(8rem,1fr); gap: 1rem; }
    .membership-form .fl { margin-top: .6rem; }
    .membership-form .fl .input { min-height: 3.15rem; border-radius: .8rem; border-color: #cbd5e1; background-color: #fff; transition: border-color .18s, box-shadow .18s; }
    .membership-form .fl .input:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(16,185,129,.12); }
    .membership-select-wrap { position: relative; }
    .membership-select-wrap > select { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    .membership-select-button { display: flex; width: 100%; min-height: 3.15rem; align-items: center; justify-content: space-between; gap: .75rem; padding: .65rem 1rem; border: 1px solid #cbd5e1; border-radius: .8rem; background: #fff; color: #0f172a; text-align: left; transition: border-color .18s, box-shadow .18s; }
    .membership-select-button:hover { border-color: #6ee7b7; }
    .membership-select-button:focus-visible, .membership-select-wrap.is-open .membership-select-button { border-color: #059669; box-shadow: 0 0 0 4px rgba(16,185,129,.12); outline: none; }
    .membership-select-button .is-placeholder { color: #64748b; }
    .membership-select-button[aria-invalid="true"] { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,.1); }
    .membership-select-chevron { width: 1rem; height: 1rem; flex: 0 0 1rem; color: #475569; transition: transform .16s ease; }
    .membership-select-wrap.is-open .membership-select-chevron { transform: rotate(180deg); }
    .membership-select-menu { position: absolute; z-index: 40; top: calc(100% + .4rem); left: 0; right: 0; max-height: 15rem; overflow-y: auto; padding: .35rem; border: 1px solid #d1fae5; border-radius: .85rem; background: #fff; box-shadow: 0 16px 35px rgba(15,23,42,.16); }
    .membership-select-menu[hidden] { display: none; }
    .membership-select-option { display: flex; width: 100%; min-height: 2.55rem; align-items: center; justify-content: space-between; gap: .75rem; padding: .55rem .75rem; border: 0; border-radius: .55rem; background: transparent; color: #334155; text-align: left; cursor: pointer; }
    .membership-select-option:hover, .membership-select-option:focus-visible { background: #f0fdf4; color: #065f46; outline: none; }
    .membership-select-option[aria-selected="true"] { background: #ecfdf5; color: #047857; font-weight: 600; }
    .membership-select-option[aria-disabled="true"] { color: #94a3b8; cursor: default; }
    .membership-form #monthly_income::placeholder, .membership-form #monthly_expense::placeholder { font-size: .75rem; }
    .membership-form .fl label { border-color: #cbd5e1; border-radius: .5rem .5rem 0 0; font-weight: 600; }
    .membership-form .fl:focus-within label { border-color: #059669; color: #047857; }
    .membership-form input[type=file].input { min-height: 3.4rem; padding-top: .72rem; }
    .membership-upload-card { min-width: 0; padding: 1rem; border: 1px solid #d1fae5; border-radius: 1rem; background: linear-gradient(145deg,#f0fdf4,#fff); box-shadow: 0 8px 20px rgba(6,78,59,.05); }
    .membership-upload-card .upload-help { margin: .5rem 0 0; color: #64748b; font-size: .78rem; line-height: 1.45; }
    .membership-upload-card input[type=file].input { width: 100%; border-style: dashed; border-color: #6ee7b7; background: #fff; }
    .membership-upload-card input[type=file]::file-selector-button { margin: -.3rem .75rem -.3rem -.3rem; padding: .55rem .9rem; border: 0; border-radius: .55rem; background: #059669; color: #fff; font: inherit; font-weight: 700; cursor: pointer; transition: background-color .15s, transform .15s; }
    .membership-upload-card input[type=file]::file-selector-button:hover { background: #047857; }
    .membership-upload-preview.hidden { display: none; }
    .membership-upload-preview { display: block; max-width: 100%; max-height: 7rem; margin-top: .75rem; border: 1px solid #d1fae5; border-radius: .65rem; background: #fff; object-fit: contain; }
    .membership-declaration { display: flex; align-items: flex-start; gap: .75rem; padding: 1rem; border: 1px solid #d1fae5; border-radius: .9rem; background: #f0fdf4; color: #475569; line-height: 1.6; }
    .membership-declaration input { margin-top: .25rem; accent-color: #059669; }
    .membership-submit { min-width: 12rem; min-height: 3rem; border-radius: .8rem; box-shadow: 0 8px 18px rgba(5,150,105,.2); }
    .membership-form .field-error { grid-column: 1 / -1; margin: .3rem 0 0; color: #b91c1c; font-size: .8rem; }
    @media (max-width: 1023px) { .membership-form { grid-template-columns: repeat(2,minmax(0,1fr)); } .membership-financial-row { grid-template-columns: repeat(2,minmax(0,1fr)); } .membership-triple { grid-template-columns: repeat(2,minmax(0,1fr)); } }
    @media (max-width: 639px) { .membership-page { padding-top: .75rem; } .membership-form { grid-template-columns: 1fr; gap: 1rem; } .membership-form > .membership-full { grid-column: 1; } .membership-file-row, .membership-two, .membership-financial-row, .membership-triple { grid-template-columns: 1fr; } .membership-submit { width: 100%; } }
</style>
<div class="membership-page">
    <?= partial('shared.membership_header') ?>
    <section class="membership-hero membership-intro">
        <span class="membership-kicker">Membership application</span>
        <h1>Institute Registration</h1>
        <p>Complete the form to apply for institute membership. Enter accurate details and attach the required logo.</p>
    </section>

    <div class="membership-card">
        <div class="membership-card-body">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-slate-900">Application details</h2>
                <p class="mt-1 text-sm text-slate-500">Fields marked <span class="text-red-500">*</span> are required.</p>
            </div>

            <div id="reg-alert" class="mb-4 hidden rounded border px-3 py-2 text-sm" role="alert"></div>

            <form id="institute-register-form" class="membership-form" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="membership-full membership-two">
                    <div class="fl">
                        <input class="input" type="text" id="name" name="name" data-field="name" placeholder="Institute Name" required value="<?= e($val('name')) ?>">
                        <label for="name">Name of Institute <span class="text-red-500">*</span></label>
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>

                    <div class="fl">
                        <input class="input" type="text" id="address" name="address" data-field="address" placeholder="Institute Address" required value="<?= e($val('address')) ?>">
                        <label for="address">Institute Address <span class="text-red-500">*</span></label>
                        <p class="field-error hidden" data-error-for="address"></p>
                    </div>
                </div>

                <div class="fl">
                    <input class="input" type="text" id="director_name" name="director_name" data-field="director_name" placeholder="Director Name" required value="<?= e($val('director_name')) ?>">
                    <label for="director_name">Representative / Director Name <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="director_name"></p>
                </div>

                <div class="fl">
                    <input class="input" type="text" id="phone" name="phone" data-field="phone" placeholder="Mobile Number" required value="<?= e($val('phone')) ?>">
                    <label for="phone">Mobile Number <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="phone"></p>
                </div>

                <div class="fl">
                    <input class="input" type="email" id="email" name="email" data-field="email" placeholder="Email Address" required value="<?= e($val('email')) ?>">
                    <label for="email">Email Address <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="email"></p>
                </div>

                <div class="fl">
                    <input class="input" type="text" id="website" name="website" data-field="website" placeholder="Website (if any)" value="<?= e($val('website')) ?>">
                    <label for="website">Website (If Any)</label>
                    <p class="field-error hidden" data-error-for="website"></p>
                </div>

                <div class="fl">
                    <select class="input" id="division-dropdown" name="division_id" data-field="division_id" required>
                        <option value="">Select Division</option>
                        <?php foreach ($divisions as $d): ?>
                            <option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="division_id">Division <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="division_id"></p>
                </div>

                <div class="fl">
                    <select class="input" id="district-dropdown" name="district_id" data-field="district_id" required>
                        <option value="">Select District</option>
                    </select>
                    <label for="district_id">District <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="district_id"></p>
                </div>

                <div class="fl">
                    <select class="input" id="upazila-dropdown" name="upazila_id" data-field="upazila_id" required>
                        <option value="">Select Upazila</option>
                    </select>
                    <label for="upazila_id">Upazila <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="upazila_id"></p>
                </div>

                <div class="fl">
                    <select class="input" id="union-dropdown" name="union_id" data-field="union_id">
                        <option value="">Select Union (Optional)</option>
                    </select>
                    <label for="union_id">Union</label>
                    <p class="field-error hidden" data-error-for="union_id"></p>
                </div>

                <div class="fl">
                    <input class="input" type="text" id="zip" name="zip" data-field="zip" placeholder="Postal Code" required value="<?= e($val('zip')) ?>">
                    <label for="zip">Postal Code <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="zip"></p>
                </div>

                <div class="fl">
                    <input class="input" type="date" id="established_date" name="established_date" data-field="established_date" placeholder="Established Date" required value="<?= e($val('established_date')) ?>">
                    <label for="established_date">Established Date <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="established_date"></p>
                </div>

                <div class="fl">
                    <input class="input" type="number" id="total_students" name="total_students" data-field="total_students" min="0" placeholder="e.g. 120" required value="<?= e($val('total_students')) ?>">
                    <label for="total_students">Total Students <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="total_students"></p>
                </div>

                <div class="fl">
                    <input class="input" type="number" id="total_teachers" name="total_teachers" data-field="total_teachers" min="0" placeholder="e.g. 8" required value="<?= e($val('total_teachers')) ?>">
                    <label for="total_teachers">Total Teachers <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="total_teachers"></p>
                </div>

                <div class="membership-full membership-triple">
                    <div class="fl">
                        <select class="input" id="membership_type" name="membership_type" data-field="membership_type" required>
                            <option value="">Select Membership Type</option>
                            <option value="1">Temporary (1500 BDT)</option>
                            <option value="2">Life Time (3000 BDT)</option>
                        </select>
                        <label for="membership_type">Membership Type <span class="text-red-500">*</span></label>
                        <p class="field-error hidden" data-error-for="membership_type"></p>
                    </div>

                    <div class="fl">
                        <select class="input" id="member_type" name="member_type" data-field="member_type" required>
                            <option value="">Select Member Type</option>
                            <option value="1">New Member</option>
                            <option value="2">Old Member</option>
                        </select>
                        <label for="member_type">Member Type <span class="text-red-500">*</span></label>
                        <p class="field-error hidden" data-error-for="member_type"></p>
                    </div>

                    <div class="fl">
                        <select class="input" id="type" name="type" data-field="type" required>
                            <option value="">Select Education Type</option>
                            <option value="1">Bangla Medium</option>
                            <option value="2">English Version</option>
                            <option value="3">Arabic Version</option>
                        </select>
                        <label for="type">Version of Education Type <span class="text-red-500">*</span></label>
                        <p class="field-error hidden" data-error-for="type"></p>
                    </div>
                </div>

                <div id="cert-wrap" class="hidden fl">
                    <input class="input" type="text" id="certificate_no" name="certificate_no" data-field="certificate_no" placeholder="Certificate No (if any)">
                    <label for="certificate_no">Membership Certificate No (If any)</label>
                    <p class="field-error hidden" data-error-for="certificate_no"></p>
                </div>

                <div class="membership-full membership-financial-row">
                    <div class="fl">
                        <select class="input" id="recognized" name="recognized" data-field="recognized" required>
                            <option value="">Select Recognized Status</option>
                            <option value="1">Yes</option>
                            <option value="2">No</option>
                        </select>
                        <label for="recognized">Is it Govt. Recognized / Approved? <span class="text-red-500">*</span></label>
                        <p class="field-error hidden" data-error-for="recognized"></p>
                    </div>

                    <div class="fl">
                        <select class="input" id="institution_run" name="institution_run" data-field="institution_run" required>
                            <option value="">Select Institution Status</option>
                            <option value="1">Personal</option>
                            <option value="2">Committee</option>
                            <option value="3">Partnership</option>
                        </select>
                        <label for="institution_run">How does the Institution Run? <span class="text-red-500">*</span></label>
                        <p class="field-error hidden" data-error-for="institution_run"></p>
                    </div>
                    <div class="fl">
                        <input class="input" type="text" id="monthly_income" name="monthly_income" data-field="monthly_income" placeholder="Monthly Income (BDT)">
                        <label for="monthly_income">Monthly Income</label>
                        <p class="field-error hidden" data-error-for="monthly_income"></p>
                    </div>

                    <div class="fl">
                        <input class="input" type="text" id="monthly_expense" name="monthly_expense" data-field="monthly_expense" placeholder="Monthly Expense (BDT)">
                        <label for="monthly_expense">Monthly Expense</label>
                        <p class="field-error hidden" data-error-for="monthly_expense"></p>
                    </div>
                </div>

                <div class="membership-full membership-file-row">
                    <div class="fl membership-upload-card">
                        <input type="file" id="logo" name="logo" class="input" accept="image/*" required>
                        <label for="logo">Institute Logo <span class="text-red-500">*</span></label>
                        <p class="upload-help">Upload a clear image of the institute logo.</p>
                        <img id="logo-preview" class="membership-upload-preview hidden" alt="Institute logo preview">
                        <p class="field-error hidden" data-error-for="logo"></p>
                    </div>

                    <div class="fl membership-upload-card">
                        <input class="input" type="file" id="principal_photo" name="principal_photo" accept="image/*">
                        <label for="principal_photo">Principal Photo</label>
                        <p class="upload-help">Upload an optional, clear principal photo.</p>
                        <img id="principal-photo-preview" class="membership-upload-preview hidden" alt="Principal photo preview">
                        <p class="field-error hidden" data-error-for="principal_photo"></p>
                    </div>
                </div>

                <div class="membership-full fl">
                    <input class="input" type="text" id="note" name="note" data-field="note" placeholder="Note (if any)">
                    <label for="note">Note (If Any)</label>
                    <p class="field-error hidden" data-error-for="note"></p>
                </div>

                <label class="membership-full membership-declaration text-sm">
                    <input type="checkbox" id="declaration" class="mt-1">
                    <span>My Declaration: I hereby declare that if this/my educational institution is recognized as a
                    member according to the laws of the association, I'll abide by all these rules and regulations and
                    take part in all activities of the association.</span>
                </label>

                <div class="membership-full flex justify-end">
                    <button type="submit" id="reg-submit" class="btn btn-primary membership-submit">Submit application</button>
                </div>
            </form>
        </div>
    </div>

    <p class="mt-5 text-center">
        <?php if ($mainWebsite !== ''): ?><a href="<?= e($mainWebsite) ?>" class="btn btn-dark btn-sm">Go to <?= e(brand('authority_name')) ?> Website</a><?php endif; ?>
    </p>
</div>

<script>
(function () {
    const form = document.getElementById('institute-register-form');
    const alertBox = document.getElementById('reg-alert');
    const submitBtn = document.getElementById('reg-submit');

    // Keep the native selects as the submitted values, but render their menus
    // consistently with the styled dropdowns on the public results form.
    const closeSelectMenus = (except) => {
        form.querySelectorAll('.membership-select-wrap.is-open').forEach((wrap) => {
            if (wrap !== except) {
                wrap.classList.remove('is-open');
                wrap.querySelector('.membership-select-menu')?.setAttribute('hidden', '');
                wrap.querySelector('.membership-select-button')?.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const selectStates = [];
    form.querySelectorAll('select.input').forEach((select) => {
        const label = form.querySelector(`label[for="${select.id}"]`) || form.querySelector(`label[for="${select.name}"]`);
        const wrapper = document.createElement('div');
        wrapper.className = 'membership-select-wrap';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        const isRequired = select.required;
        select.required = false;

        const button = document.createElement('button');
        button.type = 'button';
        button.id = `${select.id}-button`;
        button.className = 'membership-select-button';
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', label?.textContent.trim() || 'Choose an option');
        if (label) { label.htmlFor = button.id; }
        const display = document.createElement('span');
        const chevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        chevron.setAttribute('viewBox', '0 0 24 24');
        chevron.setAttribute('fill', 'none');
        chevron.setAttribute('stroke', 'currentColor');
        chevron.setAttribute('stroke-width', '2');
        chevron.setAttribute('stroke-linecap', 'round');
        chevron.setAttribute('stroke-linejoin', 'round');
        chevron.setAttribute('aria-hidden', 'true');
        chevron.classList.add('membership-select-chevron');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', 'm6 9 6 6 6-6');
        chevron.appendChild(path);
        button.append(display, chevron);

        const menu = document.createElement('div');
        menu.className = 'membership-select-menu';
        menu.id = `${select.id}-listbox`;
        menu.hidden = true;
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', label?.textContent.trim() || 'Options');
        button.setAttribute('aria-controls', menu.id);
        wrapper.append(button, menu);

        let items = [];
        const syncSelection = () => {
            const current = select.options[select.selectedIndex];
            display.textContent = current?.textContent.trim() || 'Select an option';
            display.classList.toggle('is-placeholder', !select.value);
            button.setAttribute('aria-invalid', String(isRequired && !select.value));
            items.forEach((item) => item.setAttribute('aria-selected', String(item.dataset.value === select.value)));
        };
        const rebuildOptions = () => {
            menu.replaceChildren();
            items = [];
            Array.from(select.options).forEach((option, index) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'membership-select-option';
                item.setAttribute('role', 'option');
                item.dataset.value = option.value;
                item.textContent = option.textContent.trim();
                if (option.disabled) { item.setAttribute('aria-disabled', 'true'); }
                item.addEventListener('click', () => {
                    if (option.disabled) { return; }
                    select.value = option.value;
                    button.removeAttribute('aria-invalid');
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncSelection();
                    wrapper.classList.remove('is-open');
                    menu.hidden = true;
                    button.setAttribute('aria-expanded', 'false');
                    button.focus();
                });
                item.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        const step = event.key === 'ArrowDown' ? 1 : -1;
                        let next = index + step;
                        while (items[next] && items[next].getAttribute('aria-disabled') === 'true') { next += step; }
                        items[next]?.focus();
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        closeSelectMenus();
                        button.focus();
                    }
                });
                items.push(item);
                menu.appendChild(item);
            });
            syncSelection();
        };
        rebuildOptions();
        select.addEventListener('change', syncSelection);
        const observer = new MutationObserver(rebuildOptions);
        observer.observe(select, { childList: true, subtree: true, attributes: true, characterData: true });
        selectStates.push({ select, button, wrapper, menu, isRequired, syncSelection, items: () => items });

        button.addEventListener('click', () => {
            const opening = menu.hidden;
            closeSelectMenus(wrapper);
            menu.hidden = !opening;
            wrapper.classList.toggle('is-open', opening);
            button.setAttribute('aria-expanded', String(opening));
        });
        button.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                closeSelectMenus(wrapper);
                menu.hidden = false;
                wrapper.classList.add('is-open');
                button.setAttribute('aria-expanded', 'true');
                const available = items.filter((item) => item.getAttribute('aria-disabled') !== 'true');
                (available.find((item) => item.getAttribute('aria-selected') === 'true') || available[0])?.focus();
            } else if (event.key === 'Escape') {
                closeSelectMenus();
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.membership-select-wrap')) { closeSelectMenus(); }
    });
    form.addEventListener('reset', () => setTimeout(() => selectStates.forEach((state) => state.syncSelection()), 0));

    document.getElementById('member_type').addEventListener('change', function () {
        document.getElementById('cert-wrap').classList.toggle('hidden', this.value !== '2');
    });

    [
        ['logo', 'logo-preview'],
        ['principal_photo', 'principal-photo-preview'],
    ].forEach(([inputId, previewId]) => {
        document.getElementById(inputId).addEventListener('change', function () {
            const img = document.getElementById(previewId);
            if (this.files && this.files[0]) {
                if (img.dataset.objectUrl) URL.revokeObjectURL(img.dataset.objectUrl);
                img.dataset.objectUrl = URL.createObjectURL(this.files[0]);
                img.src = img.dataset.objectUrl;
                img.classList.remove('hidden');
            } else {
                if (img.dataset.objectUrl) URL.revokeObjectURL(img.dataset.objectUrl);
                img.removeAttribute('src');
                img.classList.add('hidden');
            }
        });
    });

    function clearErrors() {
        form.querySelectorAll('[data-error-for]').forEach(el => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        alertBox.classList.add('hidden');
    }

    function showErrors(errors) {
        Object.keys(errors || {}).forEach(field => {
            const el = form.querySelector('[data-error-for="' + field + '"]');
            if (el) {
                el.textContent = Array.isArray(errors[field]) ? errors[field].join(' ') : String(errors[field]);
                el.classList.remove('hidden');
            }
        });
    }

    function alertMsg(text, kind, link) {
        if (window.BK && BK.alert) {
            BK.alert(text, kind === 'success' ? 'Success' : 'Wait!!', kind || 'error', link);
            return;
        }
        alertBox.textContent = text;
        alertBox.className = 'mb-4 rounded border px-3 py-2 text-sm ' +
            (kind === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700');
    }

    function selected(url, key, targetId) {
        return fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(res => {
                const sel = document.getElementById(targetId);
                sel.innerHTML = '<option value="">Select</option>';
                (res[key] || []).forEach(row => {
                    const o = document.createElement('option');
                    o.value = row.id;
                    o.textContent = row.name;
                    sel.appendChild(o);
                });
            });
    }

    document.getElementById('division-dropdown').addEventListener('change', function () {
        document.getElementById('upazila-dropdown').innerHTML = '<option value="">Select Upazila</option>';
        document.getElementById('union-dropdown').innerHTML = '<option value="">Select Union (Optional)</option>';
        if (this.value) selected('/api/districtById/' + this.value, 'districtById', 'district-dropdown');
    });
    document.getElementById('district-dropdown').addEventListener('change', function () {
        document.getElementById('upazila-dropdown').innerHTML = '<option value="">Select Upazila</option>';
        document.getElementById('union-dropdown').innerHTML = '<option value="">Select Union (Optional)</option>';
        if (this.value) selected('/api/upazilaById/' + this.value, 'upazilaById', 'upazila-dropdown');
    });
    document.getElementById('upazila-dropdown').addEventListener('change', function () {
        const unionSelect = document.getElementById('union-dropdown');
        unionSelect.innerHTML = '<option value="">Select Union (Optional)</option>';
        if (this.value) selected('/api/public/unions/by-upazila/' + encodeURIComponent(this.value), 'unionById', 'union-dropdown');
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();
        const missingSelect = selectStates.find((state) => state.isRequired && !state.select.value);
        if (missingSelect) {
            closeSelectMenus(missingSelect.wrapper);
            missingSelect.menu.hidden = false;
            missingSelect.wrapper.classList.add('is-open');
            missingSelect.button.setAttribute('aria-expanded', 'true');
            missingSelect.button.setAttribute('aria-invalid', 'true');
            missingSelect.button.focus();
            alertMsg('Please select an option for ' + (form.querySelector(`label[for="${missingSelect.button.id}"]`)?.textContent.replace('*', '').trim() || 'the required field') + '.', 'error');
            return;
        }
        if (!document.getElementById('declaration').checked) {
            alertMsg('Please check the declaration before submitting.', 'error');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        fetch('/api/ins-store', {
            method: 'POST',
            body: new FormData(form),
            headers: { 'Accept': 'application/json' },
        }).then(async r => {
            const body = await r.json().catch(() => ({}));
            if (r.ok && body.status === 'success') {
                form.reset();
                document.getElementById('cert-wrap').classList.add('hidden');
                ['logo-preview', 'principal-photo-preview'].forEach(id => {
                    const img = document.getElementById(id);
                    if (img.dataset.objectUrl) URL.revokeObjectURL(img.dataset.objectUrl);
                    img.removeAttribute('src');
                    img.classList.add('hidden');
                });
                alertMsg('Congratulations! Your membership registration has been completed successfully. ' +
                    'Please wait for authority approval or contact (+88 01711168094). ' +
                    'You can print your card below.', 'success', {
                        href: '/api/institute-registration/' + encodeURIComponent(body.id),
                        target: '_blank',
                        label: 'Print Registration Card'
                    });
            } else if (r.status === 401) {
                alertMsg('This institute is already registered.', 'error');
            } else if (r.status === 422 && body.errors) {
                showErrors(body.errors);
                alertMsg('Please correct the highlighted fields.', 'error');
            } else {
                alertMsg(body.message || 'Registration failed. Please try again.', 'error');
            }
        }).catch(() => {
            alertMsg('Network error. Please try again.', 'error');
        }).finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit application';
        });
    });
})();
</script>
