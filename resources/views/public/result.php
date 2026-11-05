<?php
/**
 * Public scholarship result search form.
 *
 * Registration No is UNIQUE, so it alone identifies a student. The exam-detail
 * fields (Session / Class / Roll — which form a UNIQUE index) are kept as an
 * optional fallback for students who only hold their admit card. The math
 * captcha equation is issued server-side — the answer lives only in the
 * session, never in a hidden field.
 *
 * @var array $sessions  rows: session
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
$fallbackUsed = (string) $val('session_id') !== '' || (string) $val('roll') !== '' || (string) $val('class') !== '';
$mainWebsite = trim(explode(',', brand('website'))[0] ?? '');
if ($mainWebsite !== '' && !preg_match('~^https?://~i', $mainWebsite)) { $mainWebsite = 'https://' . $mainWebsite; }
?>
<style>
    .result-membership-page { background: transparent; }
    .result-membership-intro { padding: 1rem; text-align: center; }
    .result-membership-intro .membership-kicker { display: inline-flex; margin-bottom: .35rem; padding: .35rem .75rem; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; background: rgba(255,255,255,.1); color: #d1fae5; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .result-membership-intro h1 { margin: 0; color: #fff; font-size: clamp(1.25rem, 2.4vw, 1.8rem); line-height: 1.2; font-weight: 800; }
    .result-membership-intro p { margin: .35rem 0 0; color: #ecfdf5; font-size: .95rem; line-height: 1.5; }
    .result-contact-address { white-space: nowrap; font-size: clamp(.55rem, 1vw, .75rem); }
    .result-contact-email { justify-content: center; text-align: center; }
    .result-form-card { overflow: visible; border: 1px solid #d1fae5; border-radius: 1.5rem; background: linear-gradient(145deg,#fff 0%,#fbfffd 100%); box-shadow: 0 16px 42px rgba(15,23,42,.08); }
    .result-form-card-body { padding: clamp(1rem, 2.5vw, 2.25rem); }
    .result-form-heading { margin-bottom: 1.5rem; }
    .result-form-heading h2 { margin: 0; color: #0f172a; font-size: 1.25rem; line-height: 1.5rem; font-weight: 700; }
    .result-form-heading p { margin: .25rem 0 0; color: #64748b; font-size: .875rem; }
    .result-form-fields .fl .input,
    .result-form-fields #captcha { min-height: 3.15rem; border: 1px solid #cbd5e1; border-radius: .8rem; background-color: #fff; transition: border-color .18s, box-shadow .18s; }
    .result-form-fields .fl .input:focus,
    .result-form-fields #captcha:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(16,185,129,.12); outline: none; }
    .result-form-fields .fl label { border-color: #cbd5e1; border-radius: .5rem .5rem 0 0; font-weight: 600; }
    .result-form-fields .fl:focus-within label { border-color: #059669; color: #047857; }
    .result-select-wrap { position: relative; }
    .result-select-wrap > select { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    .result-select-button { display: flex; width: 100%; min-height: 3.15rem; align-items: center; justify-content: space-between; gap: .75rem; padding: .65rem 1rem; border: 1px solid #cbd5e1; border-radius: .8rem; background: #fff; color: #0f172a; text-align: left; transition: border-color .18s, box-shadow .18s; }
    .result-select-button:hover { border-color: #6ee7b7; }
    .result-select-button:focus-visible, .result-select-wrap.is-open .result-select-button { border-color: #059669; box-shadow: 0 0 0 4px rgba(16,185,129,.12); outline: none; }
    .result-select-button .is-placeholder { color: #64748b; }
    .result-select-chevron { width: 1rem; height: 1rem; flex: 0 0 1rem; color: #475569; transition: transform .16s ease; }
    .result-select-wrap.is-open .result-select-chevron { transform: rotate(180deg); }
    .result-select-menu { position: absolute; z-index: 30; top: calc(100% + .4rem); left: 0; right: 0; max-height: 15rem; overflow-y: auto; padding: .35rem; border: 1px solid #d1fae5; border-radius: .85rem; background: #fff; box-shadow: 0 16px 35px rgba(15,23,42,.16); }
    .result-select-menu[hidden] { display: none; }
    .result-select-option { display: flex; width: 100%; min-height: 2.55rem; align-items: center; justify-content: space-between; gap: .75rem; padding: .55rem .75rem; border: 0; border-radius: .55rem; background: transparent; color: #334155; text-align: left; cursor: pointer; }
    .result-select-option:hover, .result-select-option:focus-visible { background: #f0fdf4; color: #065f46; outline: none; }
    .result-select-option[aria-selected="true"] { background: #ecfdf5; color: #047857; font-weight: 600; }
    .result-select-option[aria-disabled="true"] { color: #94a3b8; cursor: default; }
</style>
<div class="membership-page result-membership-page">
    <?= partial('shared.membership_header') ?>
    <section class="membership-hero result-membership-intro">
        <span class="membership-kicker">Results</span>
        <h1><?= e(brand('exam_name')) ?> Result</h1>
        <p>Enter your registration number to view the published result.</p>
    </section>

        <!-- Search card -->
        <div class="result-form-card mt-6">
            <div class="result-form-card-body">
                <div class="result-form-heading">
                    <h2>Find your result</h2>
                    <p>Enter your registration number, or search by exam details.</p>
                </div>
                <?php if ($gateError): ?>
                    <div class="mb-5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                        <span><?= e($gateError) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/result/search" id="result-search-form" class="result-form-fields" novalidate>
                    <?= csrf_field() ?>

                    <!-- Primary: registration number (unique) -->
                    <div class="fl">
                        <input type="text" id="registration" name="registration" class="input" data-field="registration"
                               placeholder="e.g. 2300300012001" autocomplete="off" autofocus
                               value="<?= e($val('registration')) ?>">
                        <label for="registration">Registration No</label>
                        <?= $fieldError('registration') ?>
                        <p class="mt-1 text-xs text-slate-400">Printed on your admit card / registration certificate.</p>
                    </div>

                    <!-- Fallback: exam details (session + class + roll is unique) -->
                    <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4" <?= $fallbackUsed ? 'open' : '' ?>>
                        <summary class="cursor-pointer select-none text-sm font-medium text-slate-700">
                            Don't have a registration number? &mdash; search by exam details
                        </summary>

                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <div class="fl">
                                <select id="session_id" name="session_id" data-field="session_id" class="uppercase input">
                                    <option value="">Select Session</option>
                                    <?php foreach ($sessions as $s): ?>
                                        <option value="<?= e($s['session']) ?>" <?= (string) $val('session_id') === (string) $s['session'] ? 'selected' : '' ?>><?= e($s['session']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="session_id-button">Session</label>
                                <?= $fieldError('session_id') ?>
                            </div>

                            <div class="fl">
                                <select id="class" name="class" data-field="class" class="uppercase input">
                                    <option value="">Select Class</option>
                                    <?php foreach ([6 => 'Kg', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five'] as $cv => $cl): ?>
                                        <option value="<?= $cv ?>" <?= (string) $val('class') === (string) $cv ? 'selected' : '' ?>><?= e($cl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="class-button">Class</label>
                                <?= $fieldError('class') ?>
                            </div>

                            <div class="fl">
                                <input type="text" id="roll" name="roll" class="input" data-field="roll"
                                       placeholder="Roll No" autocomplete="off" value="<?= e($val('roll')) ?>">
                                <label for="roll">Roll No</label>
                                <?= $fieldError('roll') ?>
                            </div>
                        </div>
                    </details>

                    <!-- Security check -->
                    <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
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

                    <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" id="result-clear" class="btn btn-light">Clear</button>
                        <button type="submit" id="result-submit" class="btn btn-primary sm:min-w-[180px]">Submit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Contact / help -->
        <div class="mt-6 grid gap-3 rounded-2xl bg-white/70 px-6 py-5 text-center text-xs text-slate-500 ring-1 ring-slate-200/70 sm:grid-cols-3 sm:text-left">
            <div class="result-contact-address flex items-center justify-center gap-2 sm:justify-start">
                <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                <span><?= e(brand('address')) ?></span>
            </div>
            <div class="result-contact-email flex items-center justify-center gap-2">
                <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="m4 6 8 6 8-6"/></svg>
                <span><?= e(brand('email')) ?></span>
            </div>
            <div class="flex items-center justify-center gap-2 sm:justify-start">
                <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18Z"/></svg>
                <span><?= e(brand('website')) ?></span>
            </div>
        </div>

        <p class="mt-6 text-center">
            <?php if ($mainWebsite !== ''): ?><a href="<?= e($mainWebsite) ?>" class="btn btn-dark btn-sm">Go to <?= e(brand('authority_name')) ?> Website</a><?php endif; ?>
        </p>
</div>
