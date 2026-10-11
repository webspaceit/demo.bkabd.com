<?php
/**
 * Student registration form — GET /student-registration.
 * Polished multi-section layout: a branded header band, four numbered
 * sections, cascading Center→Institute / Division→District→Upazila→Union
 * selects and an inline photo upload with preview. Saves via
 * POST /api/students (multipart FormData + BK.submit), then shows the
 * printable registration card link in a toast and resets for the next
 * student (keeping the chosen session).
 */
$currentSession = (string) ($activeSession ?? '');
$selSession = $currentSession; // only the current session is selectable
$req = '<span class="text-red-500">*</span>';
?>
<div class="max-w-4xl mx-auto">

    <!-- Header band -->
    <div class="bg-gradient-to-br from-emerald-600 via-emerald-700 to-emerald-900 rounded-2xl shadow-lg shadow-emerald-900/10 ring-1 ring-emerald-900/10 mb-6 overflow-hidden">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="absolute -right-10 -top-14 w-52 h-52 rounded-full bg-white/5 pointer-events-none" aria-hidden="true"></div>
            <div class="absolute -right-2 -top-4 w-24 h-24 rounded-full bg-white/5 pointer-events-none" aria-hidden="true"></div>
            <div class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-14 h-14 rounded-2xl bg-white/15 border border-white/25 flex items-center justify-center shrink-0 shadow-inner">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-2xl font-bold text-white tracking-tight">Student Registration</h1>
                        <p class="text-sm text-emerald-100/90 mt-1">Register a new examinee for the BKABD Scholarship Examination.</p>
                    </div>
                </div>
                <div class="shrink-0 flex sm:flex-col items-center sm:items-end gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 border border-white/25 px-3 py-1 text-xs font-semibold text-emerald-50">
                        <svg class="w-3.5 h-3.5 text-emerald-200" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                        Session <?= e($selSession !== '' ? $selSession : '—') ?>
                    </span>
                    <span class="text-xs text-emerald-100/70">Registration No. is generated automatically.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Form card -->
    <div class="card overflow-hidden">
        <form id="student-register-form" method="post" action="/api/students">
            <?= csrf_field() ?>

            <!-- Section 1 — Student Information -->
            <section class="px-6 pt-6 pb-5 sm:px-8">
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600/10 text-emerald-700 flex items-center justify-center text-sm font-bold">1</span>
                    <h2 class="font-semibold text-slate-800">Student Information</h2>
                    <span class="hidden md:inline ml-auto text-[11px] text-slate-400">Identity, contact &amp; personal details</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="label" for="name">Name <?= $req ?></label>
                        <input type="text" id="name" name="name" class="input" data-field="name" placeholder="Student's full name" value="<?= e((string) old('name')) ?>" required>
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>
                    <div>
                        <label class="label" for="father_name">Father's Name <?= $req ?></label>
                        <input type="text" id="father_name" name="father_name" class="input" data-field="father_name" placeholder="Father's name" value="<?= e((string) old('father_name')) ?>" required>
                        <p class="field-error hidden" data-error-for="father_name"></p>
                    </div>
                    <div>
                        <label class="label" for="mother_name">Mother's Name</label>
                        <input type="text" id="mother_name" name="mother_name" class="input" data-field="mother_name" placeholder="Mother's name" value="<?= e((string) old('mother_name')) ?>">
                        <p class="field-error hidden" data-error-for="mother_name"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div>
                        <label class="label" for="phone">Phone <?= $req ?></label>
                        <input type="text" id="phone" name="phone" class="input" data-field="phone" placeholder="01XXXXXXXXX" maxlength="11" inputmode="numeric" autocomplete="tel" value="<?= e((string) old('phone')) ?>" required>
                        <p class="field-error hidden" data-error-for="phone"></p>
                    </div>
                    <div>
                        <label class="label" for="dob">Date of Birth</label>
                        <input type="text" id="dob" name="dob" class="input" data-field="dob" data-datepicker autocomplete="off" placeholder="DD-MM-YYYY" value="<?= e((string) old('dob')) ?>">
                        <p class="field-error hidden" data-error-for="dob"></p>
                    </div>
                    <div>
                        <label class="label" for="religion">Religion</label>
                        <select id="religion" name="religion" class="input" data-field="religion">
                            <option value="">Select</option>
                            <option value="1" <?= old('religion') == 1 ? 'selected' : '' ?>>Islam</option>
                            <option value="2" <?= old('religion') == 2 ? 'selected' : '' ?>>Hindu</option>
                            <option value="3" <?= old('religion') == 3 ? 'selected' : '' ?>>Buddhist</option>
                            <option value="4" <?= old('religion') == 4 ? 'selected' : '' ?>>Christian</option>
                        </select>
                        <p class="field-error hidden" data-error-for="religion"></p>
                    </div>
                    <div>
                        <label class="label" for="gender">Gender <?= $req ?></label>
                        <select id="gender" name="gender" class="input" data-field="gender" required>
                            <option value="">Select</option>
                            <option value="1" <?= old('gender') == 1 ? 'selected' : '' ?>>Male</option>
                            <option value="2" <?= old('gender') == 2 ? 'selected' : '' ?>>Female</option>
                            <option value="3" <?= old('gender') == 3 ? 'selected' : '' ?>>Others</option>
                        </select>
                        <p class="field-error hidden" data-error-for="gender"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="label" for="present_address">Present Address <?= $req ?></label>
                        <input type="text" id="present_address" name="present_address" class="input" data-field="present_address" placeholder="Present address" value="<?= e((string) old('present_address')) ?>" required>
                        <p class="field-error hidden" data-error-for="present_address"></p>
                    </div>
                    <div>
                        <label class="label" for="permanent_address">Permanent Address</label>
                        <input type="text" id="permanent_address" name="permanent_address" class="input" data-field="permanent_address" placeholder="Permanent address" value="<?= e((string) old('permanent_address')) ?>">
                        <p class="field-error hidden" data-error-for="permanent_address"></p>
                    </div>
                </div>
            </section>

            <hr class="border-slate-100">

            <!-- Section 2 — Academic Details -->
            <section class="px-6 pt-6 pb-5 sm:px-8">
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600/10 text-emerald-700 flex items-center justify-center text-sm font-bold">2</span>
                    <h2 class="font-semibold text-slate-800">Academic Details</h2>
                    <span class="hidden md:inline ml-auto text-[11px] text-slate-400">Class, session &amp; medium of education</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="label" for="class">Class <?= $req ?></label>
                        <select id="class" name="class" class="input" data-field="class" required>
                            <option value="">Select</option>
                            <?php foreach ([1=>'ONE',2=>'TWO',3=>'THREE',4=>'FOUR',5=>'FIVE',6=>'KG'] as $code=>$name): ?>
                                <option value="<?= $code ?>" <?= old('class') == $code ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-error hidden" data-error-for="class"></p>
                    </div>
                    <div>
                        <label class="label" for="session">Session <?= $req ?></label>
                        <select id="session" name="session" class="input" data-field="session" required>
                            <?php foreach ($sessions as $s): $isCurrent = (string) $s['session'] === $currentSession; ?>
                                <option value="<?= e($s['session']) ?>"<?= $isCurrent ? ' selected' : '' ?><?= $isCurrent ? '' : ' disabled' ?>>
                                    <?= e($s['session']) ?><?= $isCurrent ? ' (current)' : ((int) $s['active'] === 1 ? ' (active)' : '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-error hidden" data-error-for="session"></p>
                    </div>
                    <div>
                        <label class="label" for="education_type">Education Type</label>
                        <select id="education_type" name="education_type" class="input" data-field="education_type">
                            <option value="">Select</option>
                            <option value="1" <?= old('education_type') == 1 ? 'selected' : '' ?>>Bangla Medium</option>
                            <option value="2" <?= old('education_type') == 2 ? 'selected' : '' ?>>English Version</option>
                            <option value="3" <?= old('education_type') == 3 ? 'selected' : '' ?>>Arabic Version</option>
                        </select>
                        <p class="field-error hidden" data-error-for="education_type"></p>
                    </div>
                </div>
            </section>

            <hr class="border-slate-100">

            <!-- Section 3 — Institute & Location -->
            <section class="px-6 pt-6 pb-5 sm:px-8">
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600/10 text-emerald-700 flex items-center justify-center text-sm font-bold">3</span>
                    <h2 class="font-semibold text-slate-800">Institute &amp; Location</h2>
                    <span class="hidden md:inline ml-auto text-[11px] text-slate-400">Center, institute &amp; address of the examinee</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="center_id">Center <?= $req ?></label>
                        <select id="center_id" name="center_id" class="input" data-field="center_id" required>
                            <option value="">Select Center</option>
                            <?php foreach ($centers as $c): ?>
                                <option value="<?= e($c['id']) ?>" <?= old('center_id') == $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-error hidden" data-error-for="center_id"></p>
                    </div>
                    <div>
                        <label class="label" for="institute_id">Institute <?= $req ?></label>
                        <select id="institute_id" name="institute_id" class="input" data-field="institute_id" required>
                            <option value="">
                                <?= old('center_id') ? 'Select Institute' : 'Select a Center first' ?>
                            </option>
                            <?php if (old('center_id')): ?>
                                <?php foreach ($institutes as $i): ?>
                                    <option value="<?= e($i['id']) ?>" <?= old('institute_id') == $i['id'] ? 'selected' : '' ?>>
                                        <?= e($i['name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <p class="field-error hidden" data-error-for="institute_id"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                    <div>
                        <label class="label" for="division_id">Division <?= $req ?></label>
                        <select id="division_id" name="division_id" class="input" data-field="division_id" required>
                            <option value="">Select</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= e($d['id']) ?>" <?= old('division_id') == $d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-error hidden" data-error-for="division_id"></p>
                    </div>
                    <div>
                        <label class="label" for="district_id">District</label>
                        <select id="district_id" name="district_id" class="input" data-field="district_id">
                            <option value=""><?= old('division_id') ? 'Select' : 'Select Division first' ?></option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= e($d['id']) ?>" <?= old('district_id') == $d['id'] ? 'selected' : '' ?>>
                                    <?= e($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-error hidden" data-error-for="district_id"></p>
                    </div>
                    <div>
                        <label class="label" for="upazila_id">Upazila</label>
                        <select id="upazila_id" name="upazila_id" class="input" data-field="upazila_id">
                            <option value=""><?= old('district_id') ? 'Select' : 'Select District first' ?></option>
                            <?php foreach ($upazilas as $u): ?>
                                <option value="<?= e($u['id']) ?>" <?= old('upazila_id') == $u['id'] ? 'selected' : '' ?>>
                                    <?= e($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-error hidden" data-error-for="upazila_id"></p>
                    </div>
                    <div>
                        <label class="label" for="union_id">Union</label>
                        <select id="union_id" name="union_id" class="input" data-field="union_id">
                            <option value=""><?= old('upazila_id') ? 'Select' : 'Select Upazila first' ?></option>
                            <?php foreach ($unions as $un): ?>
                                <option value="<?= e($un['id']) ?>" <?= old('union_id') == $un['id'] ? 'selected' : '' ?>>
                                    <?= e($un['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field-error hidden" data-error-for="union_id"></p>
                    </div>
                </div>
            </section>

            <hr class="border-slate-100">

            <!-- Section 4 — Additional Information & Photo -->
            <section class="px-6 pt-6 pb-6 sm:px-8">
                <div class="flex items-center gap-2.5 mb-5">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600/10 text-emerald-700 flex items-center justify-center text-sm font-bold">4</span>
                    <h2 class="font-semibold text-slate-800">Additional Information &amp; Photo</h2>
                    <span class="hidden md:inline ml-auto text-[11px] text-slate-400">Optional details &amp; examinee photo</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="principal_name">Principal Name</label>
                        <input type="text" id="principal_name" name="principal_name" class="input" data-field="principal_name" placeholder="Institute principal's name" value="<?= e((string) old('principal_name')) ?>">
                        <p class="field-error hidden" data-error-for="principal_name"></p>
                    </div>

                    <div>
                        <label class="label" for="photo-input">Photo</label>
                        <label for="photo-input" class="flex items-center gap-4 cursor-pointer rounded-lg border-2 border-dashed border-slate-300 bg-slate-50/60 px-4 py-3 transition hover:border-emerald-400 hover:bg-emerald-50/40">
                            <input type="file" id="photo-input" name="photo" accept="image/jpeg,image/png,image/gif" data-field="photo" class="sr-only">
                            <img id="photo-preview" alt="Photo preview" class="hidden w-12 h-14 object-cover rounded-md ring-1 ring-slate-200 shrink-0">
                            <span id="photo-placeholder" class="flex flex-col gap-0.5 min-w-0">
                                <span class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                                    <span id="photo-name" class="truncate">Click to choose a photo</span>
                                </span>
                                <span class="text-xs text-slate-400 pl-5">jpeg, png, gif · max 2MB</span>
                            </span>
                        </label>
                        <p class="field-error hidden" data-error-for="photo"></p>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="label" for="note">Notes</label>
                    <textarea id="note" name="note" class="input" data-field="note" placeholder="Any additional information (optional)" rows="3"><?= e((string) old('note')) ?></textarea>
                    <p class="field-error hidden" data-error-for="note"></p>
                </div>
            </section>

            <!-- Submit -->
            <div class="px-6 py-5 sm:px-8 bg-slate-50/70 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <p class="text-xs text-slate-400 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        Fields marked with <span class="text-red-500">*</span> are required. The registration card opens automatically after saving.
                    </p>
                    <button type="submit" name="register" class="btn btn-primary px-8 py-2.5 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
                        Register Student
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var form = document.getElementById('student-register-form');
    if (!form) { return; }
    var $ = function (sel) { return form.querySelector(sel); };

    // ---------------------------------------------------------------
    // Cascading selects: Center -> Institute, Division -> District ->
    // Upazila -> Union. Only reacts to user changes; the server already
    // restores every select on a validation round-trip.
    // ---------------------------------------------------------------
    var centerEl = $('[name="center_id"]');
    var instituteEl = $('[name="institute_id"]');
    var divisionEl = $('[name="division_id"]');
    var districtEl = $('[name="district_id"]');
    var upazilaEl = $('[name="upazila_id"]');
    var unionEl = $('[name="union_id"]');

    function fillOptions(select, items, placeholderText) {
        var prev = select.value;
        select.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholderText || 'Select';
        select.appendChild(ph);
        (items || []).forEach(function (item) {
            var o = document.createElement('option');
            o.value = String(item.id);
            o.textContent = item.name || '';
            select.appendChild(o);
        });
        if (prev && Array.from(select.options).some(function (o) { return o.value === prev; })) {
            select.value = prev; // keep previous selection when the parent didn't change
        }
    }

    async function load(url, select, placeholderText) {
        try {
            var json = await BK.get(url);
            var key = 'instituteList';
            if (select === districtEl) { key = 'districtById'; }
            else if (select === upazilaEl) { key = 'upazilaById'; }
            else if (select === unionEl) { key = 'unionById'; }
            var items = json && json.data ? json.data[key] : (json[key] || []);
            fillOptions(select, items, placeholderText);
        } catch (err) {
            BK.toast(err.message || 'Could not load options', 'error');
        }
    }

    centerEl.addEventListener('change', function () {
        fillOptions(instituteEl, [], 'Select Institute');
        var id = centerEl.value;
        if (!id) { return; }
        load('/api/instituteListByCenter?center_id=' + encodeURIComponent(id), instituteEl, 'Select Institute');
    });

    divisionEl.addEventListener('change', function () {
        fillOptions(districtEl, [], 'Select District');
        fillOptions(upazilaEl, [], 'Select District first');
        fillOptions(unionEl, [], 'Select Upazila first');
        var id = divisionEl.value;
        if (!id) { return; }
        load('/api/districtById/' + encodeURIComponent(id), districtEl, 'Select District');
    });

    districtEl.addEventListener('change', function () {
        fillOptions(upazilaEl, [], 'Select Upazila');
        fillOptions(unionEl, [], 'Select Upazila first');
        var id = districtEl.value;
        if (!id) { return; }
        load('/api/upazilaById/' + encodeURIComponent(id), upazilaEl, 'Select Upazila');
    });

    upazilaEl.addEventListener('change', function () {
        fillOptions(unionEl, [], 'Select Union');
        var id = upazilaEl.value;
        if (!id) { return; }
        load('/api/unionById/' + encodeURIComponent(id), unionEl, 'Select Union');
    });

    // ---------------------------------------------------------------
    // Photo picker preview
    // ---------------------------------------------------------------
    var photoInput = $('[name="photo"]');
    var photoPreview = document.getElementById('photo-preview');
    var photoName = document.getElementById('photo-name');
    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function () {
            var file = photoInput.files && photoInput.files[0];
            if (!file) {
                photoPreview.classList.add('hidden');
                if (photoName) { photoName.textContent = 'Click to choose a photo'; }
                return;
            }
            photoPreview.src = URL.createObjectURL(file);
            photoPreview.classList.remove('hidden');
            if (photoName) { photoName.textContent = file.name; }
        });
    }

    // ---------------------------------------------------------------
    // Submit (multipart) -> toast with the card link -> reset (keep session)
    // ---------------------------------------------------------------
    var sessionEl = $('[name="session"]');
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            var json = await BK.post('/api/students', new FormData(form));
            var id = json && json.data ? json.data.id : null;
            var link = id ? { href: '/api/registrationpdf/' + encodeURIComponent(id), label: 'Open card' } : null;
            BK.toast('Student registered successfully.', 'success', link);

            var sessionValue = sessionEl ? sessionEl.value : '';
            form.reset();
            // form.reset() clears the hidden field, not flatpickr's visible input.
            document.querySelectorAll('input[data-datepicker]').forEach(function (el) {
                if (el._flatpickr) { el._flatpickr.clear(); }
            });
            if (sessionValue && sessionEl) { sessionEl.value = sessionValue; }
            fillOptions(instituteEl, [], 'Select a Center first');
            fillOptions(districtEl, [], 'Select Division first');
            fillOptions(upazilaEl, [], 'Select District first');
            fillOptions(unionEl, [], 'Select Upazila first');
            if (photoPreview) {
                photoPreview.src = '';
                photoPreview.classList.add('hidden');
                if (photoName) { photoName.textContent = 'Click to choose a photo'; }
            }
            if (sessionEl) { sessionEl.focus({ preventScroll: true }); }
        });
    });
});
</script>