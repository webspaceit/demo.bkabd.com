<?php
/**
 * Guest/public shell: no sidebar, minimal header.
 * Receives $content, optional $title.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/png" href="/assets/img/result/logo.png?v=<?= (int) @filemtime(BASE_PATH . '/public/assets/img/result/logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Login') ?> | <?= e(\App\Core\Config::get('APP_NAME', 'BKABD')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) @filemtime(__DIR__ . '/../../../public/assets/css/app.css') ?>">
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col">
<header class="bg-slate-900 text-white">
    <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="/" class="text-lg font-bold tracking-wide">BKABD <span class="text-emerald-400">Exam</span></a>
        <nav class="flex items-center gap-4 text-sm">
            <a href="/scholarship-result" class="hover:text-emerald-400">Results</a>
            <a href="/login" class="hover:text-emerald-400">Login</a>
        </nav>
    </div>
</header>
<main class="flex-1">
    <?= $content ?>
</main>
<footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
    &copy; <?= date('Y') ?> BKABD Scholarship Exam
</footer>
<div id="toast-stack" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
<script src="/assets/js/app.js?v=<?= (int) @filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?>"></script>
<?php foreach ((array) ($pageScripts ?? []) as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
