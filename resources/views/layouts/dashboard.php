<?php
/**
 * Authenticated app shell — Rubick-style layout with the BKABD emerald brand:
 * a deep-green (#064e3b) backdrop, a rounded-left light canvas (#f1f5f9),
 * and a curved "notch" active nav item (see .active-nav-notch in app.css).
 * Used by every authenticated page.
 * Receives $content, optional $title, $pageScripts (extra <script> urls).
 */
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;

$user = Auth::user();
$myPerms = Auth::permissions();
$current = Request::capture()->path();

/** label, path, permission(null=always), icon */
$nav = [
    ['Dashboard', '/', null, 'dashboard'],

    ['group:Students', '#', null, 'arrow', [
        ['Student Registration', '/student-registration', 'student-registration', 'userplus'],
        ['Student Details', '/students', 'student-list', 'users'],
    ]],
    ['group:Institutes', '#', null, 'arrow', [
        ['Institute Registration', '/institute-registration', 'institute-create', 'building-plus'],
        ['Institute Details', '/institutes', 'institute-list', 'building'],
        ['Manage Center', '/centers', 'center-list', 'layers'],
    ]],
    ['group:Manage Area', '#', null, 'arrow', [
        ['Divisions Info', '/divisions', 'division-list', 'map'],
        ['District Info', '/districts', 'district-list', 'map-pin'],
        ['Upazila Info', '/upazilas', 'upazila-list', 'navigation'],
        ['Union Info', '/unions', 'union-list', 'navigation'],
    ]],
    ['group:Examinations', '#', null, 'arrow', [
        ['SMS Dashboard', '/sms', 'sms', 'message'],
        ["Mark's Entry", '/markentry', 'mark-entry', 'clipboard'],
        ['Grade Assign', '/gradeassign', 'grade-assign', 'badge-check'],
        ['Admit Card', '/admitcards', 'admit-card', 'id-card'],
        ['Seat Plan', '/seatplan', 'seatplan', 'grid'],
    ]],
    ['group:Certificates', '#', null, 'arrow', [
        ['Student Certificate', '/student-certificate', 'student-certificate', 'award'],
        ['Institute Certificate', '/institute-certificate', 'institute-certificate', 'file-check'],
    ]],
    ['group:Reports & Notices', '#', null, 'arrow', [
        ['All Reports', '/reports', 'report-list', 'bar-chart'],
        ['Notice Board', '/notices', 'notice-list', 'bell'],
    ]],
    ['group:Administration', '#', null, 'arrow', [
        ['All User Access', '/user', 'user-list', 'user-check'],
        ['Routine', '/routines', 'routine-list', 'calendar'],
        ['Session', '/sessions', 'session-list', 'calendar-clock'],
        ['Manage User', '/userrole', 'role-list', 'shield'],
        ['Settings', '/settings', 'settings', 'settings'],
        ['Mark Distribution', '/mark-distributions', 'mark-distributions', 'sliders'],
        ['Role Place', '/role-place', 'role-place', 'key'],
    ]],

    ['Instructions', '/instructions', null, 'book'],
];

function nav_icon(string $name, string $cls = ''): string
{
    // Lucide-style 24x24 stroke icons (consistent 1.75 stroke, round caps/joins).
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'userplus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>',
        'building-plus' => '<path d="M3 21h18"/><path d="M6 21V6a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v15"/><path d="M9 8h2"/><path d="M9 12h2"/><path d="M9 16h2"/><path d="M17 10v6"/><path d="M14 13h6"/>',
        'building' => '<path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16"/><path d="M15 21V9h2a2 2 0 0 1 2 2v10"/><path d="M9 7h2"/><path d="M9 11h2"/><path d="M9 15h2"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'map' => '<path d="M9 3 3 6v15l6-3 6 3 6-3V3l-6 3-6-3Z"/><path d="M9 3v15"/><path d="M15 6v15"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'navigation' => '<path d="m3 11 19-9-9 19-2-8-8-2Z"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
        'clipboard' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6"/><path d="M9 16h6"/>',
        'badge-check' => '<path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'id-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M6 16c0-1.1.9-2 2-2s2 .9 2 2"/><path d="M14 10h4"/><path d="M14 14h3"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M15.5 12.9 17 22l-5-3-5 3 1.5-9.1"/>',
        'file-check' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/>',
        'bar-chart' => '<path d="M3 3v18h18"/><path d="M7 16v-5"/><path d="M12 16V8"/><path d="M17 16v-3"/>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'calendar-clock' => '<path d="M21 13V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><circle cx="18" cy="18" r="4"/><path d="M18 16.5V18l1 1"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'sliders' => '<path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/><path d="M1 14h6"/><path d="M9 8h6"/><path d="M17 16h6"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="3.5"/><path d="m10.5 12.5 8-8"/><path d="m16 4 3 3"/><path d="m13.5 6.5 3 3"/>',
        'book' => '<path d="M2 4h6a4 4 0 0 1 4 4v12a3 3 0 0 0-3-3H2Z"/><path d="M22 4h-6a4 4 0 0 0-4 4v12a3 3 0 0 1 3-3h7Z"/>',
    ];
    $p = $paths[$name] ?? '<circle cx="12" cy="12" r="4"/>';
    return '<svg class="w-[18px] h-[18px] shrink-0 ' . $cls . '" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}

