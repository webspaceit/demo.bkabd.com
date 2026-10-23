<?php
/**
 * Printable student registration card — GET /api/registrationpdf/{registration}
 * Styled after the association's card format (attached reference): light card,
 * banner header (logo + bilingual association name), Reg No / Exam Roll tag
 * boxes on the left, exam heading + card pill centred, student photo top
 * right, and a two-column details grid over a light watermark.
 * Renders inside layouts.guest (same shell as /scholarship-result).
 *
 * @var array $student  row from students joined with institute/center/geo names
 */
$cardMode    = ($cardMode ?? 'registration') === 'admit' ? 'admit' : 'registration';
$twoPerPage  = !empty($twoPerPage);
$isAdmitCard = $cardMode === 'admit';
$cardStudents = $students ?? (isset($student) ? [$student] : []);
$cardCount = count($cardStudents);
$resolve = static function (string $file): ?string {
    if ($file === '') {
        return null;
    }
    $candidates = str_contains($file, '/')
        ? [$file]
        : ['images/students/' . $file, 'uploads/students/' . $file];
    foreach ($candidates as $rel) {
        $abs = BASE_PATH . '/public/' . $rel;
        if (is_file($abs)) {
            $mime = @mime_content_type($abs) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
        }
    }
    return null;
};

// The QR does not link back to this staff-only page; it carries the
// association's identity and contact details printed in the banner.
$brandPhone = brand('phone');
$brandEmail = brand('email');
$brandWebsite = brand('website');
$qrContent = implode("\n", [
    brand('authority_name'),
    brand('registration_label') . ': ' . brand('registration_no'),
    brand('address') . ($brandPhone !== '' ? ', Mobile: ' . $brandPhone : ''),
    ($brandEmail !== '' ? 'Email: ' . $brandEmail : '') . ($brandWebsite !== '' ? ', Web: ' . $brandWebsite : ''),
]);

/** A "Label : value" details row. */
$row = static function (string $label, $value): string {
    return '<div class="rc-row"><span class="rc-lbl">' . e($label) . '</span>'
        . '<span class="rc-sep">:</span>'
        . '<span class="rc-val">' . e((string) ($value ?? '')) . '</span></div>';
};

$resolveStudentPhoto = static function (array $student) use ($resolve): ?string {
    $photoSrc = $resolve((string) ($student['photo'] ?? ''));
    if ($photoSrc === null) {
        $placeholder = ((int) ($student['gender'] ?? 0) === 2) ? 'female.jpeg' : 'male.jpeg';
        $photoSrc = $resolve($placeholder);
    }
    return $photoSrc;
};

$className = static function (int $class): string {
    return match ($class) {
        6 => 'KG', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        default => (string) $class,
    };
};

