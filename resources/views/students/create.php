<?php /** Student registration form. */ ?>
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-slate-900 mb-6">Register Student</h1>

    <?= form_open('/api/students') ?>
        <?= csrf_field() ?>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="input" data-field="name" value="<?= old('name') ?>" required>
                <p class="field-error hidden" data-error-for="name"></p>
            </div>
            <div>
                <label class="label">Father's Name <span class="text-red-500">*</span></label>
                <input type="text" name="father_name" class="input" data-field="father_name" value="<?= old('father_name') ?>" required>
                <p class="field-error hidden" data-error-for="father_name"></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="label">Mother's Name</label>
                <input type="text" name="mother_name" class="input" data-field="mother_name" value="<?= old('mother_name') ?>">
                <p class="field-error hidden" data-error-for="mother_name"></p>
            </div>
            <div>
                <label class="label">Phone <span class="text-red-500">*</span></label>
                <input type="text" name="phone" class="input" data-field="phone" maxlength="11" value="<?= old('phone') ?>" required>
                <p class="field-error hidden" data-error-for="phone"></p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="label">Class <span class="text-red-500">*</span></label>
                <select name="class" class="input" data-field="class" required>
                    <option value="">Select</option>
                    <?php foreach ([1=>'ONE',2=>'TWO',3=>'THREE',4=>'FOUR',5=>'FIVE',6=>'KG'] as $code=>$name): ?>
                        <option value="<?= $code ?>" <?= old('class') == $code ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error hidden" data-error-for="class"></p>
            </div>
            <div>
                <label class="label">Session <span class="text-red-500">*</span></label>
                <select name="session" class="input" data-field="session" required>
                    <option value="">Select</option>
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= $s['session'] ?>" <?= old('session') == $s['session'] ? 'selected' : '' ?>>
                            <?= $s['session'] ?> <?= (int) $s['active'] === 1 ? ' (active)' : '' ?>
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
                        <option value="<?= $c['id'] ?>" <?= old('center_id') == $c['id'] ? 'selected' : '' ?>>
                            <?= $c['name'] ?? '' ?>
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
                    <?php if (old('center_id')): ?>
                        <?php foreach ($institutes as $i): ?>
                            <option value="<?= $i['id'] ?>" <?= old('institute_id') == $i['id'] ? 'selected' : '' ?>>
                                <?= $i['name'] ?? '' ?>
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
                        <option value="<?= $d['id'] ?>" <?= old('division_id') == $d['id'] ? 'selected' : '' ?>>
                            <?= $d['name'] ?>
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
                        <option value="<?= $d['id'] ?>" <?= old('district_id') == $d['id'] ? 'selected' : '' ?>>
                            <?= $d['name'] ?>
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
                        <option value="<?= $u['id'] ?>" <?= old('upazila_id') == $u['id'] ? 'selected' : '' ?>>
                            <?= $u['name'] ?>
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
                        <option value="<?= $un['id'] ?>" <?= old('union_id') == $un['id'] ? 'selected' : '' ?>>
                            <?= $un['name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error hidden" data-error-for="union_id"></p>
            </div>
        </div>

        <div>
            <label class="label">Gender <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-3 gap-2">
                <label class="radio-item"><input type="radio" name="gender" value="1" class="radio" data-field="gender" <?= old('gender') == 1 ? 'checked' : '' ?> required> Male</label>
                <label class="radio-item"><input type="radio" name="gender" value="2" class="radio" data-field="gender" <?= old('gender') == 2 ? 'checked' : '' ?> > Female</label>
                <label class="radio-item"><input type="radio" name="gender" value="3" class="radio" data-field="gender" <?= old('gender') == 3 ? 'checked' : '' ?> > Others</label>
            </div>
            <p class="field-error hidden" data-error-for="gender"></p>
        </div>

        <div>
            <label class="label">Education Type</label>
            <select name="education_type" class="input" data-field="education_type">
                <option value="">Select</option>
                <option value="1" <?= old('education_type') == 1 ? 'selected' : '' ?>>Bangla Medium</option>
                <option value="2" <?= old('education_type') == 2 ? 'selected' : '' ?>>English Version</option>
                <option value="3" <?= old('education_type') == 3 ? 'selected' : '' ?>>Arabic Version</option>
            </select>
            <p class="field-error hidden" data-error-for="education_type"></p>
        </div>

        <div>
            <label class="label">Religion <span class="text-red-500">*</span></label>
            <select name="religion" class="input" data-field="religion" required>
                <option value="">Select</option>
                <option value="1" <?= old('religion') == 1 ? 'selected' : '' ?>>Islam</option>
                <option value="2" <?= old('religion') == 2 ? 'selected' : '' ?>>Hindu</option>
                <option value="3" <?= old('religion') == 3 ? 'selected' : '' ?>>Buddhist</option>
                <option value="4" <?= old('religion') == 4 ? 'selected' : '' ?>>Christian</option>
            </select>
            <p class="field-error hidden" data-error-for="religion"></p>
        </div>

        <div>
            <label class="label">DOB</label>
            <input type="date" name="dob" class="input" data-field="dob" value="<?= old('dob') ?>">
            <p class="field-error hidden" data-error-for="dob"></p>
        </div>

        <div>
            <label class="label">Principal Name</label>
            <input type="text" name="principal_name" class="input" data-field="principal_name" value="<?= old('principal_name') ?>">
            <p class="field-error hidden" data-error-for="principal_name"></p>
        </div>

        <div>
            <label class="label">Notes</label>
            <textarea name="note" class="input" data-field="note" rows="3"><?= old('note') ?></textarea>
            <p class="field-error hidden" data-error-for="note"></p>
        </div>

        <input type="hidden" name="photo" value="<?= old('photo') ?>">
        <p class="text-sm text-slate-500 mt-2">Supported: jpeg, png, jpg, gif. Max 2MB.</p>

        <button type="submit" name="register" class="btn btn-primary w-full mt-6">
            Register Student
        </button>
    <?= form_close() ?>
</div>