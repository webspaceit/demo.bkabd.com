<?php
/**
 * Student details modal — shared by the students list (/students) and the
 * pending registration queue (/pending-students). Data is loaded client-side
 * from GET /api/students/{id} (see the pages' inline scripts).
 */
?>
<div id="student-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 overflow-y-auto">
    <div class="bg-white rounded-xl w-full max-w-2xl my-8 shadow-xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <h3 class="text-base font-semibold text-slate-800" id="modal-title">Student Info</h3>
            <button type="button" class="p-2 rounded hover:bg-slate-100 text-slate-500" data-modal-close aria-label="Close">&#10005;</button>
        </div>
        <div class="p-5">
            <div class="flex flex-col sm:flex-row gap-5">
                <div class="shrink-0">
                    <img id="modal-photo" src="" alt="Student Photo" class="w-32 h-36 object-cover rounded border border-slate-200 hidden">
                </div>
                <table class="table text-sm">
                    <tr><th class="w-40 text-left">Name</th><td class="text-left">: <span id="m-name"></span></td></tr>
                    <tr><th class="text-left">Registration</th><td class="text-left">: <span id="m-registration"></span></td></tr>
                    <tr><th class="text-left">Roll</th><td class="text-left">: <span id="m-roll"></span></td></tr>
                    <tr><th class="text-left">Father's Name</th><td class="text-left">: <span id="m-father_name"></span></td></tr>
                    <tr><th class="text-left">Mother's Name</th><td class="text-left">: <span id="m-mother_name"></span></td></tr>
                    <tr><th class="text-left">Center</th><td class="text-left">: <span id="m-center_name"></span></td></tr>
                    <tr><th class="text-left">Institute</th><td class="text-left">: <span id="m-institute_name"></span></td></tr>
                    <tr><th class="text-left">Email</th><td class="text-left">: <span id="m-email"></span></td></tr>
                    <tr><th class="text-left">Phone</th><td class="text-left">: <span id="m-phone"></span></td></tr>
                    <tr><th class="text-left">Religion</th><td class="text-left">: <span id="m-religion"></span></td></tr>
                    <tr><th class="text-left">Present Address</th><td class="text-left">: <span id="m-present_address"></span></td></tr>
                    <tr><th class="text-left">Permanent Address</th><td class="text-left">: <span id="m-permanent_address"></span></td></tr>
                    <tr><th class="text-left">Date of Birth</th><td class="text-left">: <span id="m-dob"></span></td></tr>
                    <tr><th class="text-left">Gender</th><td class="text-left">: <span id="m-gender"></span></td></tr>
                    <tr><th class="text-left">Class</th><td class="text-left">: <span id="m-class"></span></td></tr>
                    <tr><th class="text-left">Session</th><td class="text-left">: <span id="m-session"></span></td></tr>
                    <tr><th class="text-left">Division</th><td class="text-left">: <span id="m-division_name"></span></td></tr>
                    <tr><th class="text-left">District</th><td class="text-left">: <span id="m-district_name"></span></td></tr>
                    <tr><th class="text-left">Upazila</th><td class="text-left">: <span id="m-upazila_name"></span></td></tr>
                    <tr><th class="text-left">Education Type</th><td class="text-left">: <span id="m-education_type"></span></td></tr>
                    <tr><th class="text-left">Principal Name</th><td class="text-left">: <span id="m-principal_name"></span></td></tr>
                    <tr><th class="text-left">Note</th><td class="text-left">: <span id="m-note"></span></td></tr>
                </table>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
            <button type="button" class="btn btn-primary btn-sm" data-modal-close>Close</button>
        </div>
    </div>
</div>
