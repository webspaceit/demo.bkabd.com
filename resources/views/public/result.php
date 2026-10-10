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
<div class="max-w-3xl mx-auto px-4 py-6">
    <div class="mb-5 text-center text-xs leading-relaxed text-slate-500">
        <img src="/assets/img/result/header.png" alt="BKABD" class="mx-auto mb-3 w-full max-w-[480px]">
        <p><b>59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009</b></p>
        <p><b>Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com/www.bka.com.bd/www.bka.org.bd</b></p>
    </div>

    <div class="card rounded-3xl border-dashed">
        <div class="card-body">
            <h1 class="mb-4 text-center text-lg font-bold text-slate-900">Scholarship Exam Result</h1>

            <?php if ($gateError): ?>
                <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                    <?= e($gateError) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/result/search" id="result-search-form" class="grid gap-4 sm:grid-cols-2">
                <?= csrf_field() ?>

                <div>
                    <label class="label" for="session_id">Session:</label>
                    <select id="session_id" name="session_id" data-field="session_id" class="uppercase">
                        <option value="">Select Session</option>
                        <?php foreach ($sessions as $s): ?>
                            <option value="<?= e($s['session']) ?>" <?= (string) $val('session_id') === (string) $s['session'] ? 'selected' : '' ?>><?= e($s['session']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('session_id') ?>
                </div>

                <div>
                    <label class="label" for="division_id">Division:</label>
                    <select id="division_id" name="division_id" data-field="division_id" class="uppercase">
                        <option value="">Select Division</option>
                        <?php foreach ($divisions as $d): ?>
                            <option value="<?= e($d['id']) ?>" <?= (string) $val('division_id') === (string) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('division_id') ?>
                </div>

                <div>
                    <label class="label" for="center_id">Name of Center:</label>
                    <select id="center_id" name="center_id" data-field="center_id" class="uppercase">
                        <option value="">Select Center</option>
                        <?php foreach ($centers as $c): ?>
                            <option value="<?= e($c['id']) ?>" <?= (string) $val('center_id') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('center_id') ?>
                </div>

                <div>
                    <label class="label" for="class">Name of Class:</label>
                    <select id="class" name="class" data-field="class" class="uppercase">
                        <option value="">Select Class</option>
                        <?php foreach ([6 => 'Kg', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five'] as $cv => $cl): ?>
                            <option value="<?= $cv ?>" <?= (string) $val('class') === (string) $cv ? 'selected' : '' ?>><?= e($cl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('class') ?>
                </div>

                <div>
                    <label class="label" for="registration">Registration No:</label>
                    <input type="text" id="registration" name="registration" data-field="registration"
                           placeholder="Registration No" value="<?= e($val('registration')) ?>">
                    <?= $fieldError('registration') ?>
                </div>

                <div>
                    <label class="label" for="roll">Roll No:</label>
                    <input type="text" id="roll" name="roll" data-field="roll"
                           placeholder="Roll No" value="<?= e($val('roll')) ?>">
                    <?= $fieldError('roll') ?>
                </div>

                <div>
                    <label class="label" for="captcha">Calculate: <?= e($captchaEquation) ?>=</label>
                    <input type="number" id="captcha" name="captcha" data-field="captcha"
                           placeholder="Calculate and type total" autocomplete="off">
                    <?= $fieldError('captcha') ?>
                </div>

                <div class="flex items-end justify-end gap-3">
                    <button type="button" id="result-clear" class="btn btn-light">Clear</button>
                    <button type="submit" id="result-submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <p class="mt-5 text-center">
        <a href="https://www.bkabd.com" class="btn btn-dark btn-sm">Go to Main Website</a>
    </p>
</div>
