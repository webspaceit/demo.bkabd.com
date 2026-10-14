<?php
/**
 * Edit student — GET /edit-student/{id}
 * Mirrors the registration form layout exactly.
 */
$v = fn (string $key) => e((string) (old($key, $student[$key] ?? '') ?? ''));
$req = '<span class="text-red-500">*</span>';
$currentSession = (string) ($activeSession ?? '');
?>
<div class="max-w-4xl mx-auto">

    <!-- Header band -->
    <div class="bg-gradient-to-br from-emerald-600 via-emerald-700 to-emerald-900 rounded-2xl shadow-lg shadow-emerald-900/10 ring-1 ring-emerald-900/10 mb-6 overflow-hidden">
        <div class="relative px-6 py-7 sm:px-8">
            <div class="absolute -right-10 -top-14 w-52 h-52 rounded-full bg-white/5 pointer-events-none"></div>
            <div class="absolute -right-2 -top-4 w-24 h-24 rounded-full bg-white/5 pointer-events-none"></div>
            <div class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="w-14 h-14 rounded-2xl bg-white/15 border border-white/25 flex items-center justify-center shrink-0 shadow-inner">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-2xl font-bold text-white tracking-tight">Edit Student</h1>
                        <p class="text-sm text-emerald-100/90 mt-1"><?= e($student['name'] ?? '') ?> &mdash; Reg: <?= e($student['registration'] ?? '') ?></p>
                    </div>
                </div>
                <a href="/students" class="shrink-0 inline-flex items-center gap-1.5 rounded-full bg-white/15 border border-white/25 px-4 py-1.5 text-xs font-semibold text-emerald-50 hover:bg-white/25 transition">
                    &larr; Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Form card -->
    <div class="card">
        <form id="student-edit-form" method="post" action="/api/students/<?= (int) $student['id'] ?>"
              data-id="<?= (int) $student['id'] ?>">
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
                        <input type="text" name="name" class="input" data-field="name" placeholder=" " value="<?= $v('name') ?>" required>
                        <label>Name <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="name"></p>
                    </div>
                    <div class="fl">
                        <input type="text" name="father_name" class="input" data-field="father_name" placeholder=" " value="<?= $v('father_name') ?>" required>
                        <label>Father's Name <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="father_name"></p>
                    </div>
                    <div class="fl">
                        <input type="text" name="mother_name" class="input" data-field="mother_name" placeholder=" " value="<?= $v('mother_name') ?>">
                        <label>Mother's Name</label>
                        <p class="field-error hidden" data-error-for="mother_name"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-5 mt-5">
                    <div class="fl">
                        <input type="text" name="phone" class="input" data-field="phone" placeholder=" " maxlength="11" inputmode="numeric" value="<?= $v('phone') ?>" required>
                        <label>Phone <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="phone"></p>
                    </div>
                    <div class="fl">
                        <input type="date" name="dob" class="input" data-field="dob" max="<?= date('Y-m-d') ?>" value="<?= $v('dob') ?>">
                        <label>Date of Birth</label>
                        <p class="field-error hidden" data-error-for="dob"></p>
                    </div>
                    <div class="fl">
                        <select name="religion" class="input" data-field="religion">
                            <option value=""></option>
                            <option value="1" <?= $v('religion') == 1 ? 'selected' : '' ?>>Islam</option>
                            <option value="2" <?= $v('religion') == 2 ? 'selected' : '' ?>>Hindu</option>
                            <option value="3" <?= $v('religion') == 3 ? 'selected' : '' ?>>Buddhist</option>
                            <option value="4" <?= $v('religion') == 4 ? 'selected' : '' ?>>Christian</option>
                        </select>
                        <label>Religion</label>
                        <p class="field-error hidden" data-error-for="religion"></p>
                    </div>
                    <div class="fl">
                        <select name="gender" class="input" data-field="gender" required>
                            <option value=""></option>
                            <option value="1" <?= $v('gender') == 1 ? 'selected' : '' ?>>Male</option>
                            <option value="2" <?= $v('gender') == 2 ? 'selected' : '' ?>>Female</option>
                            <option value="3" <?= $v('gender') == 3 ? 'selected' : '' ?>>Others</option>
                        </select>
                        <label>Gender <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="gender"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-5 mt-5">
                    <div class="fl">
                        <input type="text" name="present_address" class="input" data-field="present_address" placeholder=" " value="<?= $v('present_address') ?>" required>
                        <label>Present Address <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="present_address"></p>
                    </div>
                    <div class="fl">
                        <input type="text" name="permanent_address" class="input" data-field="permanent_address" placeholder=" " value="<?= $v('permanent_address') ?>">
                        <label>Permanent Address</label>
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
                        <select name="class" class="input" data-field="class" required>
                            <option value=""></option>
                            <?php foreach ([1=>'ONE',2=>'TWO',3=>'THREE',4=>'FOUR',5=>'FIVE',6=>'KG'] as $code=>$name): ?>
                                <option value="<?= $code ?>" <?= $v('class') == $code ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Class <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="class"></p>
                    </div>
                    <div class="fl">
                        <select name="session" class="input" data-field="session" required>
                            <?php foreach ($sessions as $s):
                                $isCurrent = (string) $s['session'] === $currentSession;
                                $isStudent = (string) ($student['session'] ?? '') === (string) $s['session'];
                                $enabled   = $isCurrent || $isStudent;
                            ?>
                                <option value="<?= e($s['session']) ?>"<?= $isStudent ? ' selected' : '' ?><?= $enabled ? '' : ' disabled' ?>>
                                    <?= e($s['session']) ?><?= $isCurrent ? ' (current)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label>Session <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="session"></p>
                    </div>
                    <div class="fl">
                        <select name="education_type" class="input" data-field="education_type">
                            <option value=""></option>
                            <option value="1" <?= $v('education_type') == 1 ? 'selected' : '' ?>>Bangla Medium</option>
                            <option value="2" <?= $v('education_type') == 2 ? 'selected' : '' ?>>English Version</option>
                            <option value="3" <?= $v('education_type') == 3 ? 'selected' : '' ?>>Arabic Version</option>
                        </select>
                        <label>Education Type</label>
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
                        <select name="center_id" class="input" data-field="center_id" required>
                            <option value=""></option>
                            <?php foreach ($centers as $c): ?>
                                <option value="<?= e($c['id']) ?>" <?= $v('center_id') == $c['id'] ? 'selected' : '' ?>><?= e($c['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Center <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="center_id"></p>
                    </div>
                    <div class="fl">
                        <select name="institute_id" class="input" data-field="institute_id" required>
                            <option value=""></option>
                            <?php foreach ($institutes as $i): ?>
                                <option value="<?= e($i['id']) ?>" <?= $v('institute_id') == $i['id'] ? 'selected' : '' ?>><?= e($i['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Institute <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="institute_id"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-4 gap-y-5 mt-5">
                    <div class="fl">
                        <select name="division_id" class="input" data-field="division_id" required>
                            <option value=""></option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= e($d['id']) ?>" <?= $v('division_id') == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Division <?= $req ?></label>
                        <p class="field-error hidden" data-error-for="division_id"></p>
                    </div>
                    <div class="fl">
                        <select name="district_id" class="input" data-field="district_id">
                            <option value=""></option>
                            <?php foreach ($districts as $d): ?>
                                <option value="<?= e($d['id']) ?>" <?= $v('district_id') == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>District</label>
                        <p class="field-error hidden" data-error-for="district_id"></p>
                    </div>
                    <div class="fl">
                        <select name="upazila_id" class="input" data-field="upazila_id">
                            <option value=""></option>
                            <?php foreach ($upazilas as $u): ?>
                                <option value="<?= e($u['id']) ?>" <?= $v('upazila_id') == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Upazila</label>
                        <p class="field-error hidden" data-error-for="upazila_id"></p>
                    </div>
                    <div class="fl">
                        <select name="union_id" class="input" data-field="union_id">
                            <option value=""></option>
                            <?php foreach ($unions as $un): ?>
                                <option value="<?= e($un['id']) ?>" <?= $v('union_id') == $un['id'] ? 'selected' : '' ?>><?= e($un['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label>Union</label>
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
                        <input type="text" name="principal_name" class="input" data-field="principal_name" placeholder=" " value="<?= $v('principal_name') ?>">
                        <label>Principal Name</label>
                        <p class="field-error hidden" data-error-for="principal_name"></p>
                    </div>

                    <!-- Photo -->
                    <div>
                        <label class="label">Student Photo</label>
                        <div class="flex items-center gap-3">
                            <?php if (!empty($student['photo'])): ?>
                                <div style="position:relative;display:inline-block;flex-shrink:0;">
                                    <img id="photo-current" src="/<?= e($student['photo']) ?>"
                                         alt="Current photo"
                                         class="w-14 h-16 object-cover rounded-md ring-1 ring-slate-200">
                                    <button type="button" id="btn-delete-photo"
                                            style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#ef4444;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;line-height:1;"
                                            title="Delete photo">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div id="photo-placeholder-icon" class="w-14 h-16 rounded-md ring-1 ring-slate-200 bg-slate-100 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
                                </div>
                            <?php endif; ?>
                            <label for="photo-input" class="flex-1 flex items-center gap-3 cursor-pointer rounded-lg border-2 border-dashed border-slate-300 bg-slate-50/60 px-3 py-2.5 transition hover:border-emerald-400 hover:bg-emerald-50/40">
                                <input type="file" id="photo-input" name="photo" accept="image/jpeg,image/png,image/gif" data-field="photo" class="sr-only">
                                <img id="photo-preview" alt="Preview" class="hidden w-10 h-12 object-cover rounded shrink-0">
                                <span class="flex flex-col gap-0.5 min-w-0">
                                    <span class="text-sm font-medium text-slate-700 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                                        <span id="photo-name">Change photo</span>
                                    </span>
                                    <span class="text-xs text-slate-400 pl-5">jpeg, png, gif · max 2MB</span>
                                </span>
                            </label>
                        </div>
                        <p class="field-error hidden mt-1" data-error-for="photo"></p>
                    </div>
                </div>

                <div class="fl mt-5">
                    <textarea name="note" class="input" data-field="note" placeholder=" " rows="3"><?= $v('note') ?></textarea>
                    <label>Notes</label>
                    <p class="field-error hidden" data-error-for="note"></p>
                </div>
            </section>

            <!-- Submit -->
            <div class="px-6 py-5 sm:px-8 bg-slate-50/70 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <p class="text-xs text-slate-400 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        Fields marked with <span class="text-red-500">*</span> are required.
                    </p>
                    <button type="submit" name="update" class="btn btn-primary px-8 py-2.5 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Update Student
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const form = document.getElementById('student-edit-form');
    if (!form) { return; }
    const id = form.getAttribute('data-id');

    // Cascading center → institute
    const centerEl    = form.querySelector('[name="center_id"]');
    const instituteEl = form.querySelector('[name="institute_id"]');
    const divisionEl  = form.querySelector('[name="division_id"]');
    const districtEl  = form.querySelector('[name="district_id"]');
    const upazilaEl   = form.querySelector('[name="upazila_id"]');
    const unionEl     = form.querySelector('[name="union_id"]');

    function fillOptions(select, items) {
        const prev = select.value;
        select.innerHTML = '<option value=""></option>';
        (items || []).forEach(function (item) {
            const o = document.createElement('option');
            o.value = String(item.id);
            o.textContent = item.name || '';
            select.appendChild(o);
        });
        if (prev && Array.from(select.options).some(o => o.value === prev)) {
            select.value = prev;
        }
    }

    async function load(url, select, key) {
        try {
            const json = await BK.get(url);
            fillOptions(select, json && json.data ? json.data[key] : (json[key] || []));
        } catch (err) { BK.toast(err.message || 'Could not load options', 'error'); }
    }

    centerEl.addEventListener('change', function () {
        fillOptions(instituteEl, []);
        if (centerEl.value) { load('/api/instituteListByCenter?center_id=' + encodeURIComponent(centerEl.value), instituteEl, 'instituteList'); }
    });
    divisionEl.addEventListener('change', function () {
        fillOptions(districtEl, []); fillOptions(upazilaEl, []); fillOptions(unionEl, []);
        if (divisionEl.value) { load('/api/districtById/' + encodeURIComponent(divisionEl.value), districtEl, 'districtById'); }
    });
    districtEl.addEventListener('change', function () {
        fillOptions(upazilaEl, []); fillOptions(unionEl, []);
        if (districtEl.value) { load('/api/upazilaById/' + encodeURIComponent(districtEl.value), upazilaEl, 'upazilaById'); }
    });
    upazilaEl.addEventListener('change', function () {
        fillOptions(unionEl, []);
        if (upazilaEl.value) { load('/api/unionById/' + encodeURIComponent(upazilaEl.value), unionEl, 'unionById'); }
    });

    // Delete photo
    const btnDeletePhoto = document.getElementById('btn-delete-photo');
    if (btnDeletePhoto) {
        btnDeletePhoto.addEventListener('click', function () {
            // Show inline confirm toast
            const stack = document.getElementById('toast-stack');
            if (!stack) return;
            const el = document.createElement('div');
            el.className = 'rounded px-4 py-2.5 text-sm shadow-lg bg-slate-800 text-white';
            el.style.cssText = 'display:flex;align-items:center;gap:12px;';
            el.innerHTML = '<span>Delete this photo from the server?</span>'
                + '<button id="toast-confirm-yes" style="font-weight:700;text-decoration:underline;color:inherit;background:none;border:none;cursor:pointer;padding:0;">Yes, delete</button>'
                + '<button id="toast-confirm-no" style="text-decoration:underline;color:inherit;background:none;border:none;cursor:pointer;padding:0;">Cancel</button>';
            stack.appendChild(el);
            const removeEl = () => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); };
            el.style.transition = 'opacity .3s';
            document.getElementById('toast-confirm-no').addEventListener('click', removeEl);
            document.getElementById('toast-confirm-yes').addEventListener('click', async function () {
                removeEl();
                try {
                    await BK.post('/api/students/' + id + '/delete-photo', new FormData());
                    const wrap = btnDeletePhoto.closest('[style*="position:relative"]');
                    if (wrap) {
                        const placeholder = document.createElement('div');
                        placeholder.style.cssText = 'width:56px;height:64px;border-radius:6px;box-shadow:0 0 0 1px #e2e8f0;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;';
                        placeholder.innerHTML = '<svg width="20" height="20" fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>';
                        wrap.replaceWith(placeholder);
                    }
                    BK.toast('Photo deleted successfully.', 'success');
                } catch (err) {
                    BK.toast(err.message || 'Could not delete photo.', 'error');
                }
            });
            setTimeout(removeEl, 8000);
        });
    }

    // Photo preview
    const photoInput   = document.getElementById('photo-input');
    const photoPreview = document.getElementById('photo-preview');
    const photoName    = document.getElementById('photo-name');
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            const file = photoInput.files && photoInput.files[0];
            if (photoPreview) { photoPreview.classList.toggle('hidden', !file); if (file) photoPreview.src = URL.createObjectURL(file); }
            if (photoName)    { photoName.textContent = file ? file.name : 'Change photo'; }
        });
    }

    // Submit
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        BK.submit(form, async function () {
            await BK.post('/api/students/' + id, new FormData(form));
            BK.toast('Student updated successfully.', 'success');
            window.location.href = '/students';
        });
    });
});
</script>
