<?php
/**
 * Student details modal — shared by the students list (/students) and the
 * pending registration queue (/pending-students). Data is loaded client-side
 * from GET /api/students/{id} (see the pages' inline scripts).
 */
?>
<div id="student-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 overflow-y-auto">
    <div class="bg-white rounded-xl w-full max-w-4xl my-8 shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h3 class="text-base font-semibold text-slate-800" id="modal-title">Student Info</h3>
            <button type="button" class="p-2 rounded hover:bg-slate-100 text-slate-500" data-modal-close aria-label="Close">&#10005;</button>
        </div>
        <div class="p-6">
            <div class="flex flex-col sm:flex-row gap-6">
                <div class="shrink-0 flex justify-center sm:justify-start">
                    <img id="modal-photo" src="" alt="Student Photo" class="w-32 h-36 object-cover rounded-lg border border-slate-200 shadow-sm hidden">
                </div>
                <div class="min-w-0 flex-1 space-y-5">
                    <section>
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-emerald-700">Student & exam</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 sm:col-span-2 lg:col-span-1"><div class="text-xs font-medium text-slate-500">Name</div><div id="m-name" class="mt-1 break-words text-sm font-semibold text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs font-medium text-slate-500">Registration</div><div id="m-registration" class="mt-1 break-words text-sm font-semibold text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs font-medium text-slate-500">Roll</div><div id="m-roll" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs font-medium text-slate-500">Class / Session</div><div class="mt-1 text-sm text-slate-800"><span id="m-class"></span> <span class="text-slate-400">/</span> <span id="m-session"></span></div></div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs font-medium text-slate-500">Center</div><div id="m-center_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3"><div class="text-xs font-medium text-slate-500">Institute</div><div id="m-institute_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                        </div>
                    </section>
                    <section>
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-emerald-700">Personal & contact</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Father's Name</div><div id="m-father_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Mother's Name</div><div id="m-mother_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Date of Birth / Gender</div><div class="mt-1 text-sm text-slate-800"><span id="m-dob"></span> <span class="text-slate-400">/</span> <span id="m-gender"></span></div></div>
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Phone</div><div id="m-phone" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Email</div><div id="m-email" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Religion / Education</div><div class="mt-1 text-sm text-slate-800"><span id="m-religion"></span> <span class="text-slate-400">/</span> <span id="m-education_type"></span></div></div>
                        </div>
                    </section>
                    <section>
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-emerald-700">Location & additional</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Present Address</div><div id="m-present_address" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Permanent Address</div><div id="m-permanent_address" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Division / District</div><div class="mt-1 break-words text-sm text-slate-800"><span id="m-division_name"></span> / <span id="m-district_name"></span></div></div>
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Upazila</div><div id="m-upazila_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Principal Name</div><div id="m-principal_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                            <div class="rounded-lg border border-slate-200 p-3 sm:col-span-2 lg:col-span-1"><div class="text-xs font-medium text-slate-500">Note</div><div id="m-note" class="mt-1 break-words text-sm text-slate-800"></div></div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
            <button type="button" class="btn btn-primary btn-sm" data-modal-close>Close</button>
        </div>
    </div>
</div>
