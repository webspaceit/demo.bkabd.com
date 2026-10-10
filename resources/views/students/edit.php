<?php
/**
 * Edit student page — GET /edit-student/{id}
 * Same field set as the registration form (students/create.php), pre-filled
 * from the student row. Saves via POST /api/students/{id}
 * (StudentController::update) using the standard BK.submit AJAX flow, then
 * returns to the students list. Plain POST works too (returns JSON).
 *
 * $v(key) = old input (validation round-trip) falling back to the student row.
 */
$v = fn (string $key) => e((string) (old($key, $student[$key] ?? '') ?? ''));
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Edit Student</h1>
        <a href="/students" class="text-sm text-emerald-700 hover:text-emerald-900 font-medium">&larr; Back to Student Details</a>
    </div>

    <form id="student-edit-form" method="post" action="/api/students/<?= (int) $student['id'] ?>"
          data-id="<?= (int) $student['id'] ?>">
        <?= csrf_field() ?>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="input" data-field="name" placeholder="Student Name" value="<?= $v('name') ?>" required>
                <p class="field-error hidden" data-error-for="name"></p>
            </div>
            <div>
                <label class="label">Father's Name <span class="text-red-500">*</span></label>
                <input type="text" name="father_name" class="input" data-field="father_name" placeholder="Father's Name" value="<?= $v('father_name') ?>" required>
                <p class="field-error hidden" data-error-for="father_name"></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label">Mother's Name</label>
                <input type="text" name="mother_name" class="input" data-field="mother_name" placeholder="Mother's Name" value="<?= $v('mother_name') ?>">
                <p class="field-error hidden" data-error-for="mother_name"></p>
            </div>
            <div>
                <label class="label">Phone <span class="text-red-500">*</span></label>
                <input type="text" name="phone" class="input" data-field="phone" placeholder="Phone (11 digits)" maxlength="11" value="<?= $v('phone') ?>" required>
                <p class="field-error hidden" data-error-for="phone"></p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="label">Class <span class="text-red-500">*</span></label>
                <select name="class" class="input" data-field="class" required>
                    <option value="">Select</option>
                    <?php foreach ([1=>'ONE',2=>'TWO',3=>'THREE',4=>'FOUR',5=>'FIVE',6=>'KG'] as $code=>$name): ?>
                        <option value="<?= $code ?>" <?= $v('class') == $code ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error hidden" data-error-for="class"></p>
            </div>
            <div>
                <label class="label">Session <span class="text-red-500">*</span></label>
                <select name="session" class="input" data-field="session" required>
                    <option value="">Select</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= e($s['session']) ?>" <?= $v('session') == $s['session'] ? 'selected' : '' ?>>
                            <?= e($s['session']) ?> <?= (int) $s['active'] === 1 ? ' (active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error hidden" data-error-for="session"></p>
            </div>
            <div>
                <label class="label">Center <span class="text-red-500">*</span></label>
                <select name="center_id" class="input" data-field="center_id" required>
                    <option value="">Select</option>
                    <?php foreach ($centers as $c): ?>
                        <option value="<?= e($c['id']) ?>" <?= $v('center_id') == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error hidden" data-error-for="center_id"></p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="label">Institute <span class="text-red-500">*</span></label>
                <select name="institute_id" class="input" data-field="institute_id" required>
                    <option value="">Select</option>
                    <?php if ($v('center_id')): ?>
                        <?php foreach ($institutes as $i): ?>
                            <option value="<?= e($i['id']) ?>" <?= $v('institute_id') == $i['id'] ? 'selected' : '' ?>>
                                <?= e($i['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">Loading...</option>
                    <?php endif; ?>
                </select>
                <p class="field-error hidden" data-error-for="institute_id"></p>
            </div>
            <div>
                <label class="label">Division <span class="text-red-500">*</span></label>
                <select name="division_id" class="input" data-field="division_id" required>
                    <option value="">Select</option>
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?= e($d['id']) ?>" <?= $v('division_id') == $d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error hidden" data-error-for="division_id"></p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="label">District</label>
                <select name="district_id" class="input" data-field="district_id">
                    <option value="">Select</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= e($d['id']) ?>" <?= $v('district_id') == $d['id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error hidden" data-error-for="district_id"></p>
            </div>
            <div>
                <label class="label">Upazila</label>
                <select name="upazila_id" class="input" data-field="upazila_id">
                    <option value="">Select</option>
                    <?php foreach ($upazilas as $u): ?>
                        <option value="<?= e($u['id']) ?>" <?= $v('upazila_id') == $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error hidden" data-error-for="upazila_id"></p>
            </div>
            <div>
                <label class="label">Union</label>
                <select name="union_id" class="input" data-field="union_id">
                    <option value="">Select</option>
                    <?php foreach ($unions as $un): ?>
                        <option value="<?= e($un['id']) ?>" <?= $v('union_id') == $un['id'] ? 'selected' : '' ?>>
                            <?= e($un['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error hidden" data-error-for="union_id"></p>
            </div>
        </div>

        <div>
            <label class="label">Gender <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-3 gap-2">
                <label class="radio-item"><input type="radio" name="gender" value="1" class="radio" data-field="gender" <?= $v('gender') == 1 ? 'checked' : '' ?> required> Male</label>
                <label class="radio-item"><input type="radio" name="gender" value="2" class="radio" data-field="gender" <?= $v('gender') == 2 ? 'checked' : '' ?> > Female</label>
                <label class="radio-item"><input type="radio" name="gender" value="3" class="radio" data-field="gender" <?= $v('gender') == 3 ? 'checked' : '' ?> > Others</label>
            </div>
            <p class="field-error hidden" data-error-for="gender"></p>
        </div>

        <div>
            <label class="label">Education Type</label>
            <select name="education_type" class="input" data-field="education_type">
                <option value="">Select</option>
                <option value="1" <?= $v('education_type') == 1 ? 'selected' : '' ?>>Bangla Medium</option>
                <option value="2" <?= $v('education_type') == 2 ? 'selected' : '' ?>>English Version</option>
                <option value="3" <?= $v('education_type') == 3 ? 'selected' : '' ?>>Arabic Version</option>
            </select>
            <p class="field-error hidden" data-error-for="education_type"></p>
        </div>

        <div>
            <label class="label">Religion <span class="text-red-500">*</span></label>
            <select name="religion" class="input" data-field="religion" required>
                <option value="">Select</option>
                <option value="1" <?= $v('religion') == 1 ? 'selected' : '' ?>>Islam</option>
                <option value="2" <?= $v('religion') == 2 ? 'selected' : '' ?>>Hindu</option>
                <option value="3" <?= $v('religion') == 3 ? 'selected' : '' ?>>Buddhist</option>
                <option value="4" <?= $v('religion') == 4 ? 'selected' : '' ?>>Christian</option>
            </select>
            <p class="field-error hidden" data-error-for="religion"></p>
        </div>

        <div>
            <label class="label">DOB</label>
            <input type="date" name="dob" class="input" data-field="dob" placeholder="Date of Birth" value="<?= $v('dob') ?>">
            <p class="field-error hidden" data-error-for="dob"></p>
        </div>

        <div>
            <label class="label">Principal Name</label>
            <input type="text" name="principal_name" class="input" data-field="principal_name" placeholder="Principal Name" value="<?= $v('principal_name') ?>">
            <p class="field-error hidden" data-error-for="principal_name"></p>
        </div>

        <div>
            <label class="label">Notes</label>
            <textarea name="note" class="input" data-field="note" placeholder="Note (optional)" rows="3"><?= $v('note') ?></textarea>
            <p class="field-error hidden" data-error-for="note"></p>
        </div>

        <input type="hidden" name="photo" value="">
        <p class="text-sm text-slate-500 mt-2">Supported: jpeg, png, jpg, gif. Max 2MB.</p>

        <button type="submit" name="update" class="btn btn-primary w-full mt-6">
            Update Student
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const form = document.getElementById('student-edit-form');
    if (!form) { return; }
    const id = form.getAttribute('data-id');
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