$examRoutines = $examRoutines ?? [];
$groupRoutineRows = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $routine) {
        $date = trim((string) ($routine['date'] ?? ''));
        $time = trim((string) ($routine['time'] ?? ''));
        $key = strtolower(preg_replace('/\s+/u', ' ', $date)) . "\0" . strtolower(preg_replace('/\s+/u', ' ', $time));
        if (!isset($groups[$key])) {
            $groups[$key] = $routine;
            $groups[$key]['_subjects'] = [];
        }
        $subjects = preg_split('/\s*,\s*/u', trim((string) ($routine['subject'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($subjects as $subject) {
            $subjectKey = strtolower(trim($subject));
            if ($subjectKey !== '' && !isset($groups[$key]['_subject_keys'][$subjectKey])) {
                $groups[$key]['_subject_keys'][$subjectKey] = true;
                $groups[$key]['_subjects'][] = trim($subject);
            }
        }
    }
    foreach ($groups as &$group) {
        $group['subject'] = implode(', ', $group['_subjects']);
        unset($group['_subjects'], $group['_subject_keys']);
    }
    unset($group);
    return array_values($groups);
};
$examRoutineRows = static function (string|int $session, int $class) use ($examRoutines, $groupRoutineRows): array {
    $sessionRows = array_values(array_filter($examRoutines, static function (array $routine) use ($session): bool {
        return (string) ($routine['session'] ?? '') === (string) $session;
    }));
    $matchesClass = static function (array $rows) use ($class): array {
        return array_values(array_filter($rows, static function (array $routine) use ($class): bool {
            $routineClass = trim((string) ($routine['class'] ?? ''));
            return $routineClass !== '' && (int) $routineClass === $class;
        }));
    };
    $matchesAllClasses = static function (array $rows): array {
        return array_values(array_filter($rows, static function (array $routine): bool {
            return trim((string) ($routine['class'] ?? '')) === '';
        }));
    };
    $rows = $matchesClass($sessionRows);
    if ($rows === []) { $rows = $matchesAllClasses($sessionRows); }
    return $groupRoutineRows($rows);
};
$printDate = date('d-m-Y');
$copyrightYear = date('Y');
$title = $title ?? ($isAdmitCard ? 'Admit Card' : 'Registration Card');
$cardLabel = $isAdmitCard ? 'Admit Card' : 'Student Registration Card';
$qrContentEscaped = e($qrContent);
?>
<style>
    /* ---- Card format (landscape, like the association's card) ----
       Same sheet design as the scholarship result sheet (result_show.php):
       the user-supplied repeating pattern fills the sheet; the watermark logo
       is painted on the inner frame so neither hides the other. */
    .reg-card {
        max-width: 820px;
        margin: 0 auto;
        padding: .4125rem;
        background-image: url("<?= brand_asset('pattern_path', '/assets/img/result/pattern.png') ?>");
        background-repeat: repeat;
        background-position: 0 0;
        background-size: 424px 424px;
    }

    /* Watermark logo, centred and painted over the pattern (same as the
       result sheet's .rs-inner). */
    .rc-inner {
        background-image: url("<?= brand_asset('watermark_path', '/assets/img/result/for-registration.png') ?>");
        background-repeat: no-repeat;
        background-position: center center;
        background-size: 220px 220px;
    }

    .rc-brand-art { display: block; max-width: 100%; height: auto; margin-left: auto; margin-right: auto; }
    .rc-brand-art-header { margin-bottom: .35rem; }
    .rc-brand-art-footer { margin-top: .35rem; }

    /* ---- Banner header (transparent so the pattern + watermark show through) ---- */
    .rc-banner {
        position: relative;
        display: flex;
        align-items: center;
        gap: 1rem;
        /* Keep 8px under the text so the 6px double rule never collides with
           the contact line, and so print engines can snap the pair as one box. */
        padding: .8rem 1.2rem 8px;
        border-bottom: 0;
    }
    /* One 6px decoration (2px / 2px gap / 2px). Hairline `double` borders and
       two separately positioned .3mm rules snap independently per page, so
       some PDF pages look thick and others look double. */
    .rc-banner::after {
        content: "";
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 6px;
        background-image: linear-gradient(#065f46, #065f46), linear-gradient(#065f46, #065f46);
        background-repeat: no-repeat;
        background-size: 100% 2px;
        background-position: 0 0, 0 100%;
        pointer-events: none;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    /* Header logo — 70px, no frame. */
    .rc-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .rc-logo img { width: 70px; height: 70px; object-fit: contain; }
    .rc-banner h1 {
        margin: 0;
        text-align: center;
        font-size: 1.5rem;
        line-height: 1.15;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: .01em;
        white-space: nowrap;
    }
    .rc-banner .rc-gov {
        margin-top: .15rem;
        font-size: .8rem;
        line-height: 1.45;
        font-weight: 500;
        color: #000;
        text-align: center;
    }
    .reg-card.registration-card .rc-banner {
        gap: .55rem;
        padding: .3rem .7rem 8px;
        margin-bottom: .35rem;
    }
    .reg-card.registration-card .rc-banner h1 { font-size: 1.75rem; }
    .reg-card.registration-card .rc-reg { font-size: .95rem; }
    .reg-card.registration-card .rc-banner .rc-gov { font-size: .85rem; line-height: 1.35; }

    /* ---- Top row: Reg No tag | heading + pill | Exam Roll tag ---- */
    .rc-top {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.2rem .4rem;
    }
    .rc-tags { display: flex; flex-direction: column; gap: .55rem; min-width: 13rem; }
    .rc-tag {
        border: 2px solid #0f172a;
        border-radius: 9999px;
        background:transparent;
        padding: .3rem .9rem;
        font-size: .95rem;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
    }
    .rc-roll { margin-left: auto; align-self: center; min-width: 13rem; text-align: left; }
    /* Scholarship Exam heading sits on its own line above the tag row, so the
       Reg No tag, the Registration Card pill and the Exam Roll tag all share
       one horizontal line. */
    .rc-exam-head {
        margin: .5rem 0 .2rem;
        text-align: center;
        font-size: 1.45rem;
        font-weight: 800;
        color: #0f172a;
    }
    .rc-center { flex: 1; text-align: center; min-width: 0; }
    .rc-center .rc-pill {
        display: inline-block;
        border: 2px solid #0f172a;
        border-radius: 9999px;
        background: transparent;
        padding: .35rem 1.6rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .15);
    }
    /* Student photo — third block of the details row, spanning its rows.
       64x74px = the 80x93 size reduced by 20%. */
    .rc-photo {
        flex: 0 0 auto;
        align-self: center;
    }
    .rc-photo img {
        display: block;
        width: 64px;
        height: 74px;
        object-fit: cover;
        border: 2px solid #94a3b8;
        background: #fff;
        border: double;
    }

    /* ---- Details row: columns size to their content and spread across the
       full width (dynamic, like the wide Institute Address line) ---- */
    .rc-body {
        display: flex;
        align-items: stretch;
        justify-content: space-between;
        gap: 1.5rem;
        padding: .9rem 1.2rem 0;
        margin-top: .5rem;
        margin-bottom: .6rem;
    }
    .rc-body-admit {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 74px;
        gap: .6rem;
        width: 100%;
        box-sizing: border-box;
    }
    .rc-body-admit .rc-col { min-width: 0; }
    .rc-body-admit .rc-row {
        min-width: 0;
        grid-template-columns: minmax(0, 8.2rem) .75rem minmax(0, 1fr);
    }
    .rc-body-admit .rc-val { min-width: 0; }
    /* Keep the registration photo in its own fixed column. Long institute
       names and addresses must wrap inside the two detail columns. */
    .reg-card.registration-card .rc-body {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, .9fr) 74px;
        column-gap: 1rem;
        width: 100%;
        box-sizing: border-box;
    }
    .reg-card.registration-card .rc-col { min-width: 0; }
    .reg-card.registration-card .rc-col:first-child .rc-row { min-width: 0; grid-template-columns: minmax(0, 8.4rem) 1rem minmax(0, 1fr); }
    .reg-card.registration-card .rc-col:nth-child(2) .rc-row { min-width: 0; grid-template-columns: minmax(0, 7rem) 1rem minmax(0, 1fr); }
    .reg-card.registration-card .rc-val { min-width: 0; overflow-wrap: anywhere; }
    .rc-routine {
        width: calc(100% - 2rem);
        margin: .1rem 1rem .3rem;
    }
    .rc-routine-title { margin-bottom: .12rem; text-align: center; font-size: .95rem; font-weight: 700; color: #0f172a; }
    .rc-routine-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
        font-size: .82rem;
        line-height: 1.2;
        color: #111827;
    }
    .rc-routine-table-numbered th:first-child,
    .rc-routine-table-numbered td:first-child { width: 5%; }
    .rc-routine-table th,
    .rc-routine-table td { border: 1px solid #334155; padding: .15rem .3rem; text-align: center; overflow-wrap: anywhere; }
    .rc-routine-table th { font-weight: 700; background: #f1f5f9; }
    /* The two text columns use one identical standard line pitch — no
       per-column stretching, so both blocks read at the same rhythm. */
    .rc-col {
        flex: 0 0 auto;
        display: grid;
        row-gap: .3rem;
        align-content: start;
    }
    .rc-row {
        display: grid;
        grid-template-columns: 9.5rem 1rem 1fr;
        align-items: baseline;
        padding: .18rem 0;
        font-size: .95rem;
        line-height: 1.35;
    }

    /* Wide row: spans under both text columns so long values (Institute
       Address) stay on a single line. */
    .rc-body-wide { display: block; padding: 0 1.2rem .4rem; }
    .rc-body-wide .rc-row { grid-column: 1 / span 2; }
    .rc-body-wide .rc-val { white-space: nowrap; }
    .rc-lbl { font-weight: 700; color: #0f172a; }
    .rc-sep { color: #0f172a; text-align: center; }
    .rc-val { font-weight: 600; color: #111827; word-break: break-word; }
    /* Registration No. and Roll No. share a single line in the detail table. */
    .rc-row-pair {
        display: flex;
        align-items: baseline;
        flex-wrap: nowrap;
        white-space: nowrap;
    }
    .rc-row-pair .rc-sep { padding: 0 .3rem; }
    .rc-row-pair .rc-lbl-gap { margin-left: 1rem; }

    /* ---- QR (banner right, on the blank space) ---- */
/* The QR payload is the whole contact block (~236 bytes -> 61 modules), sized
        to 70px for the header. */
    .rc-qr { text-align: center; margin-left: auto; align-self: center; flex-shrink: 0; }
    .rc-qr canvas, .rc-qr img, .rc-qr table { width: 70px !important; height: 70px !important; }

    .rc-reg {
        margin: 0;
        padding: 0;
        text-align: center;
        font-size: .9rem;
        font-weight: 700;
        color: #0f172a;
    }

    .rc-welcome {
        text-align: center;
        font-size: .82rem;
        line-height: 1.4;
        color: #000;
        padding: .4rem 1.2rem 0;
    }

    /* ---- Signature block (natural image size, same signatories as the result sheet) ---- */
    .sig-tbl td { vertical-align: bottom; text-align: center; padding: 0 .75rem; }
    .sig-img img { max-width: 90px; height: auto; margin: 0 auto; display: block; }
    .sig-line { border-top: 1px solid #000; margin-top: .25rem; padding-top: .25rem; width: 82%; margin-left: auto; margin-right: auto; }
    .sig-name { font-weight: 600; font-size: 1.05em; line-height: 1.2; color: #000; }
    .sig-role { color: #000; font-size: 95%; line-height: 1.2; }

    /* ---- Footer ---- */
    .rc-note {
        clear: both;
        position: relative;
        text-align: center;
        font-size: .92rem;
        color:#000;
        padding: .6rem 1.2rem 0;
    }
    .rc-foot {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        font-size: .78rem;
        color: #000;
        padding: .5rem 1.2rem .8rem;
    }

    /* Portrait A4 sheet, 0.15in (~3.8mm) margins on all sides. */
    @page { size: A4 portrait; margin: 0.15in; }

    /* Root font scale — rem-based sizes use the print scale on screen too,
       so the sheet matches the PDF/print output exactly. */
    html { font-size: 12.5px; }

    /* A4 paper canvas: the card renders on a real A4 portrait sheet with the
       same 0.15in page margin the PDF gets. */
    .rc-page {
        width: 210mm;
        min-height: 297mm;
        margin: 0 auto .75rem;
        background: #fff;
        padding: 0.15in;
        box-sizing: border-box;
        box-shadow: 0 2px 10px rgba(15, 23, 42, .35), 0 0 1px rgba(15, 23, 42, .4);
    }

    .reg-card {
        max-width: none;
        margin: 0;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .reg-card, .reg-card * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

/* Compact print scale (applies everywhere so screens match the PDF).
        Header text scaled so the contact lines fill the space up to the QR;
        the QR keeps the 70px size set by the .rc-qr rule (its payload is the
        whole contact block, so it needs the room to stay scannable). */
    .rc-banner { padding: .5rem 1rem 8px; margin-bottom: 1rem; }
    .rc-logo img { width: 70px; height: 70px; }
    /* The QR now carries the whole contact block, so it is sized to 70px
        (scannable on paper). The heading and the contact line are scaled down a
        notch so the row (logo + text + QR) still fits inside the 210mm sheet. */
    .rc-banner h1 { font-size: 1.8rem; }
    .rc-reg { font-size: .85rem; }
    .rc-banner .rc-gov { font-size: .8rem; line-height: 1.6; white-space: nowrap; }
    .rc-top { padding: .45rem 1rem .1rem; }
    .rc-tag { padding: .15rem .75rem; font-size: .85rem; }
    .rc-exam-head { font-size: 1.5rem; margin-bottom: 1rem; }
    .rc-center .rc-pill { padding: .2rem 1.2rem; font-size: 1.5rem; background: #000; color: #fff; }
    .rc-photo img { width: 74px; height: 84px; }
    .rc-body { padding: .35rem 1rem 0; column-gap: 1.8rem; row-gap: 0; }
    .rc-row { font-size: .8rem; padding: .06rem 0; grid-template-columns: 8.2rem 1rem 1fr; }
    .rc-qr canvas, .rc-qr img, .rc-qr table { width: 70px !important; height: 70px !important; }
    .sig-tbl { margin-top: .2rem !important; }
    .sig-name { font-size: .95em; }
    .rc-welcome { font-size: .7rem; padding-top: .25rem; }
    .rc-note { font-size: .8rem; padding-top: .25rem; }
    .rc-foot { padding: .25rem 1rem .45rem; font-size: .68rem; }

    <?php if ($isAdmitCard): ?>
    /* Match the center-wide admit-card print typography. */
    .reg-card.admit-card .rc-banner { gap: .55rem; padding: .1rem .7rem 2px; margin-bottom: .35rem; }
    .reg-card.admit-card .rc-banner { border-bottom: 1px solid #065f46; }
    .reg-card.admit-card .rc-banner::after { display: none; }
    .reg-card.admit-card .rc-qr { display: flex; align-items: center; justify-content: center; align-self: center; height: 70px; }
    .rc-banner h1 { font-size: 1.75rem; }
    .rc-reg { font-size: .95rem; }
    .rc-banner .rc-gov { font-size: .85rem; line-height: 1.35; }
    .rc-tag { padding: .1rem .55rem; font-size: .8rem; }
    .rc-top { margin-bottom: .5rem; }
    .rc-row { grid-template-columns: 9.5rem 1rem 1fr; font-size: .95rem; line-height: 1.2; padding: .03rem 0; }
    /* Match the center-wide card's vertical spacing around details and rules. */
    .rc-body { margin-top: 0 !important; margin-bottom: .25rem !important; }
    .rc-rules { margin: .25rem 0 .25rem 1rem !important; font-size: 1rem !important; line-height: 1.22 !important; }
    .rc-rules p { margin: 0 0 .15rem; }
    .rc-rules > p:first-child { font-size: .95rem !important; line-height: 1.2; font-weight: 700; color: #0f172a; }
    .rc-rules p:last-child { margin-bottom: 0; white-space: nowrap; font-size: 1rem; }
    .sig-tbl { margin-top: .25rem !important; }
    .sig-name { font-size: .82rem; }
    .sig-role { font-size: .68rem; }
    .rc-note { font-size: .75rem; line-height: 1.15; }
    .reg-card.admit-card .rc-note { font-size: 10px; }
    .rc-foot { font-size: .75rem; }
    <?php endif; ?>

    /* ---- Phones / small tablets ----
       The sheet is 210mm wide so print and desktop match the reference, but
       that forces a horizontal scrollbar on a phone. Below 900px the layout
       reflows to the viewport width instead: the banner heading wraps, the
       Reg No / Exam Roll tags stack, the two detail columns become one, and
       the four signatures go 2 x 2. (Paper is unaffected — the @media print
       block below carries the 210mm sheet through to the printer.) */
    @media screen and (max-width: 900px) {
        .rc-page {
            width: 100%;
            min-height: 0;
            padding: .5rem;
            box-shadow: 0 1px 4px rgba(15, 23, 42, .25);
        }

        .rc-banner { flex-wrap: nowrap; gap: .5rem; padding: .5rem .5rem 8px; margin-bottom: .6rem; }
        .rc-logo img { width: 70px; height: 70px; }
        .rc-banner h1 { font-size: 1.05rem; line-height: 1.2; white-space: normal; }
        .rc-banner .rc-gov { font-size: .68rem; line-height: 1.5; white-space: normal; overflow-wrap: anywhere; }
        .rc-reg { font-size: .72rem; }
        <?php if ($isAdmitCard): ?>
        .reg-card.admit-card .rc-banner { gap: .35rem; padding: .35rem .4rem 8px; align-items: flex-start; }
        .reg-card.admit-card .rc-banner h1 { font-size: 1rem; line-height: 1.2; white-space: normal; overflow-wrap: anywhere; }
        .reg-card.admit-card .rc-banner .rc-gov { font-size: .58rem; line-height: 1.35; white-space: normal; overflow-wrap: anywhere; }
        .reg-card.admit-card .rc-reg { font-size: .65rem; }
        .reg-card.admit-card .rc-qr { height: auto; }
        <?php endif; ?>
        .rc-qr canvas, .rc-qr img, .rc-qr table { width: 70px !important; height: 70px !important; }

        .rc-welcome { font-size: .7rem; padding: .4rem .5rem 0; }
        .rc-exam-head { font-size: 1.05rem; margin: .5rem 0 .2rem; }

        .rc-top { flex-direction: column; align-items: stretch; gap: .5rem; padding: .5rem .5rem .2rem; }
        .rc-tags, .rc-roll { min-width: 0; }
        .rc-roll { margin-left: 0; }
        .rc-tag { white-space: normal; text-align: center; }
        .rc-center { width: 100%; }
        .rc-center .rc-pill { font-size: 1.5rem; padding: .3rem 1rem; }

        .rc-body { flex-direction: column; gap: .75rem; padding: .5rem .5rem 0; margin-top: .25rem; }
        .rc-col { width: 100%; }
        .rc-row { grid-template-columns: 8rem .75rem 1fr; font-size: .82rem; }
        .reg-card.registration-card .rc-body { display: flex; flex-direction: column; align-items: stretch; gap: .5rem; }
        .reg-card.registration-card .rc-row { grid-template-columns: 8rem .75rem minmax(0, 1fr); }
        <?php if ($isAdmitCard): ?>
        .reg-card.admit-card .rc-body-admit { display: flex; flex-direction: column; gap: .5rem; }
        .reg-card.admit-card .rc-body-admit .rc-col { width: 100%; }
        .reg-card.admit-card .rc-body-admit .rc-row { grid-template-columns: minmax(0, 7rem) .75rem minmax(0, 1fr); font-size: .78rem; }
        .reg-card.admit-card .rc-rules p:last-child { white-space: normal; overflow-wrap: anywhere; }
        <?php endif; ?>
        .rc-body-wide { padding: 0 .5rem .4rem; }
        .rc-body-wide .rc-val { white-space: normal; }
        .rc-photo { align-self: center; }
        .rc-photo img { width: 78px; height: 90px; }

        /* Four signatures across would be cramped: 2 x 2. The tbody has to
           follow the table into block flow, otherwise the browser wraps the
           grid in an anonymous inline-table that shrink-wraps (the row came
           out 234px inside a 353px sheet). Bottom-aligning each cell's
           content reproduces the table's `vertical-align: bottom`, so the
           four signature lines share a baseline as they do on desktop. */
        .sig-tbl, .sig-tbl tbody { display: block; }
        .sig-tbl tr { display: grid; grid-template-columns: 1fr 1fr; column-gap: .5rem; row-gap: .6rem; }
        .sig-tbl td { display: flex; flex-direction: column; justify-content: flex-end; padding: 0 .25rem; }

        .rc-note { font-size: .72rem; padding: .5rem .5rem 0; }
        .rc-foot { flex-direction: column; align-items: center; gap: .15rem; text-align: center; font-size: .65rem; padding: .25rem .5rem .45rem; }
    }

    @media print {
        html, body { background: #fff !important; }
        body > header, body > footer, #toast-stack { display: none !important; }
        .no-print { display: none !important; }
        .rc-pages.multiple > .py-6:not(:last-child) .rc-page { break-after: page; page-break-after: always; }
        body.print-one-admit-card .rc-page:not(.print-target) { display: none !important; }
        body.print-one-admit-card .rc-page.print-target { break-after: auto !important; page-break-after: auto !important; }
        /* The thank-you line is screen-only; with it hidden the Scholarship
           Exam heading below flows up to where the sentence was. */
        .rc-welcome { display: none !important; }

        /* Admit-card banner uses a print-safe 1px rule below its header. */
        .reg-card.admit-card .rc-banner { border-bottom: 1px solid #065f46; }
        .reg-card.admit-card .rc-banner::after { display: none; }

        /* The sheet becomes the paper itself — no shadow, no extra padding
           (the @page margin already provides the 0.15in). */
        .rc-page { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    }

    /* ---- Two-per-page mode removed — handled by admitcards.center_print ---- */
    <?php if ($twoPerPage): ?><?php endif; ?>
</style>

<div class="rc-pages<?= $cardCount > 1 ? ' multiple' : '' ?>">
<?php foreach ($cardStudents as $student): ?>
<?php $photoSrc = $resolveStudentPhoto((array) $student); ?>
<div class="py-6 print:py-0">
    <?php if ($isAdmitCard): ?>
    <div class="mb-3 flex justify-center gap-2 print:hidden">
        <a href="/admitcards" onclick="if (history.length > 1 && document.referrer && new URL(document.referrer).origin === location.origin) { history.back(); return false; }" class="btn bg-slate-500 text-white hover:bg-slate-600">Back</a>
        <button type="button" class="btn btn-primary" data-print-card
                data-session="<?= e($student['session'] ?? '') ?>"
                data-registration="<?= e($student['registration'] ?? '') ?>">Print / Save this card as PDF</button>
    </div>
    <?php endif; ?>
    <div class="rc-page">
    <div class="reg-card <?= $isAdmitCard ? 'admit-card' : 'registration-card' ?> border-2 border-black bg-white">
        <div class="rc-inner border-2 border-black">
        <?php if (brand('header_image_path') !== ''): ?>
            <img class="rc-brand-art rc-brand-art-header" src="<?= e(brand_asset('header_image_path', '')) ?>" alt="">
        <?php endif; ?>
        <div class="rc-banner">
            <span class="rc-logo"><img src="<?= e(brand_asset('logo_path', '/assets/img/result/logo.png')) ?>" alt="<?= e(brand('short_name')) ?>" onerror="this.style.display='none'"></span>
            <div>
                <h1><?= e(brand('authority_name')) ?></h1>
                <p class="rc-reg"><?= e(brand('registration_label')) ?>: <?= e(brand('registration_no')) ?></p>
                <p class="rc-gov">
                    <?= e(brand('address')) ?><?= $brandPhone !== '' ? ', Mobile: ' . e($brandPhone) : '' ?><br>
                    <?= $brandEmail !== '' ? 'Email: ' . e($brandEmail) : '' ?><?= $brandWebsite !== '' ? ', Web: ' . e($brandWebsite) : '' ?>
                </p>
            </div>
            <div class="rc-qr">
                <div class="reg-qr inline-block" data-qr-content="<?= $qrContentEscaped ?>"></div>
            </div>
        </div>

        <?php if (!$isAdmitCard): ?>
        <p class="rc-welcome">Thank you. Your Registration has been completed successfully. You must save this Card. It will be required to participate in the next Scholarship Examination.</p>
        <?php endif; ?>

        <p class="rc-exam-head"><?= e(brand('exam_name')) ?> - <?= e($student['session'] ?? '') ?></p>

        <div class="rc-top">
            <div class="rc-tags">
                <div class="rc-tag">Reg No.&nbsp;: <?= e($student['registration'] ?? '') ?></div>
            </div>
            <div class="rc-center">
                <div class="rc-pill"><?= e($cardLabel) ?></div>
            </div>
            <div class="rc-tag rc-roll">Exam Roll&nbsp;: <?= e($student['roll'] ?? '') ?></div>
        </div>

        <div class="rc-body<?= $isAdmitCard ? ' rc-body-admit' : '' ?>">
            <?php if ($isAdmitCard): ?>
            <div class="rc-col">
                <?= $row('Name', $student['name'] ?? '') ?>
                <?= $row("Father's Name", $student['father_name'] ?? '') ?>
                <?= $row("Mother's Name", $student['mother_name'] ?? '') ?>
                <?= $row('Date of Birth', !empty($student['dob']) ? DateFormat($student['dob'], 'd-m-Y') : '') ?>
                <div class="rc-row rc-row-pair"><span class="rc-lbl">Registration No.</span><span class="rc-sep">:</span><span class="rc-val"><?= e($student['registration'] ?? '') ?></span><span class="rc-lbl rc-lbl-gap">Roll No.</span><span class="rc-sep">:</span><span class="rc-val"><?= e($student['roll'] ?? '') ?></span></div>
            </div>
            <div class="rc-col">
                <?= $row('Exam Class', $className((int) ($student['class'] ?? 0))) ?>
                <?= $row('Exam Center', $student['center_name'] ?? '') ?>
                <?= $row('Institute Name', $student['institute_name'] ?? '') ?>
                <?php if (!empty($student['seat_assignment']['room_label'])): ?>
                    <?= $row('Seat Location', $student['seat_assignment']['room_label']) ?>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="rc-col">
                <?= $row('Name', $student['name'] ?? '') ?>
                <?= $row("Father's Name", $student['father_name'] ?? '') ?>
                <?= $row("Mother's Name", $student['mother_name'] ?? '') ?>
                <?= $row('Exam Subject', 'Bangla, English, Mathematics') ?>
                <?= $row('Exam Center', $student['center_name'] ?? '') ?>
                <?= $row('Institute Name', $student['institute_name'] ?? '') ?>            
                <?= $row('Institute Address', $student['institute_address'] ?? '') ?>
            </div>
            <div class="rc-col">
                <?= $row('Gender', GenderName((int) ($student['gender'] ?? 0))) ?>
                <?= $row('Class', ClassName((int) ($student['class'] ?? 0))) ?>
                <?= $row('Mobile', $student['phone'] ?? '') ?>
                <?= $row('Date of Birth', !empty($student['dob']) ? DateFormat($student['dob'], 'd-m-Y') : '') ?>
                <?= $row('Division', $student['division_name'] ?? '') ?>
                <?= $row('District', $student['district_name'] ?? '') ?>
                <?= $row('Thana/Upazila', $student['upazila_name'] ?? '') ?>
            </div>
            <?php endif; ?>
            <div class="rc-photo">
                <?php if ($photoSrc !== null): ?>
                    <img src="<?= $photoSrc ?>" alt="Student photo">
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdmitCard): ?>
        <div class="rc-routine">
            <div class="rc-routine-title">Exam Schedule</div>
            <?php $studentRoutineRows = $examRoutineRows($student['session'] ?? '', (int) ($student['class'] ?? 0)); ?>
            <?php $showRoutineSl = count($studentRoutineRows) > 1; ?>
            <table class="rc-routine-table<?= $showRoutineSl ? ' rc-routine-table-numbered' : '' ?>">
                <thead><tr><?php if ($showRoutineSl): ?><th>Sl.</th><?php endif; ?><th>Subject</th><th>Exam Date</th><th>Day</th><th>Time</th></tr></thead>
                <tbody>
                <?php if ($studentRoutineRows === []): ?>
                    <tr><td colspan="<?= $showRoutineSl ? '5' : '4' ?>">Not scheduled</td></tr>
                <?php else: foreach ($studentRoutineRows as $routineIndex => $routineItem): ?>
                    <tr>
                        <?php if ($showRoutineSl): ?><td><?= $routineIndex + 1 ?></td><?php endif; ?>
                        <td><?= e($routineItem['subject'] ?? '') ?></td>
                        <td><?= e($routineItem['date'] ?? '') ?></td>
                        <td><?= e($routineItem['day'] ?? '') ?></td>
                        <td><?= e($routineItem['time'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- <div class="rc-body rc-body-wide">
            <?= $row('Institute Address', $student['institute_address'] ?? '') ?>
        </div> -->

        <?php if ($isAdmitCard): ?>
        <div class="rc-rules ml-4 my-3 text-[11px] sm:text-xs leading-relaxed">
            <p class="font-bold mb-1 text-slate-800">বিশেষ নির্দেশাবলিঃ</p>
            <p>১. প্রবেশপত্র (অ্যাডমিট কার্ড) ছাড়া কোনো পরীক্ষার্থীকে পরীক্ষা কক্ষে প্রবেশ করতে দেওয়া হবে না।</p>
            <p>২. প্রবেশপত্র ছাড়া কোনো পরীক্ষার্থী লিখিত পরীক্ষার কাগজ বা বই সঙ্গে রাখতে পারবে না।</p>
            <p>৩. পরীক্ষা শুরুর দুই ঘণ্টার আগে কোনো পরীক্ষার্থী পরীক্ষা কক্ষ থেকে বাইরে যেতে পারবেন না।</p>
            <p>৪. প্রত্যেক পরীক্ষার্থীকে অ্যাসোসিয়েশনের সকল নিয়ম মেনে চলতে হবে। অন্যথায়, তাকে পরীক্ষায় অংশগ্রহণের অনুমতি দেওয়া হবে না।</p>
        </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
        <table class="sig-tbl mt-4 w-full text-[10px] sm:text-xs">
            <tr>
                <?php foreach ([
                    ['chairman',           brand('chairman_name'), 'Chairman'],
                    ['general_secretary',  brand('general_secretary_name'), brand('general_secretary_title')],
                    ['controller',         brand('controller_name'), brand('controller_title')],
                    ['education_secretary', brand('education_secretary_name'), brand('education_secretary_title')],
                ] as [$signKey, $signName, $signRole]): ?>
                <td>
                    <div class="sig-img"><img src="<?= e(result_signature($signKey)) ?>" alt=""></div>
                    <div class="sig-line">
                        <div class="sig-name"><?= e($signName) ?></div>
                        <div class="sig-role"><?= e($signRole) ?></div>
                    </div>
                </td>
                <?php endforeach; ?>
            </tr>
        </table>
        </div>

        <p class="rc-note">
            <?php if ($isAdmitCard): ?>
            <b>Note:</b> This admit card is valid upto this session. Examinee must bring this card in the examination hall.
            <?php else: ?>
            <b>Note:</b> This registration card is valid upto this session. Examinee must bring this card in the examination hall.
            <?php endif; ?><br>
            <span class="text-xs">&copy; <?= e($copyrightYear) ?> <?= e(brand('authority_name')) ?>. <?= e(brand('copyright_text')) ?> <?= e(brand('developer_credit')) ?></span>
        </p>

        <?php if (!$isAdmitCard): ?>
            <?php if (brand('footer_image_path') !== ''): ?><img class="rc-brand-art rc-brand-art-footer" src="<?= e(brand_asset('footer_image_path', '')) ?>" alt=""><?php endif; ?>
            <div class="rc-foot">
            <div>Date of Registration &amp; Time: <?= e(DateFormat($student['created_at'] ?? '', 'd-m-Y, h:i A')) ?></div>
            <div>Print Date: <?= e($printDate) ?></div>
        </div>
        <?php endif; ?>
        </div>
    </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php if (!$isAdmitCard): ?>
<div class="mt-4 flex justify-center gap-3 print:hidden">
    <a href="/admitcards" onclick="if (history.length > 1 && document.referrer && new URL(document.referrer).origin === location.origin) { history.back(); return false; }" class="btn bg-slate-500 text-white hover:bg-slate-600">Back</a>
    <button type="button" onclick="window.print();" class="btn bg-emerald-500 text-white hover:bg-emerald-600">Print</button>
</div>
<?php endif; ?>

<script src="/assets/js/vendor/qrcode.min.js"></script>
<script>
(function () {
    if (typeof QRCode === 'undefined') { return; }
    Array.prototype.forEach.call(document.querySelectorAll('.reg-qr'), function (box) {
        new QRCode(box, {
            text: box.getAttribute('data-qr-content'),
            width: 384,
            height: 384,
            correctLevel: QRCode.CorrectLevel.M
        });
        // qrcodejs emits a canvas and/or an <img>; size every render target.
        Array.prototype.forEach.call(box.querySelectorAll('canvas, img, table'), function (el) {
            el.style.width = '70px';
            el.style.height = '70px';
        });
    });
})();

(function () {
    var buttons = document.querySelectorAll('[data-print-card]');
    var cleanSegment = function (value) {
        return String(value || '').trim().replace(/[^A-Za-z0-9_-]+/g, '_').replace(/^_+|_+$/g, '') || 'unknown';
    };

    Array.prototype.forEach.call(buttons, function (button) {
        button.addEventListener('click', function () {
            var wrapper = button.closest('.py-6');
            var page = wrapper && wrapper.querySelector('.rc-page');
            if (!page) { return; }

            var originalTitle = document.title;
            document.querySelectorAll('.rc-page.print-target').forEach(function (target) {
                target.classList.remove('print-target');
            });
            page.classList.add('print-target');
            document.body.classList.add('print-one-admit-card');
            document.title = 'admin_card_' + cleanSegment(button.dataset.session)
                + '_' + cleanSegment(button.dataset.registration);

            var restore = function () {
                document.body.classList.remove('print-one-admit-card');
                page.classList.remove('print-target');
                document.title = originalTitle;
                window.removeEventListener('afterprint', restore);
            };
            window.addEventListener('afterprint', restore);
            window.print();
        });
    });
})();
</script>
