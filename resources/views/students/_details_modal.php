<?php
/**
 * Shared student details modal for the student list and pending registrations.
 * Data is loaded client-side from GET /api/students/{id}.
 */
?>
<div id="student-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-3 sm:p-6">
    <div class="my-3 w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:my-6">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-4 sm:px-7">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Student record</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-900" id="modal-title">Student Info</h3>
            </div>
            <button type="button" class="rounded-lg p-2 text-slate-500 transition hover:bg-white hover:text-slate-900" data-modal-close aria-label="Close">&#10005;</button>
        </div>

        <div class="space-y-6 p-4 sm:p-7">
            <section class="student-details-identity-grid grid grid-cols-1 gap-5 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 sm:p-5">
                <div class="flex items-start justify-center">
                    <div class="flex h-44 w-36 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-sm sm:h-48 sm:w-40">
                        <img id="modal-photo" src="" alt="Student photo" class="hidden h-full w-full rounded-lg object-contain" decoding="async">
                        <div id="modal-photo-placeholder" class="flex h-full w-full flex-col items-center justify-center gap-2 rounded-lg bg-slate-50 text-slate-400">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg>
                            <span class="text-xs font-medium">No photo</span>
                        </div>
                    </div>
                </div>
                <div class="min-w-0 self-center">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Student &amp; exam</p>
                    <h4 id="m-name" class="mt-1 break-words text-xl font-bold text-slate-900 sm:text-2xl">Loading student details…</h4>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white/90 p-3 xl:col-span-2"><div class="text-xs font-medium text-slate-500">Registration</div><div id="m-registration" class="mt-1 break-all text-sm font-semibold text-slate-800"></div></div>
                        <div class="rounded-xl border border-slate-200 bg-white/90 p-3"><div class="text-xs font-medium text-slate-500">Roll</div><div id="m-roll" class="mt-1 break-words text-sm font-semibold text-slate-800"></div></div>
                        <div class="rounded-xl border border-slate-200 bg-white/90 p-3"><div class="text-xs font-medium text-slate-500">Class / Session</div><div class="mt-1 text-sm font-semibold text-slate-800"><span id="m-class"></span><span class="mx-1 text-slate-400">/</span><span id="m-session"></span></div></div>
                        <div class="rounded-xl border border-slate-200 bg-white/90 p-3"><div class="text-xs font-medium text-slate-500">Center</div><div id="m-center_name" class="mt-1 break-words text-sm font-medium text-slate-800"></div></div>
                        <div class="rounded-xl border border-slate-200 bg-white/90 p-3"><div class="text-xs font-medium text-slate-500">Institute</div><div id="m-institute_name" class="mt-1 break-words text-sm font-medium text-slate-800"></div></div>
                    </div>
                </div>
            </section>

            <section>
                <h4 class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-800"><span class="h-5 w-1 rounded-full bg-emerald-500"></span>Personal &amp; contact</h4>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Father&apos;s name</div><div id="m-father_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Mother&apos;s name</div><div id="m-mother_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Date of birth</div><div id="m-dob" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Gender</div><div id="m-gender" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Phone</div><div id="m-phone" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Email</div><div id="m-email" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3 sm:col-span-2"><div class="text-xs font-medium text-slate-500">Religion / Education</div><div class="mt-1 break-words text-sm text-slate-800"><span id="m-religion"></span><span class="mx-1 text-slate-400">/</span><span id="m-education_type"></span></div></div>
                </div>
            </section>

            <section>
                <h4 class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-emerald-800"><span class="h-5 w-1 rounded-full bg-emerald-500"></span>Location &amp; additional</h4>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 p-3 sm:col-span-2"><div class="text-xs font-medium text-slate-500">Present address</div><div id="m-present_address" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3 sm:col-span-2"><div class="text-xs font-medium text-slate-500">Permanent address</div><div id="m-permanent_address" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Division</div><div id="m-division_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">District</div><div id="m-district_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Upazila</div><div id="m-upazila_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3"><div class="text-xs font-medium text-slate-500">Principal name</div><div id="m-principal_name" class="mt-1 break-words text-sm text-slate-800"></div></div>
                    <div class="rounded-xl border border-slate-200 p-3 sm:col-span-2 xl:col-span-4"><div class="text-xs font-medium text-slate-500">Note</div><div id="m-note" class="mt-1 break-words text-sm text-slate-800"></div></div>
                </div>
            </section>
        </div>

        <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-7">
            <button type="button" class="btn btn-primary btn-sm" data-modal-close>Close</button>
        </div>
    </div>
</div>
