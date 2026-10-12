<?php
/**
 * Edit Center — same form as create, prefilled by /assets/js/centers.js
 * from GET /api/centers/{id} (centerById + districts + upazilas).
 * Submitted as FormData → POST /api/centers/{id}.
 */
$centerId = (int) ($centerId ?? 0);
?>
<div class="max-w-5xl">
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">Edit Center</h1>
            <a href="/centers" class="btn btn-light btn-sm">Back to List</a>
        </div>
        <form id="center-form" data-center-id="<?= $centerId ?>" class="card-body space-y-4" novalidate>
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="fl">
                    <input class="input" id="f-name" name="name" type="text" data-field="name" placeholder="Center Name">
                    <label for="f-name">Center Name <span class="text-red-600">*</span></label>
                    <p class="field-error hidden" data-error-for="name"></p>
                </div>
                <div class="fl">
                    <input class="input" id="f-email" name="email" type="email" data-field="email" placeholder="Email Address">
                    <label for="f-email">Email <span class="text-red-600">*</span></label>
                    <p class="field-error hidden" data-error-for="email"></p>
                </div>
                <div class="fl">
                    <input class="input" id="f-phone" name="phone" type="text" data-field="phone" placeholder="Phone">
                    <label for="f-phone">Phone Number <span class="text-red-600">*</span></label>
                    <p class="field-error hidden" data-error-for="phone"></p>
                </div>

                <div class="fl">
                    <select class="input" id="f-division_id" name="division_id" data-field="division_id">
                        <option value="">Select Division</option>
                    </select>
                    <label for="f-division_id">Division <span class="text-red-600">*</span></label>
                    <p class="field-error hidden" data-error-for="division_id"></p>
                </div>
                <div class="fl">
                    <select class="input" id="f-district_id" name="district_id" data-field="district_id">
                        <option value="">Select District</option>
                    </select>
                    <label for="f-district_id">District <span class="text-red-600">*</span></label>
                    <p class="field-error hidden" data-error-for="district_id"></p>
                </div>
                <div class="fl">
                    <select class="input" id="f-upazila_id" name="upazila_id" data-field="upazila_id">
                        <option value="">Select Upazila</option>
                    </select>
                    <label for="f-upazila_id">Upazila <span class="text-red-600">*</span></label>
                    <p class="field-error hidden" data-error-for="upazila_id"></p>
                </div>

                <div class="fl">
                    <input class="input" id="f-authority_person" name="authority_person" type="text" data-field="authority_person" placeholder="Authority Person">
                    <label for="f-authority_person">Name of Authority Person <span class="text-red-600">*</span></label>
                    <p class="field-error hidden" data-error-for="authority_person"></p>
                </div>
                <div class="fl">
                    <input class="input" id="f-institute_name" name="institute_name" type="text" data-field="institute_name" placeholder="Institute Name">
                    <label for="f-institute_name">Institute Name of Center Authority <span class="text-red-600">*</span></label>
                    <p class="field-error hidden" data-error-for="institute_name"></p>
                </div>
                <div class="fl">
                    <input class="input" id="f-website" name="website" type="text" data-field="website" placeholder="Website">
                    <label for="f-website">Website (If any)</label>
                    <p class="field-error hidden" data-error-for="website"></p>
                </div>

                <div class="fl">
                    <input class="input" id="f-code" name="code" type="text" data-field="code" placeholder="Code">
                    <label for="f-code">Center Code <span class="text-red-600">*</span></label>
                    <p class="field-error hidden" data-error-for="code"></p>
                </div>
                <div class="fl">
                    <input class="input" id="f-note" name="note" type="text" data-field="note" placeholder="Note">
                    <label for="f-note">Note</label>
                    <p class="field-error hidden" data-error-for="note"></p>
                </div>
                <div></div>

                <div class="fl">
                    <input class="input" id="f-password" name="password" type="password" data-field="password" placeholder="Leave blank to keep current" autocomplete="new-password">
                    <label for="f-password">Password</label>
                    <p class="field-error hidden" data-error-for="password"></p>
                </div>
                <div class="fl">
                    <input class="input" id="f-confirmpassword" name="confirmpassword" type="password" data-field="confirmpassword" placeholder="Leave blank to keep current" autocomplete="new-password">
                    <label for="f-confirmpassword">Confirm Password</label>
                    <p class="field-error hidden" data-error-for="confirmpassword"></p>
                </div>
                <div class="self-end pb-1 text-xs text-slate-400">Leave both password fields blank to keep the current password.</div>

                <div class="fl">
                    <input class="input" id="f-authority_photo" name="authority_photo" type="file" data-field="authority_photo" accept="image/jpeg,image/png,image/gif">
                    <label for="f-authority_photo">Photo of Authority Person</label>
                    <p class="field-error hidden" data-error-for="authority_photo"></p>
                    <div class="mt-2 flex items-center gap-2">
                        <img id="preview-photo" src="" alt="" class="hidden h-14 w-14 rounded object-cover ring-1 ring-slate-200">
                        <span class="text-xs text-slate-400">Current photo — choose a file to replace it</span>
                    </div>
                </div>
                <div class="fl">
                    <input class="input" id="f-authority_signature" name="authority_signature" type="file" data-field="authority_signature" accept="image/jpeg,image/png,image/gif">
                    <label for="f-authority_signature">Signature of Authority Person</label>
                    <p class="field-error hidden" data-error-for="authority_signature"></p>
                    <div class="mt-2 flex items-center gap-2">
                        <img id="preview-signature" src="" alt="" class="hidden h-14 w-14 rounded object-cover ring-1 ring-slate-200">
                        <span class="text-xs text-slate-400">Current signature — choose a file to replace it</span>
                    </div>
                </div>
                <div></div>
            </div>

            <button type="submit" class="btn btn-primary w-full justify-center"><span class="text-base">Update</span></button>
        </form>
    </div>
</div>
