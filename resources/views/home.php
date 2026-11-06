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
            <div class="dashboard-stat-card bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
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
            <div class="dashboard-stat-card bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
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
            <div class="dashboard-stat-card bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
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
            <div class="dashboard-stat-card bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
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
            <div class="dashboard-stat-card bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
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
            <div class="dashboard-stat-card bg-white rounded-xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition flex flex-col justify-between">
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
        <!-- Live participation chart -->
        <div class="dashboard-coverage-card col-span-12 xl:col-span-6 bg-white rounded-xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
            <div>
                <div class="flex flex-wrap items-center justify-between pb-4 border-b border-slate-100 gap-2">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Participation Overview</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Attendance coverage for the active session</p>
                    </div>
                    <span class="text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-full px-3 py-1">
                        Session <?= e($activeSession) ?>
                    </span>
                </div>
            </div>
            <div class="dashboard-coverage-chart" aria-label="Attendance coverage chart">
                <div class="coverage-axis" aria-hidden="true"><span>0%</span><span>25%</span><span>50%</span><span>75%</span><span>100%</span></div>
                <div class="coverage-row">
                    <div class="coverage-label"><span class="coverage-dot coverage-dot-centers"></span><span>Centers</span></div>
                    <div class="coverage-track"><div class="coverage-fill coverage-fill-centers" data-chart-bar="centers" style="width:0%"></div></div>
                    <div class="coverage-value"><strong id="ctr-chart-pct">—</strong><span><span data-stat="attendcenter">—</span> / <span data-stat="totalcenter">—</span></span></div>
                </div>
                <div class="coverage-row">
                    <div class="coverage-label"><span class="coverage-dot coverage-dot-institutes"></span><span>Institutes</span></div>
                    <div class="coverage-track"><div class="coverage-fill coverage-fill-institutes" data-chart-bar="institutes" style="width:0%"></div></div>
                    <div class="coverage-value"><strong id="inst-chart-pct">—</strong><span><span data-stat="attendinstitute">—</span> / <span data-stat="totalinstitutes">—</span></span></div>
                </div>
                <div class="coverage-legend"><span><i class="legend-attended"></i>Has students</span><span><i class="legend-remaining"></i>No students yet</span></div>
            </div>
        </div>

        <!-- Institute participation ring (real) -->
        <div class="dashboard-participation-card col-span-12 md:col-span-6 xl:col-span-3 bg-white rounded-xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-800">Institute Participation</h3>
            </div>
            <div class="flex justify-center items-center py-4">
                <div class="dashboard-ring relative w-40 h-40">
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
        <div class="dashboard-participation-card dashboard-center-card col-span-12 md:col-span-6 xl:col-span-3 bg-white rounded-xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-800">Center Participation</h3>
            </div>
            <div class="flex justify-center items-center py-4">
                <div class="dashboard-ring relative w-40 h-40">
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
        <div class="dashboard-lower-card col-span-12 xl:col-span-8 bg-white rounded-xl p-6 shadow-sm border border-slate-100">
            <div class="flex flex-wrap items-center justify-between pb-3 border-b border-slate-100 gap-2">
                <h3 class="text-base font-semibold text-slate-800">Institute &amp; Center Coverage</h3>
                <span class="dashboard-map-badge"><span></span> Network overview · illustrative</span>
            </div>
            <div class="dashboard-map mt-4" role="img" aria-label="Illustrative map representing the association's nationwide network">
                <div class="dashboard-map-copy">
                    <span class="dashboard-map-kicker">BANGLADESH</span>
                    <strong>Growing together</strong>
                    <span>Institutes and centers across the country</span>
                </div>
                <svg class="dashboard-map-art" viewBox="0 0 620 250" aria-hidden="true" preserveAspectRatio="xMidYMid meet">
                    <defs>
                        <linearGradient id="map-fill" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="#a7f3d0"/><stop offset="1" stop-color="#34d399"/></linearGradient>
                        <filter id="map-shadow" x="-30%" y="-30%" width="160%" height="170%"><feDropShadow dx="0" dy="8" stdDeviation="8" flood-color="#065f46" flood-opacity=".18"/></filter>
                    </defs>
                    <path class="map-contour" d="M325 19c28 9 39 23 61 24 20 1 29-14 49-4 15 8 8 25 24 34 17 9 31-4 48 8 14 10 5 26 18 38 10 10 23 14 20 28-3 13-20 16-21 30-1 18 20 28 10 44-8 13-29 8-40 18-12 11-5 26-22 34-18 8-34-8-51-1-15 6-18 26-36 27-16 1-20-18-37-20-18-2-31 15-46 8-14-7-8-26-20-37-13-12-30-3-42-17-11-13 1-28-8-42-9-15-28-12-35-28-7-15 8-29 2-45-6-17-25-23-20-39 5-15 27-17 37-29 10-12 9-31 23-37 16-7 29 8 44 6 17-2 24-23 42-22Z"/>
                    <path class="map-river" d="M304 42c28 23 24 41 48 53 23 12 44-1 56 15 14 19-9 32-1 51 10 24 43 25 45 45 1 13-15 24-31 32"/>
                    <path class="map-boundary" d="M349 31c-8 31-5 54 15 74m27-63c-11 30-13 55-4 77m48-52c-18 24-25 46-20 70m61-39c-27 18-39 39-43 65m-94-26c27-8 54-5 77 6m-80 23c31-13 59-12 89-2m-62 40c8-25 23-42 45-54"/>
                    <g class="map-pin" transform="translate(350 64)"><circle r="11"/><circle class="map-pin-core" r="4"/></g>
                    <g class="map-pin map-pin-blue" transform="translate(448 118)"><circle r="11"/><circle class="map-pin-core" r="4"/></g>
                    <g class="map-pin map-pin-gold" transform="translate(382 184)"><circle r="11"/><circle class="map-pin-core" r="4"/></g>
                </svg>
                <span class="dashboard-map-footnote">A visual summary of the association network</span>
            </div>
            <div class="dashboard-area-stats grid grid-cols-3 gap-3 pt-5">
                <div class="dashboard-area-stat">
                    <div class="dashboard-area-stat-value" data-stat="totaldivision">—</div>
                    <div class="dashboard-area-stat-label">Divisions</div>
                </div>
                <div class="dashboard-area-stat">
                    <div class="dashboard-area-stat-value" data-stat="totaldistrict">—</div>
                    <div class="dashboard-area-stat-label">Districts</div>
                </div>
                <div class="dashboard-area-stat">
                    <div class="dashboard-area-stat-value" data-stat="totalupazila">—</div>
                    <div class="dashboard-area-stat-label">Upazilas</div>
                </div>
            </div>
            <?php if ($admin): ?>
            <div class="dashboard-area-stats dashboard-area-stats-secondary grid grid-cols-3 gap-3 pt-3 mt-3">
                <div class="dashboard-area-stat">
                    <div class="dashboard-area-stat-value" data-stat="totalnotice">—</div>
                    <div class="dashboard-area-stat-label">Notices Sent</div>
                </div>
                <div class="dashboard-area-stat">
                    <div class="dashboard-area-stat-value" data-stat="totalcenter">—</div>
                    <div class="dashboard-area-stat-label">Centers</div>
                </div>
                <div class="dashboard-area-stat">
                    <div class="dashboard-area-stat-value" data-stat="totaluseraccess">—</div>
                    <div class="dashboard-area-stat-label">User Access</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick links -->
        <div class="dashboard-lower-card dashboard-quick-panel col-span-12 xl:col-span-4 bg-white rounded-xl p-6 shadow-sm border border-slate-100 flex flex-col">
            <h3 class="text-sm font-semibold text-slate-800 pb-3 border-b border-slate-100 mb-4">Quick Links</h3>
            <div class="space-y-2 text-sm">
                <a href="/students" class="dashboard-quick-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3.1-6 7-6s7 2 7 6"/></svg>
                    </span>
                    Student Details
                </a>
                <a href="/institutes" class="dashboard-quick-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-5h6v5"/></svg>
                    </span>
                    Institute Details
                </a>
                <a href="/reports" class="dashboard-quick-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg>
                    </span>
                    All Reports
                </a>
                <a href="/scholarship-result" target="_blank" rel="noopener noreferrer" class="dashboard-quick-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v6h6M9 14h6M9 18h6"/></svg>
                    </span>
                    Scholarship Result
                </a>
                <a href="/notices" class="dashboard-quick-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                    </span>
                    Notice Board
                </a>
                <a href="/settings" class="dashboard-quick-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition">
                    <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>
                    </span>
                    Settings
                </a>
            </div>
        </div>
    </section>
