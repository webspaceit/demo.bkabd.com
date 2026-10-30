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
<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="mb-5 text-center text-xs leading-relaxed text-slate-500">
        <p><b><?= e(brand('address')) ?><?= brand('phone') !== '' ? ', Mobile: ' . e(brand('phone')) : '' ?></b></p>
        <p><b><?= brand('email') !== '' ? 'Email: ' . e(brand('email')) : '' ?><?= brand('website') !== '' ? ', Web: ' . e(brand('website')) : '' ?></b></p>
    </div>

    <div class="card rounded-3xl border-dashed">
        <div class="card-body">
            <h1 class="mb-4 text-center text-lg font-bold text-slate-900">New Institute Registration</h1>

            <div id="reg-alert" class="mb-4 hidden rounded border px-3 py-2 text-sm" role="alert"></div>

            <form id="institute-register-form" class="grid gap-4 sm:grid-cols-2" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="sm:col-span-2 fl">
                    <input class="input" type="text" id="name" name="name" data-field="name" placeholder="Institute Name" required value="<?= e($val('name')) ?>">
                    <label for="name">Name of Institute <span class="text-red-500">*</span></label>
                    <p class="field-error hidden" data-error-for="name"></p>
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

                <div id="cert-wrap" class="hidden fl">
                    <input class="input" type="text" id="certificate_no" name="certificate_no" data-field="certificate_no" placeholder="Certificate No (if any)">
                    <label for="certificate_no">Membership Certificate No (If any)</label>
                    <p class="field-error hidden" data-error-for="certificate_no"></p>
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

                <div class="fl">
                    <input type="file" id="logo" name="logo" class="input" accept="image/*" required>
                    <label for="logo">Institute Logo <span class="text-red-500">*</span></label>
                    <img id="logo-preview" class="mt-2 hidden h-20 w-20 rounded object-cover" alt="">
                    <p class="field-error hidden" data-error-for="logo"></p>
                </div>

                <div class="fl">
                    <input class="input" type="file" id="principal_photo" name="principal_photo" accept="image/*">
                    <label for="principal_photo">Principal Photo</label>
                    <p class="field-error hidden" data-error-for="principal_photo"></p>
                </div>

                <div class="sm:col-span-2 fl">
                    <input class="input" type="text" id="note" name="note" data-field="note" placeholder="Note (if any)">
                    <label for="note">Note (If Any)</label>
                    <p class="field-error hidden" data-error-for="note"></p>
                </div>

                <label class="sm:col-span-2 flex items-start gap-2 text-sm text-slate-600">
                    <input type="checkbox" id="declaration" class="mt-1">
                    <span>My Declaration: I hereby declare that if this/my educational institution is recognized as a
                    member according to the laws of the association, I'll abide by all these rules and regulations and
                    take part in all activities of the association.</span>
                </label>

                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" id="reg-submit" class="btn btn-primary">Submit</button>
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

    document.getElementById('member_type').addEventListener('change', function () {
        document.getElementById('cert-wrap').classList.toggle('hidden', this.value !== '2');
    });

    document.getElementById('logo').addEventListener('change', function () {
        const img = document.getElementById('logo-preview');
        if (this.files && this.files[0]) {
            img.src = URL.createObjectURL(this.files[0]);
            img.classList.remove('hidden');
        } else {
            img.classList.add('hidden');
        }
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
        if (this.value) selected('/api/districtById/' + this.value, 'districtById', 'district-dropdown');
    });
    document.getElementById('district-dropdown').addEventListener('change', function () {
        if (this.value) selected('/api/upazilaById/' + this.value, 'upazilaById', 'upazila-dropdown');
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();
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
                document.getElementById('logo-preview').classList.add('hidden');
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
            submitBtn.textContent = 'Submit';
        });
    });
})();
</script>
