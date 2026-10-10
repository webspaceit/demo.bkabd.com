<?php
/**
 * Report Center — every report sheet / export, organized into scoped groups.
 *
 * Each report button carries a data-report URL template; the inline script fills
 * in the chosen center / institute and opens the endpoint in a new tab (sheets
 * are served as printable HTML, Excel exports download as .xlsx).
 * Scoped buttons stay disabled until the required Center / Institute is picked.
 * Original Vue pages: resources/js/components/report/*.vue
 */

/** [label, url template, icon, format?, needs] — {center} / {institute} replaced at runtime.
 *  needs: '' (always on) | 'center' | 'institute' | 'both' | 'center-optional'
 *  ('center-optional' = runs for all centers by default; scopes to the picked center). */
// Labels mirror the exact report names used by the original Vue pages
// (components/reports/*.vue 'exampleModalLabel'), so anyone searching by the
// original report name can find the tile.
$centerReports = [
    ['Total Students Count Report',                    '/api/centerinfo/{center}', 'info'],
    ['Center Wise Students Details Report',            '/api/centerwisedetails/{center}', 'users'],
    ['Center Wise Students Details Report (Without Image)', '/api/centerwisedetailswithoutimage/{center}', 'user-x'],
    ['Center Wise Students Details Report (Excel)',    '/api/centerstudentdetailsexport/{center}', 'sheet', 'xlsx'],
    ['Center Wise Students Details Report (Without Image) (Excel)', '/api/centerstudentdetailsexportwithoutimage/{center}', 'sheet', 'xlsx'],
    ['Class Wise Exam Attend Sheet Report',            '/api/ClassWiseExamAttendSheetReport/{center}', 'checklist'],
    ["Scholarship Exam Student's Attend Sheet Report (Class Wise)", '/api/ClassWiseExamAttendSheetReport2/{center}', 'checklist'],
    ['Class Wise Exam Attend Sheet Report with Photo', '/api/ClassWiseExamAttendSheetReportWithPhoto/{center}', 'camera'],
    ["Scholarship Exam Student's Attend Sheet Report (Class Wise) with Photo", '/api/ClassWiseExamAttendSheetReportWithPhoto2/{center}', 'camera'],
    ['Center Wise Student Details Report (Issued Admit Card)', '/api/CenterWiseStudentDetailsReportIssuedAdmitCard/{center}', 'id-card'],
    ['Center Wise Highest Marks Report',               '/api/CenterWiseHighestMarksReport/{center}', 'trophy'],
    ['Center Wise Tabulation Sheet',                   '/api/CenterWiseTabulationSheet/{center}', 'table'],
    ['Center Wise Grade Sheet Count Report',           '/api/CenterWiseGradeSheetCountReport/{center}', 'layers'],
    ['Center Wise Grade Sheet Result Details',         '/api/CenterWiseGradeSheetResultDetails/{center}', 'award'],
    ['Merit List Result with Position',                '/api/MeritListResultwithPosition/{center}', 'list'],
];

$instituteReports = [
    ['Institute Wise Students Details Report',         '/api/institutewisedetails/{institute}', 'users', '', 'institute'],
    ['Institute Wise Students Details Report (Excel)', '/api/institutestudentdetailsexport/{institute}', 'sheet', 'xlsx', 'institute'],
    ['Institute Wise Highest Marks Report',            '/api/InstituteWiseHighestMarksReport/{center}/{institute}', 'trophy', '', 'both'],
    ['Institute Wise Grade Sheet Result Details',      '/api/InstituteWiseGradeSheetResultDetails/{center}/{institute}', 'award', '', 'both'],
];

