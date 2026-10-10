<?php
/**
 * Add Center — user credentials (name/email/phone/password) + center profile.
 * Submitted as FormData by /assets/js/centers.js → POST /api/centers.
 */
?>
<div class="max-w-5xl">
    <div class="card">
        <div class="card-header">
            <h1 class="text-base font-semibold text-slate-800">Add Center</h1>
            <a href="/centers" class="btn btn-light btn-sm">Back to List</a>
        </div>
        <form id="center-form" class="card-body space-y-4" novalidate>
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="label" for="f-name">Center Name <span class="text-red-600">*</span></label>
                    <input id="f-name" name="name" type="text" data-field="name" placeholder="New Center Name">
                    <p class="field-error hidden" data-error-for="name"></p>
                </div>
                <div>
                    <label class="label" for="f-email">Email <span class="text-red-600">*</span></label>
                    <input id="f-email" name="email" type="email" data-field="email" placeholder="Email Address">
                    <p class="field-error hidden" data-error-for="email"></p>
                </div>
                <div>
                    <label class="label" for="f-phone">Phone Number <span class="text-red-600">*</span></label>
                    <input id="f-phone" name="phone" type="text" data-field="phone" placeholder="Phone">
                    <p class="field-error hidden" data-error-for="phone"></p>
                </div>

                <div>
                    <label class="label" for="f-division_id">Division <span class="text-red-600">*</span></label>
                    <select id="f-division_id" name="division_id" data-field="division_id">
                        <option value="">Select Division</option>
                    </select>
                    <p class="field-error hidden" data-error-for="division_id"></p>
                </div>
                <div>
                    <label class="label" for="f-district_id">District <span class="text-red-600">*</span></label>
                    <select id="f-district_id" name="district_id" data-field="district_id">
                        <option value="">Select District</option>
                    </select>
                    <p class="field-error hidden" data-error-for="district_id"></p>
                </div>
                <div>
                    <label class="label" for="f-upazila_id">Upazila <span class="text-red-600">*</span></label>
                    <select id="f-upazila_id" name="upazila_id" data-field="upazila_id">
                        <option value="">Select Upazila</option>
                    </select>
                    <p class="field-error hidden" data-error-for="upazila_id"></p>
                </div>

                <div>
                    <label class="label" for="f-authority_person">Name of Authority Person <span class="text-red-600">*</span></label>
                    <input id="f-authority_person" name="authority_person" type="text" data-field="authority_person" placeholder="Authority Person">
                    <p class="field-error hidden" data-error-for="authority_person"></p>
                </div>
                <div>
                    <label class="label" for="f-institute_name">Institute Name of Center Authority <span class="text-red-600">*</span></label>
                    <input id="f-institute_name" name="institute_name" type="text" data-field="institute_name" placeholder="Institute Name">
                    <p class="field-error hidden" data-error-for="institute_name"></p>
                </div>
                <div>
                    <label class="label" for="f-website">Website (If any)</label>
                    <input id="f-website" name="website" type="text" data-field="website" placeholder="Website">
                    <p class="field-error hidden" data-error-for="website"></p>
                </div>

                <div>
                    <label class="label" for="f-code">Center Code <span class="text-red-600">*</span></label>
                    <input id="f-code" name="code" type="text" data-field="code" placeholder="Code">
                    <p class="field-error hidden" data-error-for="code"></p>
                </div>
                <div>
                    <label class="label" for="f-note">Note</label>
                    <input id="f-note" name="note" type="text" data-field="note" placeholder="Note">
                    <p class="field-error hidden" data-error-for="note"></p>
                </div>
                <div>
                    <label class="label" for="f-active">Status</label>
                    <select id="f-active" name="active" data-field="active">
                        <option value="0" selected>Pending</option>
                        <option value="1">Approved</option>
                    </select>
                    <p class="field-error hidden" data-error-for="active"></p>
                </div>

                <div>
                    <label class="label" for="f-password">Password <span class="text-red-600">*</span></label>
                    <input id="f-password" name="password" type="password" data-field="password" placeholder="Password" autocomplete="new-password">
                    <p class="field-error hidden" data-error-for="password"></p>
                </div>
                <div>
                    <label class="label" for="f-confirmpassword">Confirm Password <span class="text-red-600">*</span></label>
                    <input id="f-confirmpassword" name="confirmpassword" type="password" data-field="confirmpassword" placeholder="Confirm Password" autocomplete="new-password">
                    <p class="field-error hidden" data-error-for="confirmpassword"></p>
                </div>
                <div></div>

                <div>
                    <label class="label" for="f-authority_photo">Photo of Authority Person</label>
                    <input id="f-authority_photo" name="authority_photo" type="file" data-field="authority_photo" accept="image/jpeg,image/png,image/gif">
                    <p class="field-error hidden" data-error-for="authority_photo"></p>
                </div>
                <div>
                    <label class="label" for="f-authority_signature">Signature of Authority Person</label>
                    <input id="f-authority_signature" name="authority_signature" type="file" data-field="authority_signature" accept="image/jpeg,image/png,image/gif">
                    <p class="field-error hidden" data-error-for="authority_signature"></p>
                </div>
                <div></div>
            </div>

            <button type="submit" class="btn btn-primary w-full justify-center"><span class="text-base">Submit</span></button>
        </form>
    </div>
</div>
