<?php /** Error page: receives $status, $title, $message. */ ?>
<div class="min-h-[60vh] flex items-center justify-center px-4">
    <div class="text-center">
        <p class="text-6xl font-bold text-emerald-600"><?= (int) $status ?></p>
        <h1 class="mt-3 text-xl font-semibold text-slate-800"><?= e($title ?? 'Error') ?></h1>
        <p class="mt-2 text-sm text-slate-500"><?= e($message ?? '') ?></p>
        <a href="/" class="inline-block mt-6 rounded bg-slate-800 text-white px-4 py-2 text-sm hover:bg-slate-700">Back to home</a>
    </div>
</div>