// All-center reports. Only Total Institute Information genuinely scopes per
// center in the original (where('centers.id', $id)); the rest ignore the id and
// are all-center reports, so they stay on "/0" ('center-optional' below defaults
// to "All" and uses the picked center when one is selected).
$globalReports = [
    ['Total Students Count Report (All Centers)',      '/api/centerinfo/0', 'map'],
    ['Total Institute Information',                    '/api/TotalInstituteInformation/{center}', 'building', '', 'center-optional'],
    ['Life Time Membership Institute Report',          '/api/LifeTimeMembershipInstituteReport/0', 'infinity'],
    ['Temporary Membership Institute Report',          '/api/TemporaryMembershipInstituteReport/0', 'timer'],
    ['Documents Distribution Report',                  '/api/DocumentsDistributionReport/0', 'folder'],
    ['Educational Type Student Count Report',          '/api/EducationalTypeStudentCountReport/0', 'pie'],
    ['All Center Details Information Report',          '/api/AllCenterDetailsInformationReport/0', 'file-text'],
    ['Center Wise T.P, G.G, S.G & Merit List Position Count Report', '/api/CenterWiseGradeListMeritPositionCountReport/0', 'bar'],
];

/**
 * Lucide-style 24x24 stroke icon renderer (same visual language as the sidebar).
 */
