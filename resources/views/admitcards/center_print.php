<?php
/**
 * Center-wide admit card print — 2 cards per A4 page.
 * Rendered inside layouts.guest.
 *
 * @var array  $students      rows with all joined fields
 * @var string $centerName
 * @var string $activeSession
 */

$classLabel = static function (int $c): string {
    return match ($c) { 6=>'KG', 1=>'One', 2=>'Two', 3=>'Three', 4=>'Four', 5=>'Five', default=>(string)$c };
};

// Resolve photo to base64 data URI (avoids broken images when printing).
$photoData = static function (string $photo): ?string {
    if ($photo === '') { return null; }
    $candidates = str_contains($photo, '/')
        ? [$photo]
        : ['images/students/' . $photo, 'uploads/students/' . $photo];
    foreach ($candidates as $rel) {
        $abs = BASE_PATH . '/public/' . $rel;
        if (is_file($abs)) {
            $mime = @mime_content_type($abs) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
        }
    }
    return null;
};

$logoSrc = BASE_PATH . '/public/assets/img/result/logo.png';
$logoB64  = is_file($logoSrc)
    ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoSrc))
    : '';

$total = count($students);
?>
<style>
/* ---- Reset ---- */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; background: #e5e7eb; }

/* ---- Toolbar (hidden on print) ---- */
.toolbar {
    background: #064e3b; color: #fff;
    padding: .6rem 1.2rem;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    position: sticky; top: 0; z-index: 10;
}
.toolbar h2 { font-size: .95rem; font-weight: 600; }
.toolbar span { font-size: .8rem; opacity: .8; }
.toolbar button {
    background: #fff; color: #064e3b;
    border: none; border-radius: 6px;
    padding: .4rem 1.1rem; font-size: .85rem; font-weight: 700;
    cursor: pointer; white-space: nowrap;
}
.toolbar button:hover { background: #d1fae5; }

/* ---- A4 page wrapper — two cards stacked ---- */
.page {
    width: 210mm;
    min-height: 297mm;
    margin: 1rem auto;
    background: #fff;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 12px rgba(0,0,0,.25);
    overflow: hidden;
}

/* ---- Individual card — exactly half of A4 ---- */
.admit-card {
    width: 100%;
    height: 148.5mm;          /* A4 height ÷ 2 */
    box-sizing: border-box;
    padding: 4mm 6mm;
    border-bottom: 1.5px dashed #94a3b8;
    display: flex;
    flex-direction: column;
    gap: 2mm;
    overflow: hidden;
    position: relative;
}
.admit-card:last-child { border-bottom: none; }

/* ---- Header ---- */
.ac-header {
    display: flex;
    align-items: center;
    gap: 3mm;
    border-bottom: 2px double #065f46;
    padding-bottom: 2mm;
}
.ac-logo { width: 14mm; height: 14mm; object-fit: contain; flex-shrink: 0; }
.ac-org { flex: 1; text-align: center; }
.ac-org h1 { font-size: 9.5pt; font-weight: 800; color: #0f172a; line-height: 1.2; }
.ac-org p  { font-size: 6.5pt; color: #334155; line-height: 1.4; margin-top: 1mm; }

/* ---- Card title pill ---- */
.ac-title-row {
    display: flex; align-items: center; justify-content: center; gap: 3mm;
}
.ac-pill {
    background: #0f172a; color: #fff;
    border-radius: 999px;
    padding: 1mm 6mm;
    font-size: 9pt; font-weight: 800;
    letter-spacing: .03em;
}
.ac-session { font-size: 8pt; font-weight: 600; color: #0f172a; }

/* ---- Body: details table + photo ---- */
.ac-body {
    display: flex;
    gap: 3mm;
    flex: 1;
    overflow: hidden;
}
.ac-details { flex: 1; }
.ac-table { width: 100%; border-collapse: collapse; }
.ac-table tr td {
    font-size: 7.5pt;
    padding: 0.9mm 1.5mm;
    border: 0.5px solid #cbd5e1;
    vertical-align: middle;
    line-height: 1.3;
}
.ac-table tr td:first-child {
    font-weight: 700;
    width: 36%;
    background: #f8fafc;
    white-space: nowrap;
}
.ac-photo-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5mm;
    flex-shrink: 0;
}
.ac-photo {
    width: 20mm; height: 24mm;
    object-fit: cover;
    border: 1.5px double #94a3b8;
    background: #f1f5f9;
    display: block;
}
.ac-photo-placeholder {
    width: 20mm; height: 24mm;
    border: 1.5px double #cbd5e1;
    background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
}
.ac-photo-placeholder svg { width: 10mm; height: 10mm; color: #94a3b8; }
.ac-roll-tag {
    border: 1px solid #0f172a;
    border-radius: 999px;
    padding: 0.5mm 2.5mm;
    font-size: 6.5pt; font-weight: 700;
    text-align: center;
    white-space: nowrap;
}

/* ---- Rules ---- */
.ac-rules {
    font-size: 6pt;
    line-height: 1.5;
    color: #1e293b;
    border-top: 0.5px solid #e2e8f0;
    padding-top: 1.5mm;
}
.ac-rules b { font-size: 6.5pt; }

/* ---- Signatures ---- */
.ac-sigs {
    display: flex;
    justify-content: space-between;
    border-top: 0.5px solid #e2e8f0;
    padding-top: 1.5mm;
    margin-top: auto;
}
.ac-sig {
    text-align: center;
    font-size: 6pt;
    line-height: 1.3;
}
.ac-sig-line {
    border-top: 0.5px solid #000;
    width: 28mm;
    margin: 0 auto .5mm;
}

/* ---- Print rules ---- */
@page { size: A4 portrait; margin: 0; }
@media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .page {
        width: 210mm;
        min-height: 297mm;
        margin: 0;
        box-shadow: none;
        page-break-after: always;
    }
    .page:last-child { page-break-after: auto; }
    .admit-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<div class="toolbar">
    <div>
        <h2>Admit Cards — <?= e($centerName) ?> &nbsp;|&nbsp; Session <?= e($activeSession) ?></h2>
        <span><?= $total ?> student<?= $total !== 1 ? 's' : '' ?> &nbsp;·&nbsp; <?= ceil($total / 2) ?> page<?= ceil($total / 2) !== 1 ? 's' : '' ?></span>
    </div>
    <button type="button" onclick="window.print()">&#128424; Print / Save as PDF</button>
</div>

<?php
// Chunk students into pairs — 2 per A4 page
$pairs = array_chunk($students, 2);
foreach ($pairs as $pair):
?>
<div class="page">
    <?php foreach ($pair as $s):
        $photo    = $photoData((string) ($s['photo'] ?? ''));
        $cls      = $classLabel((int) ($s['class'] ?? 0));
        $reg      = $s['registration'] ?? '';
        $roll     = $s['roll'] ?? '';
        $session  = $s['session'] ?? $activeSession;
    ?>
    <div class="admit-card">

        <!-- Header -->
        <div class="ac-header">
            <?php if ($logoB64): ?>
                <img class="ac-logo" src="<?= $logoB64 ?>" alt="Logo">
            <?php endif; ?>
            <div class="ac-org">
                <h1>BANGLADESH KINDERGARTEN ASSOCIATION</h1>
                <p>Govt. Reg. No.: S-1028/98 &nbsp;|&nbsp; 59/3, Brammonchiron, Jatrabari, Dhaka-1203 &nbsp;|&nbsp; +88-02-224458009</p>
            </div>
        </div>

        <!-- Title row -->
        <div class="ac-title-row">
            <span class="ac-pill">Admit Card</span>
            <span class="ac-session">Scholarship Examination — <?= e($session) ?></span>
        </div>

        <!-- Body: details + photo -->
        <div class="ac-body">
            <div class="ac-details">
                <table class="ac-table">
                    <tr><td>Student's Name</td><td><?= e($s['name'] ?? '') ?></td></tr>
                    <tr><td>Father's Name</td><td><?= e($s['father_name'] ?? '') ?></td></tr>
                    <tr><td>Mother's Name</td><td><?= e($s['mother_name'] ?? '') ?></td></tr>
                    <tr><td>Date of Birth</td><td><?= e($s['dob'] ?? '') ?></td></tr>
                    <tr><td>Registration No.</td><td><?= e($reg) ?></td></tr>
                    <tr><td>Roll No.</td><td><?= e($roll) ?></td></tr>
                    <tr><td>Exam Class</td><td><?= e($cls) ?></td></tr>
                    <tr><td>Exam Date &amp; Time</td><td>As per routine</td></tr>
                    <tr><td>Exam Center</td><td><?= e($s['center_name'] ?? '') ?></td></tr>
                    <tr><td>Exam Venue</td><td><?= e($s['institute_name'] ?? '') ?></td></tr>
                </table>
            </div>

            <div class="ac-photo-wrap">
                <?php if ($photo): ?>
                    <img class="ac-photo" src="<?= $photo ?>" alt="Photo">
                <?php else: ?>
                    <div class="ac-photo-placeholder">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
                    </div>
                <?php endif; ?>
                <div class="ac-roll-tag">Roll: <?= e($roll) ?></div>
                <div class="ac-roll-tag">Reg: <?= e(substr($reg, -6)) ?></div>
            </div>
        </div>

        <!-- Rules -->
        <div class="ac-rules">
            <b>নিয়মাবলী:</b>
            ১। পরীক্ষা শুরুর কম পক্ষে ১৫ মিনিট আগে পরীক্ষা হলে উপস্থিত থাকতে হবে।
            ২। পরীক্ষা হলে কোনো ধরনের কাগজপত্র বা বই নিয়ে প্রবেশ করা যাবে না।
            ৩। পরীক্ষা প্রবেশ পত্র ছাড়া কোনো শিক্ষার্থী পরীক্ষায় অংশগ্রহণ করতে পারবে না।
            ৪। পরীক্ষা হলে কোনো অনৈতিক বা অনাকাঙ্ক্ষিত ঘটনা ঘটানো যাবে না।
            ৫। পরীক্ষা সময় মোবাইল ফোন ব্যবহার করা যাবে না।
        </div>

        <!-- Signatures -->
        <div class="ac-sigs">
            <div class="ac-sig"><div class="ac-sig-line"></div><span>Exam Controller</span></div>
            <div class="ac-sig"><div class="ac-sig-line"></div><span>General Secretary</span></div>
            <div class="ac-sig"><div class="ac-sig-line"></div><span>Chairman</span></div>
            <div class="ac-sig"><div class="ac-sig-line"></div><span>Director</span></div>
        </div>

    </div><!-- /.admit-card -->
    <?php endforeach; ?>
</div><!-- /.page -->
<?php endforeach; ?>
