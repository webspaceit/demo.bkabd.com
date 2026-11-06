<?php
/** Shared student details modal for student and pending-registration lists. */
?>
<div id="student-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-3 sm:p-6">
    <div class="student-details-modal my-3 w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:my-6">
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 sm:px-7">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Student record</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-900" id="modal-title">Student Info</h3>
            </div>
            <button type="button" class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900" data-modal-close aria-label="Close">&#10005;</button>
        </div>

        <div class="student-details-content space-y-5 p-4 sm:p-6">
            <section class="student-profile">
                <div class="student-profile-photo">
                    <img id="modal-photo" src="" alt="Student photo" class="hidden" decoding="async">
                    <div id="modal-photo-placeholder" class="student-photo-placeholder">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
                        <span>No photo</span>
                    </div>
                </div>
                <div class="student-profile-main">
                    <p class="student-section-kicker">Student &amp; exam</p>
                    <h4 id="m-name" class="student-profile-name">Loading student details…</h4>
                    <div class="student-key-facts">
                        <div class="student-key-fact"><span>Registration</span><strong id="m-registration"></strong></div>
                        <div class="student-key-fact"><span>Roll</span><strong id="m-roll"></strong></div>
                        <div class="student-key-fact"><span>Class / Session</span><strong><span id="m-class"></span><span class="student-fact-divider">/</span><span id="m-session"></span></strong></div>
                        <div class="student-key-fact student-key-fact-emphasis"><span>Center</span><strong id="m-center_name"></strong></div>
                        <div class="student-key-fact student-key-fact-emphasis"><span>Institute</span><strong id="m-institute_name"></strong></div>
                    </div>
                </div>
            </section>

            <section class="student-details-section student-details-personal">
                <h4 class="student-section-heading">Personal &amp; contact</h4>
                <div class="student-details-grid">
                    <div class="student-detail student-detail-emphasis"><span>Father&apos;s name</span><strong id="m-father_name"></strong></div>
                    <div class="student-detail student-detail-emphasis"><span>Mother&apos;s name</span><strong id="m-mother_name"></strong></div>
                    <div class="student-detail student-detail-emphasis"><span>Date of birth</span><strong id="m-dob"></strong></div>
                    <div class="student-detail student-detail-emphasis"><span>Gender</span><strong id="m-gender"></strong></div>
                    <div class="student-detail student-detail-emphasis"><span>Phone</span><strong id="m-phone"></strong></div>
                    <div class="student-detail student-detail-emphasis"><span>Email</span><strong id="m-email"></strong></div>
                    <div class="student-detail student-detail-emphasis"><span>Religion</span><strong id="m-religion"></strong></div>
                    <div class="student-detail student-detail-emphasis"><span>Education type</span><strong id="m-education_type"></strong></div>
                </div>
            </section>

            <section class="student-details-section student-details-location-section">
                <h4 class="student-section-heading">Location &amp; additional</h4>
                <div class="student-details-grid">
                    <div class="student-detail student-detail-wide student-detail-emphasis"><span>Present address</span><strong id="m-present_address"></strong></div>
                    <div class="student-detail student-detail-wide student-detail-emphasis"><span>Permanent address</span><strong id="m-permanent_address"></strong></div>
                    <div class="student-detail student-detail-emphasis student-detail-location"><span>Division</span><strong id="m-division_name"></strong></div>
                    <div class="student-detail student-detail-emphasis student-detail-location"><span>District</span><strong id="m-district_name"></strong></div>
                    <div class="student-detail student-detail-emphasis student-detail-location"><span>Upazila</span><strong id="m-upazila_name"></strong></div>
                    <div class="student-detail student-detail-emphasis"><span>Principal name</span><strong id="m-principal_name"></strong></div>
                    <div class="student-detail student-detail-wide"><span>Note</span><strong id="m-note"></strong></div>
                </div>
            </section>
        </div>

        <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-3 sm:px-7">
            <button type="button" class="btn btn-primary btn-sm" data-modal-close>Close</button>
        </div>
    </div>
</div>
