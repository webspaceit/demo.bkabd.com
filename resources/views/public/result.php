<?php
/**
 * Public scholarship result search form.
 * Port of original resources/views/result.blade.php (same fields, labels,
 * messages). Dropdowns are server-side (cached in ResultPortalService),
 * the math captcha equation is issued server-side — the answer lives only
 * in the session, never in a hidden field.
 *
 * @var array $sessions  rows: session
 * @var array $divisions rows: id, name
 * @var array $centers   rows: id, name (users.name)
 * @var string $captchaEquation e.g. "7+8"
 * @var array $errors    flashed validation errors (one-shot)
 * @var array $old       flashed input (one-shot)
 * @var string|null $gateError flashed gate / no-student message
 */
$val = fn(string $field, mixed $default = '') => $old[$field] ?? $default;
$fieldError = function (string $field) use ($errors): string {
    $msgs = (array) ($errors[$field] ?? []);
    $cls = $msgs ? 'field-error' : 'field-error hidden';
    return '<p class="' . $cls . '" data-error-for="' . $field . '">' . e(implode(' ', $msgs)) . '</p>';
};
?>
<div class="bg-gradient-to-b from-emerald-50 via-slate-50 to-slate-100">
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:py-12">

        <!-- Branded hero -->
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
            <div class="bg-gradient-to-r from-emerald-800 via-emerald-700 to-emerald-600 px-6 py-7 text-center text-white">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>
                </div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-emerald-50/90">Bangladesh Kindergarten Association</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Scholarship Exam Result</h1>
                <p class="mx-auto mt-2 max-w-md text-sm text-emerald-50/90">Enter the exam details below to view the published result.</p>
            </div>
            <div class="bg-slate-50/70 px-6 py-4 text-center">
                <img src="/assets/img/result/header.png" alt="BKABD" class="mx-auto w-full max-w-[560px]">
            </div>
        </div>

        <!-- Search card -->
        <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
            <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                </span>
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Find your result</h2>
                    <p class="text-xs text-slate-500">All fields are required.</p>
                </div>
            </div>

            <div class="px-6 py-5 sm:px-7">
                <?php if ($gateError): ?>
                    <div class="mb-5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                        <span><?= e($gateError) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/result/search" id="result-search-form" class="grid gap-4 sm:grid-cols-2" novalidate>
                    <?= csrf_field() ?>

                    <div>
                        <label class="label" for="session_id">Session</label>
                        <select id="session_id" name="session_id" data-field="session_id" class="uppercase">
                            <option value="">Select Session</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= e($s['session']) ?>" <?= (string) $val('session_id') === (string) $s['session'] ? 'selected' : '' ?>><?= e($s['session']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fieldError('session_id') ?>
                    </div>

                    <div>
                        <label class="label" for="division_id">Division</label>
                        <select id="division_id" name="division_id" data-field="division_id" class="uppercase">
                            <option value="">Select Division</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= e($d['id']) ?>" <?= (string) $val('division_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fieldError('division_id') ?>
                    </div>

                    <div>
                        <label class="label" for="center_id">Name of Center</label>
                        <select id="center_id" name="center_id" data-field="center_id" class="uppercase">
                            <option value="">Select Center</option>
                            <?php foreach ($centers as $c): ?>
                                <option value="<?= e($c['id']) ?>" <?= (string) $val('center_id') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fieldError('center_id') ?>
                    </div>

                    <div>
                        <label class="label" for="class">Name of Class</label>
                        <select id="class" name="class" data-field="class" class="uppercase">
                            <option value="">Select Class</option>
                            <?php foreach ([6 => 'Kg', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five'] as $cv => $cl): ?>
                                <option value="<?= $cv ?>" <?= (string) $val('class') === (string) $cv ? 'selected' : '' ?>><?= e($cl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fieldError('class') ?>
                    </div>

                    <div>
                        <label class="label" for="registration">Registration No</label>
                        <input type="text" id="registration" name="registration" data-field="registration"
                               placeholder="Registration No" autocomplete="off" value="<?= e($val('registration')) ?>">
                        <?= $fieldError('registration') ?>
                    </div>

                    <div>
                        <label class="label" for="roll">Roll No</label>
                        <input type="text" id="roll" name="roll" data-field="roll"
                               placeholder="Roll No" autocomplete="off" value="<?= e($val('roll')) ?>">
                        <?= $fieldError('roll') ?>
                    </div>

                    <!-- Security check -->
                    <div class="sm:col-span-2">
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                            <label class="label mb-0" for="captcha">Security check</label>
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <span class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-base font-semibold text-emerald-800 ring-1 ring-emerald-200">
                                    <?= e($captchaEquation) ?> <span class="text-slate-400">=</span> <span class="text-slate-400">?</span>
                                </span>
                                <input type="number" id="captcha" name="captcha" data-field="captcha" inputmode="numeric"
                                       placeholder="Type the total" autocomplete="off" class="sm:max-w-[200px]">
                            </div>
                            <?= $fieldError('captcha') ?>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:col-span-2 sm:flex-row sm:justify-end">
                        <button type="button" id="result-clear" class="btn btn-light">Clear</button>
                        <button type="submit" id="result-submit" class="btn btn-primary sm:min-w-[180px]">Submit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Contact / help -->
        <div class="mt-6 grid gap-3 rounded-2xl bg-white/70 px-6 py-5 text-center text-xs text-slate-500 ring-1 ring-slate-200/70 sm:grid-cols-3 sm:text-left">
            <div class="flex items-start justify-center gap-2 sm:justify-start">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                <span>59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh</span>
            </div>
            <div class="flex items-start justify-center gap-2 sm:justify-start">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="m4 6 8 6 8-6"/></svg>
                <span>infobkabd@gmail.com, info@bkabd.com</span>
            </div>
            <div class="flex items-start justify-center gap-2 sm:justify-start">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18Z"/></svg>
                <span>www.bkabd.com &middot; www.bka.org.bd</span>
            </div>
        </div>

        <p class="mt-6 text-center">
            <a href="https://www.bkabd.com" class="btn btn-dark btn-sm">Go to Main Website</a>
        </p>
    </div>
</div>