</div>

<style>
.dashboard-stat-card {
    position: relative;
    min-height: 144px;
    overflow: hidden;
    border-color: #d9eee5;
    background: linear-gradient(145deg, #fff 0%, #f5fcf8 100%);
    box-shadow: 0 5px 16px rgba(15, 80, 58, .045);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.dashboard-stat-card::before {
    position: absolute;
    top: 0;
    right: 18px;
    left: 18px;
    height: 3px;
    border-radius: 0 0 8px 8px;
    background: linear-gradient(90deg, #059669, #34d399);
    content: "";
}
.dashboard-stat-card::after {
    position: absolute;
    top: -34px;
    right: -28px;
    width: 110px;
    height: 110px;
    border: 1px solid rgba(16,185,129,.08);
    border-radius: 50%;
    box-shadow: 0 0 0 12px rgba(16,185,129,.025), 0 0 0 25px rgba(16,185,129,.018);
    content: "";
    pointer-events: none;
}
.dashboard-stat-card:hover {
    transform: translateY(-3px);
    border-color: #bce8d2;
    box-shadow: 0 12px 26px rgba(15, 80, 58, .09);
}
.dashboard-stat-card [data-stat] { position: relative; z-index: 1; font-size: 28px; }
.dashboard-stat-card .text-xs { position: relative; z-index: 1; }
.dashboard-stat-card:nth-child(2)::before { background: linear-gradient(90deg, #0284c7, #38bdf8); }
.dashboard-stat-card:nth-child(3)::before { background: linear-gradient(90deg, #d97706, #fbbf24); }
.dashboard-stat-card:nth-child(4)::before { background: linear-gradient(90deg, #7c3aed, #c084fc); }
.dashboard-participation-card {
    position: relative;
    overflow: hidden;
    border-color: #d9eee5;
    background: radial-gradient(circle at 50% 45%, rgba(16,185,129,.07), transparent 45%), linear-gradient(145deg, #fff, #f7fcfa);
    box-shadow: 0 6px 18px rgba(15, 80, 58, .045);
}
.dashboard-center-card {
    background: radial-gradient(circle at 50% 45%, rgba(14,165,233,.08), transparent 45%), linear-gradient(145deg, #fff, #f7fbff);
}
.dashboard-participation-card h3 { font-size: 14px; letter-spacing: -.01em; }
.dashboard-ring { filter: drop-shadow(0 5px 10px rgba(15,23,42,.06)); }
.dashboard-coverage-card {
    background: radial-gradient(ellipse at 92% 4%, rgba(16,185,129,.08), transparent 34%), linear-gradient(145deg, #fff 0%, #fbfffd 100%);
}
.dashboard-lower-card {
    border-color: #d9eee5;
    background: linear-gradient(145deg, #fff 0%, #f8fcfa 100%);
    box-shadow: 0 6px 18px rgba(15, 80, 58, .045);
}
.dashboard-area-stats { margin-top: 16px; }
.dashboard-area-stat {
    min-height: 76px;
    padding: 13px 15px;
    border: 1px solid #e3f0e9;
    border-radius: 13px;
    background: linear-gradient(145deg, #fff, #f4fbf7);
    box-shadow: 0 3px 10px rgba(15,80,58,.035);
    transition: transform .18s ease, box-shadow .18s ease;
}
.dashboard-area-stat:hover { transform: translateY(-2px); box-shadow: 0 7px 16px rgba(15,80,58,.08); }
.dashboard-area-stat-value { color: #123d31; font-size: 21px; font-weight: 750; line-height: 1.2; }
.dashboard-area-stat-label { margin-top: 5px; color: #718579; font-size: 11px; font-weight: 550; }
.dashboard-area-stats-secondary { border-top: 1px solid #e8f1ec; }
.dashboard-area-stats-secondary .dashboard-area-stat { background: linear-gradient(145deg, #f5fbff, #fff); border-color: #e3edf4; }
.dashboard-area-stats-secondary .dashboard-area-stat-value { color: #17628a; }
.dashboard-map-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 10px;
    border: 1px solid #d9eee5;
    border-radius: 999px;
    background: #f4fbf7;
    color: #647b70;
    font-size: 10px;
    font-weight: 600;
}
.dashboard-map-badge > span { width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.13); }
.dashboard-map {
    position: relative;
    display: flex;
    min-height: 214px;
    align-items: center;
    overflow: hidden;
    border: 1px solid #dbece4;
    border-radius: 17px;
    background:
        radial-gradient(ellipse at 82% 50%, rgba(167,243,208,.45), transparent 43%),
        linear-gradient(115deg, #f7fcf9 0%, #effaf5 48%, #eaf7f5 100%);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.9), 0 5px 16px rgba(15,80,58,.045);
}
.dashboard-map::before {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(5,150,105,.13) .8px, transparent .8px);
    background-size: 18px 18px;
    mask-image: linear-gradient(90deg, transparent, #000 55%, #000);
    content: "";
    opacity: .38;
}
.dashboard-map-copy { position: relative; z-index: 2; display: flex; width: 42%; flex-direction: column; padding: 24px 0 24px 26px; }
.dashboard-map-kicker { color: #059669; font-size: 10px; font-weight: 800; letter-spacing: .16em; }
.dashboard-map-copy strong { margin-top: 8px; color: #123d31; font-size: 20px; font-weight: 750; letter-spacing: -.03em; }
.dashboard-map-copy > span:last-child { max-width: 210px; margin-top: 7px; color: #6b8277; font-size: 11px; line-height: 1.55; }
.dashboard-map-art { position: absolute; top: 0; right: 5%; width: 57%; height: 100%; overflow: visible; }
.map-contour { fill: url(#map-fill); stroke: #6ee7b7; stroke-width: 2; filter: url(#map-shadow); }
.map-river { fill: none; stroke: rgba(255,255,255,.88); stroke-width: 5; stroke-linecap: round; }
.map-boundary { fill: none; stroke: rgba(6,95,70,.22); stroke-width: 1.5; stroke-dasharray: 3 4; stroke-linecap: round; }
.map-pin > circle:first-child { fill: #fff; stroke: #059669; stroke-width: 4; filter: drop-shadow(0 2px 4px rgba(6,95,70,.2)); }
.map-pin-core { fill: #059669; }
.map-pin-blue > circle:first-child { stroke: #0284c7; }
.map-pin-blue .map-pin-core { fill: #0284c7; }
.map-pin-gold > circle:first-child { stroke: #d97706; }
.map-pin-gold .map-pin-core { fill: #d97706; }
.dashboard-map-footnote { position: absolute; right: 14px; bottom: 10px; z-index: 2; color: #789186; font-size: 9px; }
.dashboard-quick-panel { background: radial-gradient(ellipse at 100% 0%, rgba(14,165,233,.07), transparent 40%), linear-gradient(145deg, #fff, #f9fcff); }
.dashboard-quick-link {
    min-height: 52px;
    border: 1px solid transparent;
    border-radius: 12px;
    font-weight: 550;
}
.dashboard-quick-link:hover {
    transform: translateX(3px);
    border-color: #dcefe6;
    background: rgba(236,253,245,.75);
    box-shadow: 0 4px 12px rgba(15,80,58,.05);
}
.dashboard-coverage-chart {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-top: 28px;
    padding: 22px 20px 18px;
    border: 1px solid #e8f2ed;
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(248,252,250,.92), rgba(255,255,255,.98));
    box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
}
.coverage-axis {
    display: flex;
    justify-content: space-between;
    margin: 0 88px 0 116px;
    color: #94a3b8;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .02em;
}
.coverage-row {
    display: grid;
    grid-template-columns: 100px minmax(0,1fr) 76px;
    align-items: center;
    gap: 16px;
}
.coverage-label {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #334155;
    font-size: 12px;
    font-weight: 650;
}
.coverage-dot { width: 9px; height: 9px; flex: 0 0 9px; border-radius: 50%; }
.coverage-dot-centers { background: #0ea5e9; box-shadow: 0 0 0 4px rgba(14,165,233,.12); }
.coverage-dot-institutes { background: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,.12); }
.coverage-track {
    position: relative;
    height: 24px;
    overflow: hidden;
    border: 1px solid #e2ebe6;
    border-radius: 999px;
    background-color: #eef4f1;
    background-image: repeating-linear-gradient(to right, transparent 0, transparent calc(25% - 1px), rgba(148,163,184,.3) calc(25% - 1px), rgba(148,163,184,.3) 25%);
}
.coverage-fill {
    height: 100%;
    min-width: 0;
    border-radius: inherit;
    transition: width .8s cubic-bezier(.2,.75,.25,1);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.3), 0 2px 8px rgba(5,150,105,.18);
}
.coverage-fill-centers { background: linear-gradient(90deg, #0284c7, #38bdf8); }
.coverage-fill-institutes { background: linear-gradient(90deg, #059669, #34d399); }
.coverage-value { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2; }
.coverage-value strong { color: #0f172a; font-size: 15px; font-weight: 750; }
.coverage-value > span { margin-top: 4px; color: #94a3b8; font-size: 10px; white-space: nowrap; }
.coverage-legend { display: flex; align-items: center; gap: 18px; padding-top: 12px; border-top: 1px solid #edf2ef; color: #64748b; font-size: 10px; font-weight: 500; }
.coverage-legend > span { display: inline-flex; align-items: center; gap: 7px; }
.coverage-legend i { display: inline-block; width: 9px; height: 9px; border-radius: 3px; }
.coverage-legend .legend-attended { background: linear-gradient(135deg,#059669,#34d399); }
.coverage-legend .legend-remaining { background: #dce7e1; }
@media (max-width: 520px) {
    .dashboard-stat-card { min-height: 128px; }
    .dashboard-stat-card [data-stat] { font-size: 24px; }
    .dashboard-coverage-chart { padding: 18px 12px 14px; gap: 20px; }
    .coverage-row { grid-template-columns: 78px minmax(0,1fr) 62px; gap: 9px; }
    .coverage-axis { margin-left: 87px; margin-right: 70px; font-size: 9px; }
    .coverage-label { gap: 7px; font-size: 11px; }
    .coverage-value strong { font-size: 13px; }
    .dashboard-area-stat { min-height: 68px; padding: 10px; }
    .dashboard-area-stat-value { font-size: 18px; }
    .dashboard-map { min-height: 190px; }
    .dashboard-map-copy { width: 48%; padding-left: 17px; }
    .dashboard-map-copy strong { font-size: 17px; }
    .dashboard-map-copy > span:last-child { max-width: 150px; font-size: 10px; }
    .dashboard-map-art { right: -4%; width: 64%; }
    .dashboard-map-footnote { right: 9px; font-size: 8px; }
}
</style>

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

    function setCoverageChart(key, attended, total) {
        attended = Number(attended || 0);
        total = Number(total || 0);
        const pct = total > 0 ? Math.min(100, Math.round((attended / total) * 100)) : 0;
        const bar = document.querySelector('[data-chart-bar="' + key + '"]');
        const label = document.getElementById((key === 'centers' ? 'ctr' : 'inst') + '-chart-pct');
        if (bar) bar.style.width = pct + '%';
        if (label) label.textContent = total > 0 ? pct + '%' : '—';
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
        setCoverageChart('centers', json.attendcenter, json.totalcenter);
        setCoverageChart('institutes', json.attendinstitute, json.totalinstitutes);
    }

    load();
});
</script>
