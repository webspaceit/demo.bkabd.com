<?php
/**
 * Rendering regression test for the admit-card print view.
 * Usage: php tools/test_admit_card_view.php
 */
require __DIR__ . '/../bootstrap/app.php';

use App\Core\View;

$student = [
    'session' => '2026',
    'registration' => 'REG-001',
    'roll' => '15',
    'name' => '<Student One>',
    'father_name' => 'Parent One',
    'mother_name' => 'Parent Two',
    'dob' => '2016-04-05',
    'class' => 3,
    'gender' => 1,
    'photo' => '',
    'center_name' => 'Sample Center',
    'institute_name' => 'Sample Institute',
    'institute_address' => 'Sample Address',
    'division_name' => 'Dhaka',
    'district_name' => 'Dhaka',
    'upazila_name' => 'Dhanmondi',
    'phone' => '01700000000',
    'created_at' => '2026-01-01 10:00:00',
];
$secondStudent = $student;
$secondStudent['registration'] = 'REG-002';
$secondStudent['roll'] = '16';
$secondStudent['name'] = 'Student Two';

$html = View::render('students.registration_card', [
    'title' => 'Admit Cards',
    'students' => [$student, $secondStudent],
    'cardMode' => 'admit',
], 'layouts.guest');

$registrationHtml = View::render('students.registration_card', [
    'title' => 'Registration Card',
    'student' => $student,
], 'layouts.guest');

$checks = [
    'renders one registration-card style sheet per student' => substr_count($html, 'class="rc-page"') === 2,
    'keeps the single-student registration card label' => str_contains($registrationHtml, 'Student Registration Card'),
    'keeps registration-only confirmation text' => str_contains($registrationHtml, 'Registration has been completed successfully'),
    'keeps registration date in the single-student card' => str_contains($registrationHtml, 'Date of Registration &amp; Time'),
    'uses the Admit Card label, not the registration-card label' => str_contains($html, 'Admit Card') && !str_contains($html, 'Student Registration Card'),
    'includes admit-specific exam details' => str_contains($html, 'Exam Date &amp; Time') && str_contains($html, 'Exam Venue'),
    'includes both students in the printable output' => str_contains($html, 'REG-001') && str_contains($html, 'REG-002') && str_contains($html, 'Student Two'),
    'offers an individual print action with each registration number' => substr_count($html, ' data-print-card') === 2 && str_contains($html, 'data-registration="REG-001"') && str_contains($html, 'data-registration="REG-002"'),
    'sets the PDF filename from the prefix, session, and registration number' => str_contains($html, "document.title = 'admin_card_'") && str_contains($html, 'button.dataset.session') && str_contains($html, 'button.dataset.registration'),
    'escapes student-provided HTML' => str_contains($html, '&lt;Student One&gt;'),
    'keeps the shared registration-card visual system' => str_contains($html, '.rc-banner') && str_contains($html, '.reg-card'),
];

foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . "  $label\n";
}
if (in_array(false, $checks, true)) {
    exit(1);
}
