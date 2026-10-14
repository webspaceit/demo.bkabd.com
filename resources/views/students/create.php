<?php
/**
 * Student registration form — GET /student-registration.
 */
$currentSession = (string) ($activeSession ?? '');
$req = '<span class="text-red-500">*</span>';
// Date of birth: the form submits ISO Y-m-d (validated server-side), while the
// visible field shows DD-MM-YYYY through the decade-first picker.
$dobOld = (string) old('dob');
$dobIso = '';
$dobDisplay = '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobOld)) {
    [$dobY, $dobM, $dobD] = explode('-', $dobOld);
    $dobIso = $dobOld;
    $dobDisplay = $dobD . '-' . $dobM . '-' . $dobY;
}
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
                        Session <?= e($currentSession !== '' ? $currentSession : '—') ?>
                    </span>
                    <span class="text-xs text-emerald-100/70">Registration No. is generated automatically. New registrations are submitted as Pending.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Form card -->
    <div class="card">
        <form id="student-register-form" method="post" action="/api/students">
            <?= csrf_field() ?>

            <!-- Section 1 — Student Information -->
            <section class="px-6 pt-6 pb-5 sm:px-8">
                <div class="flex items-center gap-2.5 mb-6">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600/10 text-emerald-700 flex items-center justify-center text-sm font-bold">1</span>
                    <h2 class="font-semibold text-slate-800">Student Information</h2>
                    <span class="hidden md:inline ml-auto text-[11px] text-slate-400">Identity, contact &amp; personal details</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-5">
                    <div class="fl">
                        <input type="text" id="name" name="name" class="input" data-field="name" placeholder="Student's full name" value="<?= e((string) old('name')) ?>" required>
                        <label for="name">Name <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>
                    <div class="fl">
                        <input type="text" id="father_name" name="father_name" class="input" data-field="father_name" placeholder="Father's name" value="<?= e((string) old('father_name')) ?>" required>
                        <label for="father_name">Father's Name <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="father_name"></p>
                    </div>
                    <div class="fl">
                        <input type="text" id="mother_name" name="mother_name" class="input" data-field="mother_name" placeholder="Mother's name" value="<?= e((string) old('mother_name')) ?>">
                        <label for="mother_name">Mother's Name</label>
                        <p class="field-error hidden" data-error-for="mother_name"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-5 mt-5">
                    <div class="fl">
                        <input type="text" id="phone" name="phone" class="input" data-field="phone" placeholder="01XXXXXXXXX" maxlength="11" inputmode="numeric" autocomplete="tel" value="<?= e((string) old('phone')) ?>" required>
                        <label for="phone">Phone <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="phone"></p>
                    </div>
                    <div class="fl dob-field">
                        <div class="dob-field__wrap">
                            <span class="dob-field__icon" aria-hidden="true">
                                <svg fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 10h16m-8-3V4M7 7V4m10 3V4M5 20h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Zm3-7h.01v.01H8V13Zm4 0h.01v.01H12V13Zm4 0h.01v.01H16V13Zm-8 4h.01v.01H8V17Zm4 0h.01v.01H12V17Zm4 0h.01v.01H16V17Z"/></svg>
                            </span>
                            <input type="text" id="dob-display" data-dobpicker class="input" data-field="dob"
                                   placeholder="DD-MM-YYYY" autocomplete="off" readonly
                                   aria-haspopup="dialog" aria-expanded="false"
                                   value="<?= e($dobDisplay) ?>">
                        </div>
                        <input type="hidden" id="dob" name="dob" data-dobpicker-value value="<?= e($dobIso) ?>">
                        <label for="dob-display">Date of Birth</label>
                        <p class="field-error hidden" data-error-for="dob"></p>
                    </div>
                    <div class="fl">
                        <select id="religion" name="religion" class="input" data-field="religion">
                            <option value="">Select religion</option>
                            <option value="1" <?= old('religion') == 1 ? 'selected' : '' ?>>Islam</option>
                            <option value="2" <?= old('religion') == 2 ? 'selected' : '' ?>>Hindu</option>
                            <option value="3" <?= old('religion') == 3 ? 'selected' : '' ?>>Buddhist</option>
                            <option value="4" <?= old('religion') == 4 ? 'selected' : '' ?>>Christian</option>
                        </select>
                        <label for="religion">Religion</label>
                        <p class="field-error hidden" data-error-for="religion"></p>
                    </div>
                    <div class="fl">
                        <select id="gender" name="gender" class="input" data-field="gender" required>
                            <option value="">Select gender</option>
                            <option value="1" <?= old('gender') == 1 ? 'selected' : '' ?>>Male</option>
                            <option value="2" <?= old('gender') == 2 ? 'selected' : '' ?>>Female</option>
                            <option value="3" <?= old('gender') == 3 ? 'selected' : '' ?>>Others</option>
                        </select>
                        <label for="gender">Gender <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="gender"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-5 mt-5">
                    <div class="fl">
                        <input type="text" id="present_address" name="present_address" class="input" data-field="present_address" placeholder="House, road, area" value="<?= e((string) old('present_address')) ?>" required>
                        <label for="present_address">Present Address <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="present_address"></p>
                    </div>
                    <div class="fl">
                        <input type="text" id="permanent_address" name="permanent_address" class="input" data-field="permanent_address" placeholder="House, road, area" value="<?= e((string) old('permanent_address')) ?>">
                        <label for="permanent_address">Permanent Address</label>
                        <p class="field-error hidden" data-error-for="permanent_address"></p>
                    </div>
                </div>
            </section>

            <hr class="border-slate-100">

            <!-- Section 2 — Academic Details -->
            <section class="px-6 pt-6 pb-5 sm:px-8">
                <div class="flex items-center gap-2.5 mb-6">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600/10 text-emerald-700 flex items-center justify-center text-sm font-bold">2</span>
                    <h2 class="font-semibold text-slate-800">Academic Details</h2>
                    <span class="hidden md:inline ml-auto text-[11px] text-slate-400">Class, session &amp; medium of education</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-5">
                    <div class="fl">
                        <select id="class" name="class" class="input" data-field="class" required>
                            <option value="">Select class</option>
                            <?php foreach ([1=>'ONE',2=>'TWO',3=>'THREE',4=>'FOUR',5=>'FIVE',6=>'KG'] as $code=>$name): ?>
                                <option value="<?= $code ?>" <?= old('class') == $code ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="class">Class <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="class"></p>
                    </div>
                    <div class="fl">
                        <select id="session" name="session" class="input" data-field="session" required>
                            <?php foreach ($sessions as $s): $isCurrent = (string) $s['session'] === $currentSession; ?>
                                <option value="<?= e($s['session']) ?>"<?= $isCurrent ? ' selected' : '' ?><?= $isCurrent ? '' : ' disabled' ?>>
                                    <?= e($s['session']) ?><?= $isCurrent ? ' (current)' : ((int) $s['active'] === 1 ? ' (active)' : '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="session">Session <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="session"></p>
                    </div>
                    <div class="fl">
                        <select id="education_type" name="education_type" class="input" data-field="education_type">
                            <option value="">Select medium</option>
                            <option value="1" <?= old('education_type') == 1 ? 'selected' : '' ?>>Bangla Medium</option>
                            <option value="2" <?= old('education_type') == 2 ? 'selected' : '' ?>>English Version</option>
                            <option value="3" <?= old('education_type') == 3 ? 'selected' : '' ?>>Arabic Version</option>
                        </select>
                        <label for="education_type">Education Type</label>
                        <p class="field-error hidden" data-error-for="education_type"></p>
                    </div>
                </div>
            </section>

            <hr class="border-slate-100">

            <!-- Section 3 — Institute & Location -->
            <section class="px-6 pt-6 pb-5 sm:px-8">
                <div class="flex items-center gap-2.5 mb-6">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600/10 text-emerald-700 flex items-center justify-center text-sm font-bold">3</span>
                    <h2 class="font-semibold text-slate-800">Institute &amp; Location</h2>
                    <span class="hidden md:inline ml-auto text-[11px] text-slate-400">Center, institute &amp; address of the examinee</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-5">
                    <div class="fl">
                        <select id="center_id" name="center_id" class="input" data-field="center_id" required>
                            <option value="">Select center</option>
                            <?php foreach ($centers as $c): ?>
                                <option value="<?= e($c['id']) ?>" <?= old('center_id') == $c['id'] ? 'selected' : '' ?>><?= e($c['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="center_id">Center <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="center_id"></p>
                    </div>
                    <div class="fl">
                        <select id="institute_id" name="institute_id" class="input" data-field="institute_id" required>
                            <option value="">Select institute</option>
                            <?php if (old('center_id')): ?>
                                <?php foreach ($institutes as $i): ?>
                                    <option value="<?= e($i['id']) ?>" <?= old('institute_id') == $i['id'] ? 'selected' : '' ?>><?= e($i['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <label for="institute_id">Institute <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="institute_id"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-5 mt-5">
                    <div class="fl">
                        <select id="division_id" name="division_id" class="input" data-field="division_id" required>
                            <option value="">Select division</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= e($d['id']) ?>" <?= old('division_id') == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="division_id">Division <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="division_id"></p>
                    </div>
                    <div class="fl">
                        <select id="district_id" name="district_id" class="input" data-field="district_id">
                            <option value="">Select district</option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= e($d['id']) ?>" <?= old('district_id') == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="district_id">District</label>
                        <p class="field-error hidden" data-error-for="district_id"></p>
                    </div>
                    <div class="fl">
                        <select id="upazila_id" name="upazila_id" class="input" data-field="upazila_id">
                            <option value="">Select upazila</option>
                            <?php foreach ($upazilas as $u): ?>
                                <option value="<?= e($u['id']) ?>" <?= old('upazila_id') == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="upazila_id">Upazila</label>
                        <p class="field-error hidden" data-error-for="upazila_id"></p>
                    </div>
                    <div class="fl">
                        <select id="union_id" name="union_id" class="input" data-field="union_id">
                            <option value="">Select union</option>
                            <?php foreach ($unions as $un): ?>
                                <option value="<?= e($un['id']) ?>" <?= old('union_id') == $un['id'] ? 'selected' : '' ?>><?= e($un['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="union_id">Union</label>
                        <p class="field-error hidden" data-error-for="union_id"></p>
                    </div>
                </div>
            </section>

            <hr class="border-slate-100">

            <!-- Section 4 — Additional Information & Photo -->
            <section class="px-6 pt-6 pb-6 sm:px-8">
                <div class="flex items-center gap-2.5 mb-6">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600/10 text-emerald-700 flex items-center justify-center text-sm font-bold">4</span>
                    <h2 class="font-semibold text-slate-800">Additional Information &amp; Photo</h2>
                    <span class="hidden md:inline ml-auto text-[11px] text-slate-400">Optional details &amp; examinee photo</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-5">
                    <div class="fl">
                        <input type="text" id="principal_name" name="principal_name" class="input" data-field="principal_name" placeholder="Principal's name" value="<?= e((string) old('principal_name')) ?>">
                        <label for="principal_name">Principal Name</label>
                        <p class="field-error hidden" data-error-for="principal_name"></p>
                    </div>

                    <div>
                        <label class="label" for="photo-input">Student Photo</label>
                        <label for="photo-input" class="flex items-center gap-4 cursor-pointer rounded-lg border-2 border-dashed border-slate-300 bg-slate-50/60 px-4 py-3 transition hover:border-emerald-400 hover:bg-emerald-50/40">
                            <input type="file" id="photo-input" name="photo" accept="image/jpeg,image/png,image/gif" data-field="photo" class="sr-only">
                            <img id="photo-preview" alt="Photo preview" class="hidden w-12 h-14 object-cover rounded-md ring-1 ring-slate-200 shrink-0">
                            <span id="photo-placeholder" class="flex flex-col gap-0.5 min-w-0">
                                <span class="flex items-center gap-1.5 text-sm font-medium text-slate-700">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                                    <span id="photo-name" class="truncate">Click to choose a photo</span>
                                </span>
                                <span class="text-xs text-slate-400 pl-5">jpeg, png, gif &middot; auto-compressed to &le; 30 KB</span>
                            </span>
                        </label>
                        <p class="field-error hidden" data-error-for="photo"></p>
                    </div>
                </div>

                <div class="fl mt-5">
                    <textarea id="note" name="note" class="input" data-field="note" placeholder="Any additional notes" rows="3"><?= e((string) old('note')) ?></textarea>
                    <label for="note">Notes</label>
                    <p class="field-error hidden" data-error-for="note"></p>
                </div>
            </section>

            <!-- Submit -->
            <div class="px-6 py-5 sm:px-8 bg-slate-50/70 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <p class="text-xs text-slate-400 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        Fields marked with <span class="text-red-500">*</span> are required.
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

    var centerEl   = $('[name="center_id"]');
    var instituteEl = $('[name="institute_id"]');
    var divisionEl = $('[name="division_id"]');
    var districtEl = $('[name="district_id"]');
    var upazilaEl  = $('[name="upazila_id"]');
    var unionEl    = $('[name="union_id"]');

    function fillOptions(select, items, placeholderText) {
        var prev = select.value;
        select.innerHTML = '<option value="">' + (placeholderText || '') + '</option>';
        (items || []).forEach(function (item) {
            var o = document.createElement('option');
            o.value = String(item.id);
            o.textContent = item.name || '';
            select.appendChild(o);
        });
        if (prev && Array.from(select.options).some(function (o) { return o.value === prev; })) {
            select.value = prev;
        }
    }

    async function load(url, select, placeholder) {
        try {
            var json = await BK.get(url);
            var key = select === districtEl ? 'districtById'
                    : select === upazilaEl  ? 'upazilaById'
                    : select === unionEl    ? 'unionById'
                    : 'instituteList';
            var items = json && json.data ? json.data[key] : (json[key] || []);
            fillOptions(select, items, placeholder);
        } catch (err) {
            BK.toast(err.message || 'Could not load options', 'error');
        }
    }

    centerEl.addEventListener('change', function () {
        fillOptions(instituteEl, []);
        if (centerEl.value) { load('/api/instituteListByCenter?center_id=' + encodeURIComponent(centerEl.value), instituteEl); }
    });
    divisionEl.addEventListener('change', function () {
        fillOptions(districtEl, []); fillOptions(upazilaEl, []); fillOptions(unionEl, []);
        if (divisionEl.value) { load('/api/districtById/' + encodeURIComponent(divisionEl.value), districtEl); }
    });
    districtEl.addEventListener('change', function () {
        fillOptions(upazilaEl, []); fillOptions(unionEl, []);
        if (districtEl.value) { load('/api/upazilaById/' + encodeURIComponent(districtEl.value), upazilaEl); }
    });
    upazilaEl.addEventListener('change', function () {
        fillOptions(unionEl, []);
        if (upazilaEl.value) { load('/api/unionById/' + encodeURIComponent(upazilaEl.value), unionEl); }
    });

    // Photo upload — compressed to a JPEG <= 30 KB in the browser before it is
    // submitted (the server re-checks and compresses again, so the stored file
    // is always within the limit even without JavaScript).
    var photoInput   = $('[name="photo"]');
    var photoPreview = document.getElementById('photo-preview');
    var photoName    = document.getElementById('photo-name');
    var PHOTO_MAX_BYTES = 30 * 1024; // 30 KB
    var PHOTO_MAX_DIM   = 600;

    function photoSizeLabel(bytes) {
        return bytes < 1024 ? bytes + ' B' : Math.round(bytes / 1024) + ' KB';
    }

    function loadPhoto(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('Cannot read image')); };
            img.src = url;
        });
    }

    // Downscale + lower JPEG quality until the encoded blob fits in 30 KB.
    function compressPhoto(file) {
        return loadPhoto(file).then(function (img) {
            var sw = img.naturalWidth || img.width;
            var sh = img.naturalHeight || img.height;
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            var best = null;
            var steps = 0;

            var draw = function (w, h, quality) {
                canvas.width = w;
                canvas.height = h;
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                return new Promise(function (resolve) { canvas.toBlob(resolve, 'image/jpeg', quality); });
            };

            var attempt = function (w, h, quality) {
                return draw(w, h, quality).then(function (blob) {
                    if (blob && (!best || blob.size < best.size)) { best = blob; }
                    if (blob && blob.size <= PHOTO_MAX_BYTES) { return blob; }
                    if (quality > 0.4) {
                        return attempt(w, h, quality - 0.1);
                    }
                    if (steps++ < 24 && w > 100 && h > 100) {
                        return attempt(Math.round(w * 0.8), Math.round(h * 0.8), 0.7);
                    }
                    return best;
                });
            };

            var scale = Math.min(1, PHOTO_MAX_DIM / Math.max(sw, sh));
            return attempt(Math.max(1, Math.round(sw * scale)), Math.max(1, Math.round(sh * scale)), 0.85)
                .then(function (blob) {
                    if (!blob) { return file; }
                    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
                });
        });
    }

    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function () {
            var file = photoInput.files && photoInput.files[0];
            if (!file) {
                photoPreview.classList.add('hidden');
                photoPreview.removeAttribute('src');
                if (photoName) { photoName.textContent = 'Click to choose a photo'; }
                return;
            }
            photoPreview.classList.remove('hidden');
            photoPreview.src = URL.createObjectURL(file);
            if (photoName) { photoName.textContent = 'Compressing\u2026'; }

            compressPhoto(file).then(function (out) {
                try {
                    var dt = new DataTransfer();
                    dt.items.add(out);
                    photoInput.files = dt.files;
                } catch (err) { /* older browser: the server still compresses */ }
                photoPreview.src = URL.createObjectURL(out);
                if (photoName) { photoName.textContent = out.name + ' \u00b7 ' + photoSizeLabel(out.size); }
            }).catch(function () {
                if (photoName) { photoName.textContent = file.name + ' \u00b7 ' + photoSizeLabel(file.size); }
            });
        });
    }

    // Submit
    var sessionEl = $('[name="session"]');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        BK.submit(form, async function () {
            var json = await BK.post('/api/students', new FormData(form));
            // The card endpoint resolves students by REGISTRATION number (the
            // route segment is {id}, but it is not the row id).
            var reg  = json && json.data ? json.data.registration : null;
            var link = reg ? { href: '/api/registrationpdf/' + encodeURIComponent(reg), label: 'Open card' } : null;
            // New registrations are created PENDING and wait in the
            // /pending-students queue until they are approved.
            BK.toast((json && json.message) || 'Student registered successfully.', 'success', link);
            var sv = sessionEl ? sessionEl.value : '';
            form.reset();
            if (sv && sessionEl) { sessionEl.value = sv; }
            fillOptions(instituteEl, []); fillOptions(districtEl, []); fillOptions(upazilaEl, []); fillOptions(unionEl, []);
            if (photoPreview) { photoPreview.src = ''; photoPreview.classList.add('hidden'); }
            if (photoName)    { photoName.textContent = 'Click to choose a photo'; }
            if (sessionEl)    { sessionEl.focus({ preventScroll: true }); }
        });
    });
});
</script>
