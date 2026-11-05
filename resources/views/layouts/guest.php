<?php
use App\Core\Request;

$currentPath = Request::capture()->path();
/**
 * Guest/public shell: no sidebar, minimal header.
 * Receives $content, optional $title.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= e(brand_asset('favicon_path', '/assets/img/result/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Login') ?> | <?= e(brand('app_name', \App\Core\Config::get('APP_NAME', 'BKABD'))) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) @filemtime(__DIR__ . '/../../../public/assets/css/app.css') ?>">
    <style>
        .guest-header { background: linear-gradient(115deg, #0f172a 0%, #111d35 58%, #0b2630 100%); color: #fff; border-bottom: 1px solid rgba(110,231,183,.28); box-shadow: 0 8px 24px rgba(15,23,42,.14); }
        .guest-header-inner { box-sizing: border-box; min-height: 4.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .guest-brand { display: inline-flex; align-items: center; gap: .75rem; min-width: 0; color: #fff; text-decoration: none; }
        .guest-brand-mark { display: grid; place-items: center; width: 2.55rem; height: 2.55rem; flex: 0 0 2.55rem; border-radius: 50%; background: rgba(16,185,129,.14); box-shadow: 0 0 0 1px rgba(110,231,183,.28), 0 5px 14px rgba(0,0,0,.2); }
        .guest-brand-mark img { width: 2rem; height: 2rem; object-fit: contain; }
        .guest-brand-copy { display: grid; gap: .08rem; }
        .guest-brand-name { font-size: 1.05rem; line-height: 1.2; font-weight: 750; letter-spacing: .01em; white-space: nowrap; }
        .guest-brand-tagline { color: #a7f3d0; font-size: .66rem; line-height: 1.2; letter-spacing: .1em; text-transform: uppercase; }
        .guest-nav { display: flex; align-items: center; gap: .5rem; }
        .guest-nav a { display: inline-flex; align-items: center; min-height: 2.35rem; padding: 0 .9rem; border: 1px solid #059669; border-radius: .65rem; color: #fff; background: #059669; font-size: .875rem; font-weight: 600; text-decoration: none; transition: background .16s ease, color .16s ease, border-color .16s ease; }
        .guest-nav a:hover, .guest-nav a:focus-visible { color: #fff; background: #047857; border-color: #047857; outline: none; }
        .guest-nav a[aria-current="page"] { color: #fff; background: #064e3b; border-color: #6ee7b7; box-shadow: inset 0 0 0 1px rgba(167,243,208,.18), 0 3px 10px rgba(6,78,59,.22); }
        @media (max-width: 480px) { .guest-header-inner { min-height: 3.9rem; flex-wrap: wrap; padding-top: .55rem; padding-bottom: .55rem; } .guest-brand { gap: .55rem; } .guest-brand-mark { width: 2.25rem; height: 2.25rem; flex-basis: 2.25rem; } .guest-brand-mark img { width: 1.75rem; height: 1.75rem; } .guest-brand-name { font-size: .95rem; } .guest-brand-tagline { font-size: .55rem; } .guest-nav { width: 100%; justify-content: flex-end; gap: .2rem; } .guest-nav a { min-height: 2.1rem; padding: 0 .55rem; font-size: .78rem; } }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col">
<header class="guest-header">
    <div class="max-w-6xl mx-auto px-4 guest-header-inner">
        <a href="/" class="guest-brand" aria-label="<?= e(brand('app_name', 'BKABD Exam')) ?> home">
            <span class="guest-brand-mark"><img src="<?= e(brand_asset('logo_path', '/assets/img/result/logo.png')) ?>" alt=""></span>
            <span class="guest-brand-copy">
                <span class="guest-brand-name"><?= e(brand('app_name', 'BKABD Exam')) ?></span>
                <span class="guest-brand-tagline">Scholarship Examination</span>
            </span>
        </a>
        <nav class="guest-nav" aria-label="Main navigation">
            <a href="/membership-institute-registration" <?= $currentPath === '/membership-institute-registration' ? 'aria-current="page"' : '' ?>>Membership Application</a>
            <a href="/scholarship-result" <?= $currentPath === '/scholarship-result' ? 'aria-current="page"' : '' ?>>Results</a>
            <?php if (empty($hideLoginLink)): ?>
            <a href="/login" class="guest-nav-primary" <?= $currentPath === '/login' ? 'aria-current="page"' : '' ?>>Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="flex-1">
    <?= $content ?>
</main>
<footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
    &copy; <?= date('Y') ?> <?= e(brand('authority_name')) ?> <?= e(brand('app_name')) ?> — <?= e(brand('copyright_text')) ?>
</footer>
<div id="toast-stack" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
<script src="/assets/js/app.js?v=<?= (int) @filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?>"></script>
<?php foreach ((array) ($pageScripts ?? []) as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
