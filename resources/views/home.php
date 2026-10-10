<?php
/**
 * Dashboard home — Rubick-style layout with the BKABD emerald brand.
 * Every figure is loaded live from GET /api/dashboard
 * (DashboardController::dashboardcount) using data-stat="<key>"; the same key
 * names as the original Dashboard.vue: totalstudents, totalinstitutes,
 * totalcenter, totaldivision, totaldistrict, totalupazila, totalnotice,
 * totaluseraccess, attendcenter, attendinstitute.
 *
 * The line chart is decorative; the two participation rings are computed from
 * real attendance counts. Admin-only metrics are hidden for center users.
 */
$admin = is_admin();
$user  = auth_user();
?>
<div class="space-y-7" id="dashboard-page" data-admin="<?= $admin ? '1' : '0' ?>">

    <!-- ======================= General Report ======================= -->
    <section>
        <div class="flex items-center justify-between gap-3 mb-4">
            <div class="min-w-0">
                <h1 class="text-base font-semibold text-slate-800 tracking-tight">General Report</h1>
                <p class="text-xs text-slate-400 mt-0.5 truncate">
                    Welcome to <?= e($user['name'] ?? '') ?><?= $admin ? '' : ' Center' ?> — exam session <?= e($activeSession) ?>
                </p>
            </div>
            <a href="/reports" class="inline-flex items-center text-xs font-medium text-emerald-600 hover:text-emerald-700 transition shrink-0">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 21h16"/></svg>
                Reports
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
            <!-- Registered Students -->
            <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3.1-6 7-6h2"/><path d="M17 14a4 4 0 1 0-1.2-7.9"/><path d="M22 21c0-3-2-5-5-5"/></svg>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">Registration</span>
                </div>
                <div>
                    <div data-stat="totalstudents" class="text-2xl font-bold text-slate-800 tracking-tight">—</div>
                    <div class="text-xs text-slate-400 font-medium mt-1">Registered Students</div>
                </div>
            </div>

            <!-- Registered Institutes -->
            <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-5h6v5"/></svg>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">Membership</span>
                </div>
                <div>
                    <div data-stat="totalinstitutes" class="text-2xl font-bold text-slate-800 tracking-tight">—</div>
                    <div class="text-xs text-slate-400 font-medium mt-1">Registered Institutes</div>
                </div>
            </div>

            <?php if ($admin): ?>
            <!-- Registered Centers -->
            <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 21h18M4 21V9l8-6 8 6v12"/><path d="M9 21v-6h6v6"/></svg>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">Network</span>
                </div>
                <div>
                    <div data-stat="totalcenter" class="text-2xl font-bold text-slate-800 tracking-tight">—</div>
                    <div class="text-xs text-slate-400 font-medium mt-1">Registered Centers</div>
                </div>
            </div>

            <!-- User Access -->
            <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-9 h-9 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="8" cy="8" r="4"/><path d="M2 21c0-4 2.7-6 6-6s6 2 6 6"/><path d="M17 8a4 4 0 1 1 2.5 7.1"/></svg>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">Access</span>
                </div>
                <div>
                    <div data-stat="totaluseraccess" class="text-2xl font-bold text-slate-800 tracking-tight">—</div>
                    <div class="text-xs text-slate-400 font-medium mt-1">User Access</div>
                </div>
            </div>
            <?php else: ?>
            <!-- Districts -->
            <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 20 3 17V4l6 3 6-3 6 3v13l-6-3-6 3Z"/><path d="M9 7v13M15 4v13"/></svg>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">Area</span>
                </div>
                <div>
                    <div data-stat="totaldistrict" class="text-2xl font-bold text-slate-800 tracking-tight">—</div>
                    <div class="text-xs text-slate-400 font-medium mt-1">Districts</div>
                </div>
            </div>

            <!-- Upazilas -->
            <div class="bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-9 h-9 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 21s-7-5.2-7-11a7 7 0 0 1 14 0c0 5.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">Area</span>
                </div>
                <div>
                    <div data-stat="totalupazila" class="text-2xl font-bold text-slate-800 tracking-tight">—</div>
                    <div class="text-xs text-slate-400 font-medium mt-1">Upazilas</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ======================= Charts ======================= -->
    <section class="grid grid-cols-12 gap-6">
        <!-- Report line chart (decorative) -->
        <div class="col-span-12 xl:col-span-6 bg-white rounded-xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
            <div>
                <div class="flex flex-wrap items-center justify-between pb-4 border-b border-slate-100 gap-2">
                    <h3 class="text-base font-semibold text-slate-800">Report</h3>
                    <span class="text-xs text-slate-500 bg-slate-50 border border-slate-200/70 rounded-md px-2.5 py-1">
                        Session <?= e($activeSession) ?>
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-6 pt-4 pb-2">
                    <div>
                        <div data-stat="totalstudents" class="text-xl font-bold text-emerald-600 tracking-tight">—</div>
                        <div class="text-xs text-slate-400">Registered Students</div>
                    </div>
                    <div class="h-8 w-px bg-slate-100"></div>
                    <div>
                        <div data-stat="attendinstitute" class="text-xl font-bold text-slate-700 tracking-tight">—</div>
                        <div class="text-xs text-slate-400">Attended Institutes</div>
                    </div>
                </div>
            </div>
            <div>
                <div class="relative w-full h-52 mt-4">
                    <svg class="w-full h-full" fill="none" preserveAspectRatio="none" viewBox="0 0 540 180" aria-hidden="true">
                        <line stroke="#f1f5f9" stroke-width="1" x1="10" x2="530" y1="30" y2="30"></line>
                        <line stroke="#f1f5f9" stroke-width="1" x1="10" x2="530" y1="70" y2="70"></line>
                        <line stroke="#f1f5f9" stroke-width="1" x1="10" x2="530" y1="110" y2="110"></line>
                        <line stroke="#f1f5f9" stroke-width="1" x1="10" x2="530" y1="150" y2="150"></line>
                        <rect fill="#e2e8f0" fill-opacity="0.6" height="140" rx="2" width="16" x="100" y="20"></rect>
                        <rect fill="#e2e8f0" fill-opacity="0.6" height="140" rx="2" width="16" x="460" y="20"></rect>
                        <path d="M 40 160 C 80 155, 120 130, 160 145 C 200 160, 230 115, 270 120 C 310 125, 340 135, 370 115 C 400 95, 430 125, 460 120 C 490 115, 510 95, 530 100" fill="none" stroke="#cbd5e1" stroke-dasharray="3 3" stroke-width="1.8"></path>
                        <path d="M 40 160 C 80 150, 110 155, 150 135 C 190 115, 210 150, 250 145 C 290 140, 320 80, 360 65 C 400 50, 420 80, 450 70 C 480 60, 500 40, 530 55" fill="none" stroke="#059669" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path>
                    </svg>
                    <div class="flex justify-between pl-2 pr-2 text-[10px] text-slate-400 font-medium mt-1">
                        <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span>
                        <span>Jul</span><span>Aug</span><span>Sep</span><span>Oct</span><span>Nov</span><span>Dec</span>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Illustrative trend — not a live series.</p>
            </div>
        </div>

        <!-- Institute participation ring (real) -->
        <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white rounded-xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-800">Institute Participation</h3>
            </div>
            <div class="flex justify-center items-center py-4">
                <div class="relative w-40 h-40">
                    <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                        <circle cx="50" cy="50" fill="none" r="38" stroke="#e2e8f0" stroke-width="9"></circle>
                        <circle id="inst-arc" cx="50" cy="50" fill="none" r="38" stroke="#10b981" stroke-dasharray="0 238.76" stroke-linecap="round" stroke-width="9"></circle>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span id="inst-pct" class="text-lg font-bold text-slate-800 leading-tight">—</span>
                        <span class="text-[10px] text-slate-400">attended</span>
                    </div>
                </div>
            </div>
            <div class="space-y-2 text-xs pt-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span><span class="text-slate-500">Attended</span></div>
                    <span data-stat="attendinstitute" class="font-semibold text-slate-700">—</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-slate-200 mr-2"></span><span class="text-slate-500">Not attended</span></div>
                    <span id="inst-rem" class="font-semibold text-slate-700">—</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-slate-400 mr-2"></span><span class="text-slate-500">Total institutes</span></div>
                    <span data-stat="totalinstitutes" class="font-semibold text-slate-700">—</span>
                </div>
            </div>
        </div>

        <!-- Center participation ring (real) -->
        <div class="col-span-12 md:col-span-6 xl:col-span-3 bg-white rounded-xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-800">Center Participation</h3>
            </div>
            <div class="flex justify-center items-center py-4">
                <div class="relative w-40 h-40">
                    <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                        <circle cx="50" cy="50" fill="none" r="38" stroke="#e2e8f0" stroke-width="9"></circle>
                        <circle id="ctr-arc" cx="50" cy="50" fill="none" r="38" stroke="#0ea5e9" stroke-dasharray="0 238.76" stroke-linecap="round" stroke-width="9"></circle>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span id="ctr-pct" class="text-lg font-bold text-slate-800 leading-tight">—</span>
                        <span class="text-[10px] text-slate-400">attended</span>
                    </div>
                </div>
            </div>
            <div class="space-y-2 text-xs pt-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-sky-500 mr-2"></span><span class="text-slate-500">Attended</span></div>
                    <span data-stat="attendcenter" class="font-semibold text-slate-700">—</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-slate-200 mr-2"></span><span class="text-slate-500">Not attended</span></div>
                    <span id="ctr-rem" class="font-semibold text-slate-700">—</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center"><span class="w-2 h-2 rounded-full bg-slate-400 mr-2"></span><span class="text-slate-500">Total centers</span></div>
                    <span data-stat="totalcenter" class="font-semibold text-slate-700">—</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ======================= Bottom row ======================= -->
    <section class="grid grid-cols-12 gap-6 pb-4">
        <!-- Coverage (decorative map) + real area counts -->
        <div class="col-span-12 xl:col-span-8 bg-white rounded-xl p-6 shadow-sm border border-slate-100">
            <div class="flex flex-wrap items-center justify-between pb-3 border-b border-slate-100 gap-2">
                <h3 class="text-base font-semibold text-slate-800">Institute &amp; Center Coverage</h3>
                <span class="text-[11px] text-slate-400">Illustrative map</span>
            </div>
            <div class="relative w-full h-36 bg-slate-100 rounded-lg overflow-hidden border border-slate-200/60 flex items-center justify-center mt-4">
                <svg class="w-full h-full opacity-70" viewBox="0 0 600 150" aria-hidden="true">
                    <rect fill="#f8fafc" height="150" width="600"></rect>
                    <path d="M0,40 Q150,20 300,50 T600,30" fill="none" stroke="#e2e8f0" stroke-width="4"></path>
                    <path d="M0,100 Q200,120 400,90 T600,110" fill="none" stroke="#e2e8f0" stroke-width="6"></path>
                    <path d="M120,0 L140,150" fill="none" stroke="#cbd5e1" stroke-width="2"></path>
                    <path d="M280,0 L270,150" fill="none" stroke="#cbd5e1" stroke-width="2"></path>
                    <path d="M440,0 L450,150" fill="none" stroke="#cbd5e1" stroke-width="2"></path>
                </svg>
                <div class="absolute top-8 left-1/4"><span class="block w-3.5 h-3.5 bg-emerald-600 rounded-full ring-4 ring-emerald-200 shadow-md"></span></div>
                <div class="absolute bottom-6 left-1/2"><span class="block w-3.5 h-3.5 bg-emerald-600 rounded-full ring-4 ring-emerald-200 shadow-md"></span></div>
                <div class="absolute top-10 right-1/3"><span class="block w-3.5 h-3.5 bg-emerald-600 rounded-full ring-4 ring-emerald-200 shadow-md"></span></div>
            </div>
            <div class="grid grid-cols-3 gap-4 pt-5">
                <div>
                    <div data-stat="totaldivision" class="text-lg font-bold text-slate-800">—</div>
                    <div class="text-xs text-slate-400">Divisions</div>
                </div>
                <div>
                    <div data-stat="totaldistrict" class="text-lg font-bold text-slate-800">—</div>
                    <div class="text-xs text-slate-400">Districts</div>
                </div>
                <div>
                    <div data-stat="totalupazila" class="text-lg font-bold text-slate-800">—</div>
                    <div class="text-xs text-slate-400">Upazilas</div>
                </div>
            </div>
            <?php if ($admin): ?>
            <div class="grid grid-cols-3 gap-4 pt-4 mt-4 border-t border-slate-100">
                <div>
                    <div data-stat="totalnotice" class="text-lg font-bold text-slate-800">—</div>
                    <div class="text-xs text-slate-400">Notices Sent</div>
                </div>
                <div>
                    <div data-stat="totalcenter" class="text-lg font-bold text-slate-800">—</div>
                    <div class="text-xs text-slate-400">Centers</div>
                </div>
                <div>
                    <div data-stat="totaluseraccess" class="text-lg font-bold text-slate-800">—</div>
                    <div class="text-xs text-slate-400">User Access</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick links -->
        <div class="col-span-12 xl:col-span-4 bg-white rounded-xl p-6 shadow-sm border border-slate-100 flex flex-col">
            <h3 class="text-sm font-semibold text-slate-800 pb-3 border-b border-slate-100 mb-4">Quick Links</h3>
            <div class="space-y-2 text-sm">
                <a href="/students" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3.1-6 7-6s7 2 7 6"/></svg>
                    </span>
                    Student Details
                </a>
                <a href="/institutes" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-5h6v5"/></svg>
                    </span>
                    Institute Details
                </a>
                <a href="/reports" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>
                    </span>
                    All Reports
                </a>
                <a href="/notices" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    </span>
                    Notice Board
                </a>
                <a href="/settings" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>
                    </span>
                    Settings
                </a>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const CIRC = 2 * Math.PI * 38; // circumference for r=38

    function setRing(prefix, attended, total) {
        attended = Number(attended || 0);
        total = Number(total || 0);
        const pct = total > 0 ? Math.round((attended / total) * 100) : 0;

        const arc = document.getElementById(prefix + '-arc');
        if (arc) {
            arc.style.transition = 'stroke-dasharray .6s ease';
            arc.setAttribute('stroke-dasharray', (CIRC * pct / 100) + ' ' + CIRC);
        }
        const pctEl = document.getElementById(prefix + '-pct');
        if (pctEl) { pctEl.textContent = total > 0 ? pct + '%' : '—'; }
        const remEl = document.getElementById(prefix + '-rem');
        if (remEl) { remEl.textContent = total > 0 ? (total - attended) : '—'; }
    }

    async function load() {
        let json;
        try {
            json = await BK.get('/api/dashboard');
        } catch (err) {
            return;
        }
        document.querySelectorAll('[data-stat]').forEach(function (el) {
            const v = json[el.getAttribute('data-stat')];
            el.textContent = (v === undefined || v === null) ? '—' : v;
        });
        setRing('inst', json.attendinstitute, json.totalinstitutes);
        setRing('ctr', json.attendcenter, json.totalcenter);
    }

    load();
});
</script>