$canSee = function (?string $perm) use ($myPerms): bool {
    return $perm === null || in_array($perm, $myPerms, true);
};

$isActive = function (string $path) use ($current): bool {
    return $path === '/'
        ? $current === '/'
        : ($current === $path || str_starts_with($current, $path . '/'));
};

$sessions = [];
if (Auth::isAdmin()) {
    $sessions = \App\Models\ExamSession::query()->orderBy('id', 'desc')->get();
}
$activeSession = Auth::activeSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Dashboard') ?> | <?= e(\App\Core\Config::get('APP_NAME', 'BKABD')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) @filemtime(__DIR__ . '/../../../public/assets/css/app.css') ?>">
</head>
<body class="bg-[#064e3b] text-slate-700 antialiased selection:bg-emerald-600 selection:text-white">
<div class="flex min-h-screen bg-[#064e3b]">

    <!-- Sidebar -->
    <aside id="sidebar"
           class="fixed lg:sticky top-0 left-0 z-40 h-screen w-64 shrink-0 -translate-x-full lg:translate-x-0 transition-transform
                  bg-[#064e3b] flex flex-col justify-between pt-6 pb-5 pl-5 pr-0 text-white overflow-y-auto"
           aria-label="Main navigation">
        <div>
            <div class="flex items-center gap-3 px-3 mb-5">
                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center border border-white/30 shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>
                </div>
                <span class="text-xl font-bold tracking-tight text-white whitespace-nowrap">BKABD Exam</span>
            </div>

            <div class="px-3 pr-5 mb-4">
                <div class="relative">
                    <input id="nav-search" type="text" placeholder="Search menu..." autocomplete="off"
                           class="w-full bg-white/10 border border-white/10 rounded-full py-2 pl-4 pr-9 text-xs text-white placeholder-emerald-100/50 focus:outline-none focus:ring-2 focus:ring-white/30 focus:border-white/30 transition">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <svg class="w-3.5 h-3.5 text-emerald-100/60" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    </div>
                </div>
            </div>

            <nav id="sidebar-nav" class="space-y-0.5 text-[13px] font-medium">
                <?php foreach ($nav as $entry): ?>
                    <?php
                    $label = $entry[0];
                    $path = $entry[1] ?? '#';
                    $perm = $entry[2] ?? null;
                    $icon = $entry[3] ?? 'dashboard';
                    $children = $entry[4] ?? [];
                    ?>
                    <?php if (str_starts_with($label, 'group:')) { ?>
                        <?php if (!$canSee($perm)) { continue; } ?>
                        <?php
                        $groupName = substr($label, 6);
                        $groupId = 'nav-group-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($groupName));
                        // A section only appears when the user may see at least one of its items.
                        $visibleChildren = array_values(array_filter(
                            $children,
                            static function ($c) use ($canSee) { return $canSee($c[2] ?? null); }
                        ));
                        if (!$visibleChildren) { continue; }
                        ?>
                        <button type="button" data-nav-header data-nav-group-toggle aria-controls="<?= e($groupId) ?>"
                                aria-expanded="true"
                                class="w-full flex items-center justify-between gap-2 pt-5 pb-2 px-3 text-sm font-bold uppercase tracking-wider text-emerald-50 hover:text-white transition">
                            <span><?= e($groupName) ?></span>
                            <svg data-nav-chevron class="w-4 h-4 shrink-0" style="transition: transform .15s; transform: rotate(90deg)" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        </button>
                        <div id="<?= e($groupId) ?>" data-nav-group data-open="1" class="space-y-0.5">
                            <?php foreach ($visibleChildren as $child): ?>
                                <?php
                                $cLabel = $child[0];
                                $cPath = $child[1];
                                $cIcon = $child[3] ?? 'dashboard';
                                ?>
                                <?php if ($isActive($cPath)): ?>
                                    <div class="relative">
                                        <a href="<?= e($cPath) ?>" data-nav-item
                                           class="active-nav-notch flex items-center gap-3 pl-4 py-3 ml-4">
                                            <?= nav_icon($cIcon) ?>
                                            <span class="truncate"><?= e($cLabel) ?></span>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <a href="<?= e($cPath) ?>" data-nav-item
                                       class="group flex items-center gap-3 px-4 py-2.5 rounded-full text-emerald-50/90 hover:text-white hover:bg-white/10 transition ml-4">
                                        <?= nav_icon($cIcon, 'text-emerald-200/70 group-hover:text-white transition-colors') ?>
                                        <span class="truncate"><?= e($cLabel) ?></span>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php continue; ?>
                    <?php } ?>
                    <?php if (!$canSee($perm)) { continue; } ?>
                    <?php $active = $isActive($path); ?>
                    <?php if ($active): ?>
                        <div class="relative">
                            <a href="<?= e($path) ?>" data-nav-item
                               class="active-nav-notch flex items-center gap-3 pl-4 py-3">
                                <?= nav_icon($icon) ?>
                                <span class="truncate"><?= e($label) ?></span>
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="<?= e($path) ?>" data-nav-item
                           class="group flex items-center gap-3 px-4 py-2.5 rounded-full text-emerald-50/90 hover:text-white hover:bg-white/10 transition">
                            <?= nav_icon($icon, 'text-emerald-200/70 group-hover:text-white transition-colors') ?>
                            <span class="truncate"><?= e($label) ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div data-nav-empty class="hidden px-4 py-4 text-xs text-emerald-100/70">No menu matches your search.</div>
            </nav>
        </div>

    </aside>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden"></div>

    <!-- Light canvas -->
    <main class="flex-1 bg-[#f1f5f9] lg:rounded-l-[30px] px-4 md:px-8 py-5 min-w-0 relative z-10">
        <!-- Top bar -->
        <header class="flex flex-col sm:flex-row sm:items-center justify-between pb-5 gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <button id="sidebar-toggle" class="lg:hidden p-2 -ml-2 rounded hover:bg-slate-200 shrink-0" aria-label="Toggle menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <nav class="flex items-center text-sm font-medium text-slate-500 gap-2 min-w-0" aria-label="Breadcrumb">
                    <span class="hidden sm:inline">BKABD</span>
                    <svg class="hidden sm:inline w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                    <span class="text-slate-800 font-semibold truncate"><?= e($title ?? 'Dashboard') ?></span>
                </nav>
            </div>

            <div class="flex items-center gap-3 md:gap-4">
                <?php if (Auth::isAdmin() && $sessions): ?>
                <select id="session-switcher" class="hidden md:block w-auto border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs bg-white text-slate-700 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none shadow-sm">
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (string) $s['session'] === (string) $activeSession ? 'selected' : '' ?>>
                            Session <?= e($s['session']) ?><?= (int) $s['active'] === 1 ? ' (active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <?php if ($canSee('notice-list')): ?>
                <a href="/notices" class="relative p-1 text-slate-500 hover:text-slate-700 transition shrink-0" title="Notices" aria-label="Notices">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 0 0-4-5.659V5a2 2 0 1 0-4 0v.341A6 6 0 0 0 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
                    <span class="absolute top-0.5 right-0.5 w-2 h-2 bg-emerald-500 rounded-full ring-2 ring-white"></span>
                </a>
                <?php endif; ?>

                <div class="relative" id="profile-menu">
                    <button type="button" id="profile-toggle"
                            class="flex items-center gap-2 rounded-full hover:bg-slate-200/60 p-1 pr-2 transition"
                            aria-haspopup="menu" aria-expanded="false" aria-controls="profile-dropdown">
                        <span class="w-8 h-8 rounded-full ring-2 ring-white shadow-sm bg-emerald-600 text-white flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>
                        </span>
                        <span class="hidden sm:flex flex-col items-start leading-tight min-w-0 max-w-[10rem]">
                            <span class="text-xs font-semibold text-slate-800 truncate"><?= e($user['name'] ?? '') ?></span>
                            <span class="text-[11px] text-slate-400 truncate"><?= e($user['email'] ?? 'Signed in') ?></span>
                        </span>
                        <svg class="hidden sm:block w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <div id="profile-dropdown"
                         class="hidden absolute right-0 top-full mt-2 w-60 bg-white border border-slate-200 rounded-xl shadow-lg z-50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100">
                            <p class="text-sm font-semibold text-slate-800 truncate"><?= e($user['name'] ?? '') ?></p>
                            <p class="text-xs text-slate-400 truncate"><?= e($user['email'] ?? '') ?></p>
                        </div>
                        <a href="/passchange" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
                            Account &amp; Security
                        </a>
                        <form method="POST" action="/logout" class="m-0 border-t border-slate-100">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <?php $flashMsg = flash('success'); if ($flashMsg): ?>
            <div data-flash="success" hidden><?= e($flashMsg) ?></div>
        <?php endif; ?>

        <?= $content ?>

        <footer class="pt-6 pb-2 text-xs text-slate-400">
            &copy; <?= date('Y') ?> BKABD Scholarship Exam — All rights reserved.
        </footer>
    </main>
</div>

<div id="toast-stack" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<script src="/assets/js/app.js?v=<?= (int) @filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?>"></script>
<?php foreach ((array) ($pageScripts ?? []) as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