function rk_report_icon(string $name, string $cls = ''): string
{
    $paths = [
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-x' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 8 5 5"/><path d="m21 8-5 5"/>',
        'sheet' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 15h8"/><path d="M8 18h5"/>',
        'checklist' => '<path d="m3 5 2 2 3-3"/><path d="M10 4h11"/><path d="m3 12 2 2 3-3"/><path d="M10 11h11"/><path d="m3 19 2 2 3-3"/><path d="M10 18h11"/>',
        'camera' => '<path d="M2 8a2 2 0 0 1 2-2h2l1.5-2h5L14 6h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8Z"/><circle cx="11" cy="11" r="3.5"/>',
        'id-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M6 16c0-1.1.9-2 2-2s2 .9 2 2"/><path d="M14 9h4"/><path d="M14 13h3"/>',
        'trophy' => '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v6a5 5 0 0 1-10 0V4Z"/><path d="M7 6H4a1 1 0 0 0-1 1v1a4 4 0 0 0 4 4"/><path d="M17 6h3a1 1 0 0 1 1 1v1a4 4 0 0 1-4 4"/>',
        'table' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M3 14h18"/><path d="M9 4v16"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M15.5 12.9 17 22l-5-3-5 3 1.5-9.1"/>',
        'list' => '<path d="M10 6h11"/><path d="M10 12h11"/><path d="M10 18h11"/><path d="M4 6h1"/><path d="M4 10h1"/><path d="M4 14h1"/><path d="M4 18h1"/>',
        'building' => '<path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16"/><path d="M15 21V9h2a2 2 0 0 1 2 2v10"/><path d="M9 7h2"/><path d="M9 11h2"/><path d="M9 15h2"/>',
        'map' => '<path d="M9 3 3 6v15l6-3 6 3 6-3V3l-6 3-6-3Z"/><path d="M9 3v15"/><path d="M15 6v15"/>',
        'infinity' => '<path d="M12 12c-1.8-2.4-3.3-3.5-4.8-3.5a4.8 4.8 0 0 0 0 9.6c1.5 0 3-1.1 4.8-3.5 1.8 2.4 3.3 3.5 4.8 3.5a4.8 4.8 0 0 0 0-9.6c-1.5 0-3 1.1-4.8 3.5Z"/>',
        'timer' => '<path d="M10 2h4"/><path d="M12 14l3-3"/><circle cx="12" cy="14" r="7"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 3h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>',
        'pie' => '<path d="M21.2 15.9A10 10 0 1 1 8 2.8"/><path d="M22 12A10 10 0 0 0 12 2v10Z"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'bar' => '<path d="M3 3v18h18"/><path d="M7 16v-5"/><path d="M12 16V8"/><path d="M17 16v-3"/>',
        'cert' => '<path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'print' => '<path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/>',
    ];
    $p = $paths[$name] ?? '<circle cx="12" cy="12" r="4"/>';
    return '<svg class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}

/**
 * One report row — icon tile, label, format badge, run arrow.
 * Scoped rows (needs !== '') render disabled and unlock once a selection is made.
 */
function rk_report_row(string $label, string $url, string $icon, string $needs = '', string $fmt = ''): string
{
    $tone = $fmt === 'xlsx'
        ? 'bg-slate-900 text-white'
        : 'bg-emerald-50 text-emerald-600';
    $badge = $fmt === 'xlsx'
        ? '<span class="badge badge-green">Excel</span>'
        : '<span class="badge badge-gray">Sheet</span>';
    $title = $needs === 'both' ? 'Select a center and institute first' : ($needs === 'center' ? 'Select a center first' : ($needs === 'institute' ? 'Select an institute first' : ($needs === 'center-optional' ? 'All centers by default — pick a center to scope it' : '')));
    $disabled = $needs !== '' && $needs !== 'center-optional';
    return '<button type="button" data-report="' . e($url) . '"' . ($needs !== '' ? ' data-needs="' . e($needs) . '"' : '')
        . ($disabled ? ' disabled' : '') . ' title="' . e($title) . '"'
        . ' class="group flex items-center gap-3 w-full px-5 py-2 text-left transition hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent">'
        . '<span class="w-7 h-7 rounded-lg ' . $tone . ' flex items-center justify-center shrink-0">' . rk_report_icon($icon, 'w-4 h-4') . '</span>'
        . '<span class="text-sm font-medium text-slate-700 group-hover:text-slate-900 truncate">' . e($label) . '</span>'
        . '<span class="ml-auto flex items-center gap-2 shrink-0">' . $badge
        . rk_report_icon('arrow', 'w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors') . '</span></button>';
}

/** A section card: icon header + count badge + divided list of rows. */
function rk_report_card(string $title, string $icon, string $rows, string $count): string
{
    return '<section class="card">'
        . '<div class="card-header">'
        . '<span class="flex items-center gap-2 min-w-0">'
        . '<span class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">' . rk_report_icon($icon, 'w-3.5 h-3.5') . '</span>'
        . '<span class="truncate">' . e($title) . '</span>'
        . '</span>'
        . '<span class="badge badge-gray shrink-0">' . e($count) . '</span>'
        . '</div>'
        . '<div class="divide-y divide-slate-100">' . $rows . '</div>'
        . '</section>';
}

$centerRows = '';
foreach ($centerReports as $r) {
    $centerRows .= rk_report_row($r[0], $r[1], $r[2], 'center', $r[3] ?? '');
}
$instituteRows = '';
foreach ($instituteReports as $r) {
    $instituteRows .= rk_report_row($r[0], $r[1], $r[2], $r[4] ?? 'both', $r[3] ?? '');
}
$globalRows = '';
foreach ($globalReports as $r) {
    $globalRows .= rk_report_row($r[0], $r[1], $r[2], $r[4] ?? '', $r[3] ?? '');
}
?>
<div class="space-y-5" id="reports-page">
    <!-- Page intro -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-slate-800">Report Center</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Sheet reports run against the active session (switch it from the top bar).
                Printable sheets open in a new tab; Excel downloads arrive as .xlsx.
            </p>
        </div>
        <div class="inline-flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500 shrink-0">
            <span class="inline-flex items-center gap-1.5">
                <span class="w-5 h-5 rounded bg-emerald-50 text-emerald-600 flex items-center justify-center"><?= rk_report_icon('print', 'w-3 h-3') ?></span>
                Printable sheet
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-5 h-5 rounded bg-slate-900 text-white flex items-center justify-center"><?= rk_report_icon('sheet', 'w-3 h-3') ?></span>
                Excel download
            </span>
        </div>
    </div>

    <!-- Scope selector -->
    <div class="card">
        <div class="card-header">
            <span class="flex items-center gap-2 min-w-0">
                <span class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><?= rk_report_icon('target', 'w-3.5 h-3.5') ?></span>
                <span class="truncate">Report scope</span>
            </span>
            <span class="text-xs text-slate-500 font-normal">Pick a center (and institute) to unlock scoped reports — all-center reports run for every center unless one is picked</span>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="rp-center">Center</label>
                    <select id="rp-center"><option value="">Select Center</option></select>
                </div>
                <div>
                    <label class="label" for="rp-institute">Institute</label>
                    <select id="rp-institute"><option value="">Select Institute</option></select>
                </div>
            </div>
        </div>
    </div>

    <!-- Certificates -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php if (can('student-certificate')): ?>
            <a href="/student-certificate" class="card card-body flex items-center gap-3 hover:shadow-md transition group">
                <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><?= rk_report_icon('award', 'w-4 h-4') ?></span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-800 group-hover:text-emerald-700 transition">Student Certificate</span>
                    <span class="block text-xs text-slate-500 mt-0.5">Printable certificate for grade 1–3 students.</span>
                </span>
                <span class="ml-auto shrink-0"><?= rk_report_icon('arrow', 'w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors') ?></span>
            </a>
        <?php endif; ?>
        <?php if (can('institute-certificate')): ?>
            <a href="/institute-certificate" class="card card-body flex items-center gap-3 hover:shadow-md transition group">
                <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><?= rk_report_icon('cert', 'w-4 h-4') ?></span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-800 group-hover:text-emerald-700 transition">Institute Certificate</span>
                    <span class="block text-xs text-slate-500 mt-0.5">Membership certificate for an institute.</span>
                </span>
                <span class="ml-auto shrink-0"><?= rk_report_icon('arrow', 'w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors') ?></span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Report groups -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
        <?= rk_report_card('Center Reports', 'layers', $centerRows, count($centerReports) . ' reports') ?>
        <div class="grid gap-4">
            <?= rk_report_card('Institute Reports', 'building', $instituteRows, count($instituteReports) . ' reports') ?>
            <?= rk_report_card('All-Center Reports', 'map', $globalRows, count($globalReports) . ' reports') ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const root = document.getElementById('reports-page');
    if (!root) { return; }
    const centerSel = document.getElementById('rp-center');
    const instituteSel = document.getElementById('rp-institute');

    function refreshLocks() {
        root.querySelectorAll('[data-report][data-needs]').forEach(function (btn) {
            const needs = btn.getAttribute('data-needs');
            const lock = (needs === 'center' && !centerSel.value)
                || (needs === 'both' && (!centerSel.value || !instituteSel.value))
                || (needs === 'institute' && !instituteSel.value);
            btn.disabled = lock;
        });
    }

    function options(items, placeholder) {
        let html = '<option value="">' + BK.esc(placeholder) + '</option>';
        (items || []).forEach(function (item) {
            html += '<option value="' + BK.esc(String(item.id)) + '">' + BK.esc(item.name) + '</option>';
        });
        return html;
    }

    centerSel.addEventListener('change', async function () {
        instituteSel.innerHTML = options([], 'Select Institute');
        refreshLocks();
        if (!centerSel.value) { return; }
        try {
            const json = await BK.get('/api/instituteListByCenter?center_id=' + encodeURIComponent(centerSel.value));
            instituteSel.innerHTML = options((json.data && json.data.instituteList) || [], 'Select Institute');
            refreshLocks();
        } catch (err) {
            BK.toast(err.message || 'Could not load institutes', 'error');
        }
    });
    instituteSel.addEventListener('change', refreshLocks);

    try {
        const json = await BK.get('/api/center');
        centerSel.innerHTML = options(json.centerList || [], 'Select Center');
        refreshLocks();
    } catch (err) {
        BK.toast(err.message || 'Could not load centers', 'error');
    }

    root.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-report]');
        if (!btn || btn.disabled) { return; }
        const needs = btn.getAttribute('data-needs') || '';
        if ((needs === 'center' || needs === 'both') && !centerSel.value) {
            BK.toast('Please select a center', 'error');
            return;
        }
        if ((needs === 'both' || needs === 'institute') && !instituteSel.value) {
            BK.toast('Please select an institute', 'error');
            return;
        }
        const centerVal = needs === 'center-optional' ? (centerSel.value || '0') : centerSel.value;
        const url = btn.getAttribute('data-report')
            .replace('{center}', encodeURIComponent(centerVal))
            .replace('{institute}', encodeURIComponent(instituteSel.value));
        window.open(url, '_blank');
    });
});
</script>