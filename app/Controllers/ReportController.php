<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ReportService;
use App\Services\ReportSheetService;
use App\Support\ReportPdf;

/**
 * Report controller — ported from the original ReportController.
 * Endpoint contracts (URL + response keys) identical to original routes/api.php.
 *
 * Heavy queries go through ReportService / ReportSheetService (single JOIN per
 * sheet, cached dropdowns, result-day indexes).
 *
 * Sheet reports are served as printable HTML (the browser's "Print / Save as
 * PDF" produces the PDF), matching how the clone serves certificates. This keeps
 * thousand-row sheets inside shared-hosting memory — dompdf's table engine
 * cannot lay out sheets that large. The three Excel exports use PhpSpreadsheet.
 *
 * Result sheets live in ResultPortalController (/result/show) and mark entry in
 * MarkEntryController (/api/markentrystudent/updateTotalNumbers).
 */
final class ReportController extends Controller
{
    /** Class-order SQL used by the original reports (KG first). */
    private const FIELD_CLASS = 'FIELD(`students`.`class`,6,1,2,3,4,5)';

    // ---------------------------------------------------------------
    // Row Level Security helpers (id/scope guards, backend of BOLA fixes)
    // Admins may read any center/institute; center users (type 4) are
    // scoped to their own center (centers.user_id = users.id).
    // ---------------------------------------------------------------

    /** 403 unless the {id} in the URL is the caller's own center. */
    private function assertCenter(int $centerId): void
    {
        Auth::assertCenterAccess($centerId);
    }

    /**
     * Center scope for "all centers" reports: null = all centers (admin),
     * the caller's own center id for center users.
     */
    private function centerScopeOrNull(): ?int
    {
        return Auth::isAdmin() ? null : (int) (Auth::centerId() ?? 0);
    }

    /**
     * Resolve a report {id} where 0 means "all centers". Center users asking
     * for id=0 are pinned to their own center; any other id must be theirs.
     */
    private function resolveCenter(int $id): int
    {
        if (!Auth::isAdmin()) {
            $mine = (int) (Auth::centerId() ?? 0);
            if ($id === 0) {
                return $mine;
            }
            Auth::assertCenterAccess($id);
            return $id;
        }
        return $id;
    }

    // ===============================================================
    // JSON endpoints
    // ===============================================================

    /** Print student list. GET /api/report-student-list equivalent. */
    public function studentList(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $filters = $request->all();
        $paginator = ReportService::studentList($filters);

        return $this->sendResponse([
            'studentList' => $paginator['data'],
            'paginator'   => $paginator,
        ], 'Student list report retrieved.');
    }

    /** Full marks config. GET /api/fullmarks equivalent. */
    public function fullmarks(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $session = $request->input('session');
        $marks = ReportService::fullmarks((int) ($session ?? 0));

        if ($marks === false) {
            return $this->sendError('No full marks configuration found for this session.', [], 404);
        }

        return $this->sendResponse($marks, 'Full marks retrieved.');
    }

    /** Center list for reports. */
    public function centersForReports(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $centers = ReportService::centersCache();
        // RLS: a center user's picker lists only their own center.
        if (!Auth::isAdmin()) {
            $mine = (int) (Auth::centerId() ?? 0);
            $centers = array_values(array_filter($centers, static fn($c) => (int) ($c['id'] ?? 0) === $mine));
        }

        return $this->sendResponse(['centerList' => $centers], 'Center list retrieved.');
    }

    /** Institute list by center. */
    public function institutesByCenter(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $centerId = $request->input('center_id');
        if ($centerId === null) {
            return $this->sendError('center_id is required.', [], 400);
        }

        // RLS/BOLA: a center user may only list institutes of their own center.
        Auth::assertCenterAccess((int) $centerId);

        $institutes = ReportService::institutesByCenterCache((int) $centerId);

        return $this->sendResponse(['instituteList' => $institutes], 'Institute list retrieved.');
    }

    /** GET /reports — report hub (links to the report pages that are available). */
    public function pageHub(Request $request): string
    {
        return View::render('reports.index', [
            'title' => 'Reports',
        ], 'layouts.dashboard');
    }

    // ===============================================================
    // Excel exports
    // ===============================================================

    /** Institute student details as .xlsx. */
    public function institutestudentdetailsexport(Request $request): never
    {
        $id = (int) $request->params['id'];
        Auth::assertInstituteAccess($id); // RLS/BOLA
        $session = $this->activeSession();
        $students = ReportSheetService::rows([
            'session' => $session,
            'institute_id' => $id,
            'orders' => [
                ['raw' => self::FIELD_CLASS],
                ['col' => 'students.roll', 'dir' => 'asc'],
            ],
        ]);
        if ($students === []) {
            ReportPdf::noData('Institute');
        }

        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            $rows[] = $this->detailRow($i++, $s);
        }

        $name = $this->safeName((string) ($students[0]['institute_name'] ?? 'Institute'));
        ReportPdf::xlsx($this->detailHeadings(), $rows, $name . ' Student Details.xlsx', 'Institute Students');
    }

    /** Center student details as .xlsx. */
    public function centerstudentdetailsexport(Request $request): never
    {
        $this->centerStudentExcel((int) $request->params['id']);
    }

    /** Center student details (without image) as .xlsx. */
    public function centerstudentdetailsexportwithoutimage(Request $request): never
    {
        $this->centerStudentExcel((int) $request->params['id']);
    }

    // ===============================================================
    // Center info
    // ===============================================================

    /** All-centers (id=0) or single-center info sheet. */
    public function centerinfo(Request $request): string
    {
        // RLS: id=0 (all centers) becomes the caller's own center for center users.
        $id = $this->resolveCenter((int) $request->params['id']);
        $session = $this->activeSession();

        if ($id > 0) {
            $counts = ReportService::classCounts($session, $id);
            $rows = ReportSheetService::instituteClassCounts($session, $id);
            $headings = ['Sl', 'Institute Name', 'KG', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'Total'];
            $scope = 'Center Name: ' . ReportService::centerName($id);
        } else {
            $counts = ReportService::classCounts($session);
            $rows = ReportSheetService::centerClassCounts($session);
            $headings = ['Sl', 'Center Name', 'KG', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'Total'];
            $scope = 'All Centers';
        }

        $tableRows = [];
        $i = 1;
        foreach ($rows as $r) {
            $tableRows[] = [
                $i++, $r['group_name'] ?? '',
                (int) ($r['kg'] ?? 0), (int) ($r['one'] ?? 0), (int) ($r['two'] ?? 0),
                (int) ($r['three'] ?? 0), (int) ($r['four'] ?? 0), (int) ($r['five'] ?? 0),
                (int) ($r['total'] ?? 0),
            ];
        }

        return $this->renderSheet([
            'reportTitle' => 'Center Information Report',
            'scopeName' => $scope,
            'summaries' => [$this->classSummary($counts)],
            'headings' => $headings,
            'rows' => $tableRows,
        ], 'landscape');
    }

    // ===============================================================
    // Center / institute student detail reports
    // ===============================================================

    /** Center-wise student details with photo. */
    public function centerwisedetails(Request $request): string
    {
        return $this->centerStudentPdf((int) $request->params['id'], true);
    }

    /** Center-wise student details without photo (adds gender/religion/education). */
    public function centerwisedetailswithoutimage(Request $request): string
    {
        return $this->centerStudentPdf((int) $request->params['id'], false);
    }

    /** Institute-wise student details. */
    public function institutewisedetails(Request $request): string
    {
        $id = (int) $request->params['id'];
        Auth::assertInstituteAccess($id); // RLS/BOLA
        $session = $this->activeSession();
        $students = ReportSheetService::rows([
            'session' => $session,
            'institute_id' => $id,
            'orders' => [
                ['raw' => self::FIELD_CLASS],
                ['col' => 'students.roll', 'dir' => 'asc'],
            ],
        ]);
        if ($students === []) {
            ReportPdf::noData('Institute');
        }

        $counts = ReportService::classCounts($session, null, $id);
        $headings = ['Sl', 'Class', 'Registration No', 'Roll No', 'Student Name', "Father's Name", "Mother's Name", 'Contact No', 'Date of Birth', 'Photo'];
        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            $rows[] = [
                $i++, ClassName($s['class'] ?? 0), $s['registration'] ?? '', $s['roll'] ?? '',
                $s['name'] ?? '', $s['father_name'] ?? '', $s['mother_name'] ?? '',
                $s['phone'] ?? '', $this->dob($s['dob'] ?? ''),
                ['img' => $this->photoData($s['photo'] ?? null)],
            ];
        }

        return $this->renderSheet([
            'reportTitle' => 'Institute Wise Student Details Report',
            'scopeName' => 'Institute Name: ' . ($students[0]['institute_name'] ?? ''),
            'summaries' => [$this->classSummary($counts)],
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    // ===============================================================
    // Attendance sheets
    // ===============================================================

    /** Class-wise exam attendance sheet (blank marks). */
    public function ClassWiseExamAttendSheetReport(Request $request): string
    {
        return $this->attendSheetPdf((int) $request->params['id'], false, false);
    }

    /** Attendance sheet, class-then-institute order. */
    public function ClassWiseExamAttendSheetReport2(Request $request): string
    {
        return $this->attendSheetPdf((int) $request->params['id'], false, true);
    }

    /** Attendance sheet with student photo. */
    public function ClassWiseExamAttendSheetReportWithPhoto(Request $request): string
    {
        return $this->attendSheetPdf((int) $request->params['id'], true, false);
    }

    /** Attendance sheet with student photo, class-then-institute order. */
    public function ClassWiseExamAttendSheetReportWithPhoto2(Request $request): string
    {
        return $this->attendSheetPdf((int) $request->params['id'], true, true);
    }

    // ===============================================================
    // Issued admit card report
    // ===============================================================

    /** Center-wise students with admit-card issued remarks. */
    public function CenterWiseStudentDetailsReportIssuedAdmitCard(Request $request): string
    {
        $id = (int) $request->params['id'];
        Auth::assertCenterAccess($id); // RLS/BOLA
        $session = $this->activeSession();
        $students = ReportSheetService::rows(['session' => $session, 'center_id' => $id]);
        if ($students === []) {
            ReportPdf::noData('Center');
        }

        $headings = ['Sl', 'Institute Name', 'Class', 'Reg. No.', 'Roll No.', 'Student Name', "Father's Name", "Mother's Name", 'Gender', 'Religion', 'Contact No', 'Date of Birth', 'Remarks'];
        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            $rows[] = [
                $i++, $s['institute_name'] ?? '', ClassName($s['class'] ?? 0),
                $s['registration'] ?? '', $s['roll'] ?? '', $s['name'] ?? '',
                $s['father_name'] ?? '', $s['mother_name'] ?? '',
                GenderName($s['gender'] ?? ''), ReligionName($s['religion'] ?? ''),
                $s['phone'] ?? '', $this->dob($s['dob'] ?? ''), '',
            ];
        }

        $centerName = ReportService::centerName($id);
        return $this->renderSheet([
            'reportTitle' => 'Center Wise Student Details (Admit Card Issued)',
            'scopeName' => 'Center Name: ' . $centerName,
            'summaries' => [$this->classSummary(ReportService::classCounts($session, $id))],
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    // ===============================================================
    // Marks reports
    // ===============================================================

    /** Center-wise highest marks. */
    public function CenterWiseHighestMarksReport(Request $request): string
    {
        return $this->marksReportPdf((int) $request->params['id'], null, false);
    }

    /** Institute-wise (or center-wise when inst_id=0) highest marks. */
    public function InstituteWiseHighestMarksReport(Request $request): string
    {
        $id = (int) $request->params['id'];
        $instId = (int) $request->params['inst_id'];
        return $this->marksReportPdf($id, $instId > 0 ? $instId : null, true);
    }

    /** Center-wise tabulation sheet. */
    public function CenterWiseTabulationSheet(Request $request): string
    {
        return $this->marksReportPdf((int) $request->params['id'], null, false, 'Center Wise Tabulation Sheet');
    }

    // ===============================================================
    // Grade / result reports
    // ===============================================================

    /** Center-wise grade sheet count. */
    public function CenterWiseGradeSheetCountReport(Request $request): string
    {
        $id = (int) $request->params['id'];
        Auth::assertCenterAccess($id); // RLS/BOLA
        $session = $this->activeSession();
        $students = ReportSheetService::rows([
            'session' => $session,
            'center_id' => $id,
            'grades' => [1, 2, 3],
            'orders' => [
                ['raw' => self::FIELD_CLASS],
                ['col' => 'students.total', 'dir' => 'desc'],
            ],
        ]);
        if ($students === []) {
            ReportPdf::noData('Center');
        }

        $headings = ['Sl', 'Class', 'Grade', 'Roll No', 'Registration No', 'Total Marks'];
        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            $rows[] = [
                $i++, ClassName($s['class'] ?? 0), GradeName($s['grade'] ?? ''),
                $s['roll'] ?? '', $s['registration'] ?? '', $s['total'] ?? '',
            ];
        }

        $centerName = ReportService::centerName($id);
        return $this->renderSheet([
            'reportTitle' => 'Center Wise Grade Sheet Count Report',
            'scopeName' => 'Center Name: ' . $centerName,
            'summaries' => [$this->gradeSummary(ReportService::gradeCounts($session, $id))],
            'headings' => $headings,
            'rows' => $rows,
        ]);
    }

    /** Center-wise grade list + merit position counts. */
    public function CenterWiseGradeListMeritPositionCountReport(Request $request): string
    {
        $session = $this->activeSession();
        $centers = ReportSheetService::centerGradeMerit($session);
        $overall = ReportSheetService::overallGradeMerit($session);

        // RLS: center users see only their own center — the "overall" total row
        // is recomputed from the centers actually shown (no cross-center leak).
        if (!Auth::isAdmin()) {
            $mine = (int) (Auth::centerId() ?? 0);
            $centers = array_values(array_filter($centers, static fn($c) => (int) ($c['id'] ?? 0) === $mine));
            $overall = ['total' => 0, 'present' => 0, 'absent' => 0, 'tp' => 0, 'gg' => 0, 'sg' => 0, 'position1' => 0, 'position2' => 0, 'position3' => 0];
            foreach ($centers as $c) {
                $overall['total'] += (int) $c['students_count'];
                $overall['present'] += (int) $c['students_present'];
                $overall['absent'] += (int) $c['students_absent'];
                $overall['tp'] += (int) $c['tp'];
                $overall['gg'] += (int) $c['gg'];
                $overall['sg'] += (int) $c['sg'];
                $overall['position1'] += (int) $c['position1'];
                $overall['position2'] += (int) $c['position2'];
                $overall['position3'] += (int) $c['position3'];
            }
        }

        $headings = ['Sl', 'Center Name', 'Students', 'Present', 'Absent', 'Talent Pool', 'General Grade', 'School Grade', '1st', '2nd', '3rd'];
        $rows = [];
        $i = 1;
        foreach ($centers as $c) {
            $rows[] = [
                $i++, $c['center_name'] ?? '',
                (int) $c['students_count'], (int) $c['students_present'], (int) $c['students_absent'],
                (int) $c['tp'], (int) $c['gg'], (int) $c['sg'],
                (int) $c['position1'], (int) $c['position2'], (int) $c['position3'],
            ];
        }
        $rows[] = [
            '', 'TOTAL',
            (int) ($overall['total'] ?? 0), (int) ($overall['present'] ?? 0), (int) ($overall['absent'] ?? 0),
            (int) ($overall['tp'] ?? 0), (int) ($overall['gg'] ?? 0), (int) ($overall['sg'] ?? 0),
            (int) ($overall['position1'] ?? 0), (int) ($overall['position2'] ?? 0), (int) ($overall['position3'] ?? 0),
        ];

        return $this->renderSheet([
            'reportTitle' => 'Center Wise Grade List & Merit Position Count Report',
            'scopeName' => 'All Centers',
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    /** Center-wise grade sheet result details. */
    public function CenterWiseGradeSheetResultDetails(Request $request): string
    {
        return $this->gradeSheetResultPdf((int) $request->params['id'], null);
    }

    /** Institute-wise grade sheet result details (inst_id=0 means whole center). */
    public function InstituteWiseGradeSheetResultDetails(Request $request): string
    {
        $id = (int) $request->params['id'];
        $instId = (int) $request->params['inst_id'];
        return $this->gradeSheetResultPdf($id, $instId > 0 ? $instId : null);
    }

    /** Merit list with positions. */
    public function MeritListResultwithPosition(Request $request): string
    {
        $id = (int) $request->params['id'];
        Auth::assertCenterAccess($id); // RLS/BOLA
        $session = $this->activeSession();
        $students = ReportService::meritStudents($id, $session);
        if ($students === []) {
            ReportPdf::noData('Center');
        }

        $merit = ReportService::meritCounts($session, $id);
        $headings = ['Sl', 'Institute Name', 'Class', 'Roll No', 'Reg. No.', 'Student Name', 'Scholarship', 'Position'];
        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            $rows[] = [
                $i++, $s['institute_name'] ?? '', ClassName($s['class'] ?? 0),
                $s['roll'] ?? '', $s['registration'] ?? '', $s['name'] ?? '',
                GradeName($s['grade'] ?? ''), MeritName($s['ml'] ?? ''),
            ];
        }

        $centerName = ReportService::centerName($id);
        return $this->renderSheet([
            'reportTitle' => 'Merit List Result with Position',
            'scopeName' => 'Center Name: ' . $centerName,
            'summaries' => [[
                'headings' => ['1st Position', '2nd Position', '3rd Position', 'Total'],
                'rows' => [[$merit['first'], $merit['second'], $merit['third'], $merit['tot']]],
            ]],
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    // ===============================================================
    // Institute membership / information
    // ===============================================================

    /** Total institute information — .xlsx for all institutes, sheet for one center. */
    public function TotalInstituteInformation(Request $request): string
    {
        // RLS: id=0 (all centers) is admin-only; center users are pinned to
        // their own center.
        $id = $this->resolveCenter((int) $request->params['id']);
        $institutes = ReportService::institutesWithCenter($id > 0 ? $id : null);
        if ($institutes === []) {
            ReportPdf::noData('Institute');
        }
        if ($id === 0) {
            // Original: Total Institute Information is a printable sheet for both
            // "All" (0) and one center — the Excel button was commented out in the
            // original component, so no xlsx branch here.
        }

        return $this->renderSheet([
            'reportTitle' => 'Total Institute Information Report',
            'scopeName' => $id === 0 ? 'All Centers' : 'Center Name: ' . ReportService::centerName($id),
            'headings' => $this->instituteHeadings(),
            'rows' => $this->instituteRows($institutes),
        ], 'landscape');
    }

    /** Life-time membership institutes. */
    public function LifeTimeMembershipInstituteReport(Request $request): string
    {
        return $this->membershipPdf(2, 'Life Time Membership Institute Report');
    }

    /** Temporary membership institutes. */
    public function TemporaryMembershipInstituteReport(Request $request): string
    {
        return $this->membershipPdf(1, 'Temporary Membership Institute Report');
    }

    // ===============================================================
    // Aggregate reports
    // ===============================================================

    /** All center details information. */
    public function AllCenterDetailsInformationReport(Request $request): string
    {
        $centers = ReportSheetService::allCenterDetails();
        // RLS: a center user may only see their own center + authority contact.
        if (!Auth::isAdmin()) {
            $mine = (int) (Auth::centerId() ?? 0);
            $centers = array_values(array_filter($centers, static fn($c) => (int) ($c['id'] ?? 0) === $mine));
        }
        if ($centers === []) {
            ReportPdf::noData('Center');
        }

        $headings = ['Sl', 'Center Name', 'Code', 'Authority', 'Mobile', 'Email', 'Division', 'District', 'Upazila', 'Status'];
        $rows = [];
        $i = 1;
        foreach ($centers as $c) {
            $rows[] = [
                $i++, $c['center_name'] ?? '', $c['code'] ?? '', $c['authority_person'] ?? '',
                $c['phone'] ?? '', $c['email'] ?? '', $c['division'] ?? '', $c['district'] ?? '',
                $c['upazila'] ?? '', ((int) ($c['active'] ?? 0) === 1 ? 'Active' : 'Inactive'),
            ];
        }

        return $this->renderSheet([
            'reportTitle' => 'All Center Details Information Report',
            'scopeName' => 'All Centers',
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    /** Documents distribution (center/class student counts). */
    public function DocumentsDistributionReport(Request $request): string
    {
        $session = $this->activeSession();
        // RLS: center users' "documents distribution" covers their own center only.
        $scopeCenter = $this->centerScopeOrNull();
        $report = ReportSheetService::documentsDistribution($session, $scopeCenter);
        if ($report === []) {
            ReportPdf::noData('Center');
        }

        // Group class counts per center for the "Center Total" column.
        $centerTotals = [];
        foreach ($report as $r) {
            $centerTotals[$r['center_name']] = ($centerTotals[$r['center_name']] ?? 0) + (int) $r['class_count'];
        }

        $headings = ['Sl', 'Division', 'District', 'Upazila', 'Center Name', 'Class', 'Students', 'Center Total'];
        $rows = [];
        $i = 1;
        foreach ($report as $r) {
            $rows[] = [
                $i++, $r['division'] ?? '', $r['district'] ?? '', $r['upazila'] ?? '',
                $r['center_name'] ?? '', ClassName($r['class'] ?? 0),
                (int) $r['class_count'], $centerTotals[$r['center_name']] ?? 0,
            ];
        }

        return $this->renderSheet([
            'reportTitle' => 'Documents Distribution Report',
            'scopeName' => $scopeCenter !== null ? 'Center Name: ' . ReportService::centerName($scopeCenter) : 'All Centers',
            'summaries' => [$this->classSummary(ReportService::classCounts($session, $scopeCenter))],
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    /** Educational type student count. */
    public function EducationalTypeStudentCountReport(Request $request): string
    {
        $session = $this->activeSession();
        // RLS: center users' report covers their own center only.
        $scopeCenter = $this->centerScopeOrNull();
        $report = ReportSheetService::educationalType($session, $scopeCenter);
        if ($report === []) {
            ReportPdf::noData('Center');
        }

        $headings = ['Sl', 'Center Name', 'Class', 'Bangla', 'English', 'Arabic', 'Total'];
        $rows = [];
        $i = 1;
        foreach ($report as $r) {
            $rows[] = [
                $i++, $r['center_name'] ?? '', ClassName($r['class'] ?? 0),
                (int) $r['bangla'], (int) $r['english'], (int) $r['arabic'], (int) $r['total'],
            ];
        }

        return $this->renderSheet([
            'reportTitle' => 'Educational Type Student Count Report',
            'scopeName' => $scopeCenter !== null ? 'Center Name: ' . ReportService::centerName($scopeCenter) : 'All Centers',
            'headings' => $headings,
            'rows' => $rows,
        ]);
    }

    // ===============================================================
    // Shared builders
    // ===============================================================

    /** Render a generic report sheet to printable HTML. */
    private function renderSheet(array $data, string $orientation = 'portrait'): string
    {
        $data += [
            'reportTitle' => '',
            'scopeName' => '',
            'activeSession' => $this->activeSession(),
            'summaries' => [],
            'headings' => [],
            'rows' => [],
        ];
        $data['orientation'] = $orientation;

        return View::render('reports.pdf.table', $data);
    }

    /** Class-count summary block. */
    private function classSummary(array $c): array
    {
        return [
            'headings' => ['KG', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'TOTAL'],
            'rows' => [[
                (int) ($c['kg'] ?? 0), (int) ($c['one'] ?? 0), (int) ($c['two'] ?? 0),
                (int) ($c['three'] ?? 0), (int) ($c['four'] ?? 0), (int) ($c['five'] ?? 0),
                (int) ($c['total'] ?? 0),
            ]],
        ];
    }

    /** Grade-count summary block. */
    private function gradeSummary(array $g): array
    {
        return [
            'headings' => ['Talent Pool', 'General Grade', 'S.G', 'Absent', 'Total Scholarship'],
            'rows' => [[
                (int) ($g['tp'] ?? 0), (int) ($g['gg'] ?? 0), (int) ($g['sg'] ?? 0),
                (int) ($g['ab'] ?? 0), (int) ($g['tot'] ?? 0),
            ]],
        ];
    }

    /** Center student detail spreadsheet. */
    private function centerStudentExcel(int $centerId): never
    {
        Auth::assertCenterAccess($centerId); // RLS/BOLA
        $session = $this->activeSession();
        $students = ReportSheetService::rows(['session' => $session, 'center_id' => $centerId]);
        if ($students === []) {
            ReportPdf::noData('Center');
        }

        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            $rows[] = $this->detailRow($i++, $s);
        }

        $centerName = $this->safeName(ReportService::centerName($centerId));
        ReportPdf::xlsx($this->detailHeadings(), $rows, $centerName . ' Student Details.xlsx', 'Center Students');
    }

    /** Center student detail sheet (with or without photo). */
    private function centerStudentPdf(int $centerId, bool $withPhoto): string
    {
        Auth::assertCenterAccess($centerId); // RLS/BOLA
        $session = $this->activeSession();
        $students = ReportSheetService::rows(['session' => $session, 'center_id' => $centerId]);
        if ($students === []) {
            ReportPdf::noData('Center');
        }

        if ($withPhoto) {
            $headings = ['Sl', 'Institute Name', 'Class', 'Reg. No.', 'Student Name', "Father's Name", "Mother's Name", 'Contact No', 'Date of Birth', 'Photo'];
        } else {
            $headings = ['Sl', 'Institute Name', 'Class', 'Reg. No.', 'Roll No.', 'Student Name', "Father's Name", "Mother's Name", 'Gender', 'Religion', 'Education Type'];
        }

        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            if ($withPhoto) {
                $rows[] = [
                    $i++, $s['institute_name'] ?? '', ClassName($s['class'] ?? 0),
                    $s['registration'] ?? '', $s['name'] ?? '', $s['father_name'] ?? '',
                    $s['mother_name'] ?? '', $s['phone'] ?? '', $this->dob($s['dob'] ?? ''),
                    ['img' => $this->photoData($s['photo'] ?? null)],
                ];
            } else {
                $rows[] = [
                    $i++, $s['institute_name'] ?? '', ClassName($s['class'] ?? 0),
                    $s['registration'] ?? '', $s['roll'] ?? '', $s['name'] ?? '',
                    $s['father_name'] ?? '', $s['mother_name'] ?? '',
                    GenderName($s['gender'] ?? ''), ReligionName($s['religion'] ?? ''),
                    EducationType($s['education_type'] ?? ''),
                ];
            }
        }

        $centerName = ReportService::centerName($centerId);
        return $this->renderSheet([
            'reportTitle' => $withPhoto ? 'Center Wise Student Details with Image' : 'Center Wise Student Details without Image',
            'scopeName' => 'Center Name: ' . $centerName,
            'summaries' => [$this->classSummary(ReportService::classCounts($session, $centerId))],
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    /** Class-wise attendance sheet. */
    private function attendSheetPdf(int $centerId, bool $withPhoto, bool $classFirst): string
    {
        Auth::assertCenterAccess($centerId); // RLS/BOLA
        $session = $this->activeSession();
        $orders = $classFirst
            ? [
                ['raw' => self::FIELD_CLASS],
                ['col' => 'institutes.name', 'dir' => 'asc'],
                ['col' => 'students.roll', 'dir' => 'asc'],
            ]
            : [
                ['col' => 'institutes.name', 'dir' => 'asc'],
                ['raw' => self::FIELD_CLASS],
                ['col' => 'students.roll', 'dir' => 'asc'],
            ];
        $students = ReportSheetService::rows([
            'session' => $session,
            'center_id' => $centerId,
            'orders' => $orders,
        ]);
        if ($students === []) {
            ReportPdf::noData('Center');
        }

        $headings = ['Sl', 'Institute Name', 'Class', 'Reg. No.', 'Roll No.', 'Student Name', 'Bangla', 'English', 'Math'];
        if ($withPhoto) {
            $headings[] = 'Photo';
        }

        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            $row = [
                $i++, $s['institute_name'] ?? '', ClassName($s['class'] ?? 0),
                $s['registration'] ?? '', $s['roll'] ?? '', $s['name'] ?? '', '', '', '',
            ];
            if ($withPhoto) {
                $row[] = ['img' => $this->photoData($s['photo'] ?? null)];
            }
            $rows[] = $row;
        }

        $centerName = ReportService::centerName($centerId);
        return $this->renderSheet([
            'reportTitle' => 'Class Wise Exam Attendance Sheet' . ($withPhoto ? ' with Image' : ''),
            'scopeName' => 'Center Name: ' . $centerName,
            'summaries' => [$this->classSummary(ReportService::classCounts($session, $centerId))],
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    /** Marks report sheet (highest marks / tabulation). */
    private function marksReportPdf(int $centerId, ?int $instituteId, bool $instituteWise, string $title = 'Highest Marks Report'): string
    {
        // RLS/BOLA: the center must be the caller's own; a specific institute
        // must also belong to that center (admins pass through).
        Auth::assertCenterAccess($centerId);
        if ($instituteId !== null) {
            Auth::assertInstituteAccess($instituteId);
        }
        $session = $this->activeSession();
        $students = ReportSheetService::rows([
            'session' => $session,
            'center_id' => $centerId,
            'institute_id' => $instituteId,
            'orders' => ReportSheetService::orderClassThenTotal(),
        ]);
        if ($students === []) {
            ReportPdf::noData($instituteId ? 'Institute' : 'Center');
        }

        $headings = ['Sl', 'Institute Name', 'Class', 'Roll No', 'Reg. No.', 'Student Name', 'Bangla', 'English', 'Math', 'Total', 'Grade', 'Position'];
        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            $rows[] = [
                $i++, $s['institute_name'] ?? '', ClassName($s['class'] ?? 0),
                $s['roll'] ?? '', $s['registration'] ?? '', $s['name'] ?? '',
                $s['bangla'] ?? '', $s['english'] ?? '', $s['math'] ?? '',
                $s['total'] ?? '', GradeName($s['grade'] ?? ''), MeritName($s['ml'] ?? ''),
            ];
        }

        if ($instituteId !== null) {
            $scope = 'Institute Name: ' . ReportSheetService::instituteName($instituteId);
        } else {
            $scope = 'Center Name: ' . ReportService::centerName($centerId);
        }

        return $this->renderSheet([
            'reportTitle' => ($instituteId !== null ? 'Institute Wise ' : 'Center Wise ') . $title,
            'scopeName' => $scope,
            'summaries' => [$this->classSummary(ReportService::classCounts($session, $centerId, $instituteId))],
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    /** Grade sheet result details (center-wide or one institute). */
    private function gradeSheetResultPdf(int $centerId, ?int $instituteId): string
    {
        // RLS/BOLA: same ownership rules as marksReportPdf.
        Auth::assertCenterAccess($centerId);
        if ($instituteId !== null) {
            Auth::assertInstituteAccess($instituteId);
        }
        $session = $this->activeSession();
        $students = ReportService::certifiedStudents($centerId, $session, $instituteId);
        if ($students === []) {
            ReportPdf::noData($instituteId ? 'Institute' : 'Center');
        }

        $counts = ReportService::classCounts($session, $centerId, $instituteId);
        $grades = ReportService::gradeCounts($session, $centerId, $instituteId);

        $headings = ['Sl', 'Institute Name', 'Class', 'Roll No', 'Reg. No.', 'Student Name', 'Scholarship', 'Position'];
        $rows = [];
        $i = 1;
        foreach ($students as $s) {
            $rows[] = [
                $i++, $s['institute_name'] ?? '', ClassName($s['class'] ?? 0),
                $s['roll'] ?? '', $s['registration'] ?? '', $s['name'] ?? '',
                GradeName($s['grade'] ?? ''), MeritName($s['ml'] ?? ''),
            ];
        }

        if ($instituteId !== null) {
            $scope = 'Institute Name: ' . ReportSheetService::instituteName($instituteId);
        } else {
            $scope = 'Center Name: ' . ReportService::centerName($centerId);
        }

        return $this->renderSheet([
            'reportTitle' => ($instituteId !== null ? 'Institute' : 'Center') . ' Wise Grade Sheet Result Details',
            'scopeName' => $scope,
            'summaries' => [$this->classSummary($counts), $this->gradeSummary($grades)],
            'headings' => $headings,
            'rows' => $rows,
        ], 'landscape');
    }

    /** Membership institute sheet. */
    private function membershipPdf(int $membershipType, string $title): string
    {
        // RLS: lifetime/temporary membership lists are scoped to the caller's
        // own center for center users (admins see all centers).
        $institutes = ReportService::institutesWithCenter($this->centerScopeOrNull(), $membershipType);
        if ($institutes === []) {
            ReportPdf::noData('Institute');
        }

        return $this->renderSheet([
            'reportTitle' => $title,
            'scopeName' => MembershipType($membershipType),
            'headings' => $this->instituteHeadings(),
            'rows' => $this->instituteRows($institutes),
        ], 'landscape');
    }

    /** Standard student-detail spreadsheet headings. */
    private function detailHeadings(): array
    {
        return ['Sl', 'Institute Name', 'Class', 'Registration No', 'Roll No', 'Student Name', "Father's Name", "Mother's Name", 'Gender', 'Religion', 'Date of Birth', 'Education Type', 'Contact No'];
    }

    /** Standard student-detail row. */
    private function detailRow(int $i, array $s): array
    {
        return [
            $i,
            $s['institute_name'] ?? '',
            ClassName($s['class'] ?? 0),
            $s['registration'] ?? '',
            $s['roll'] ?? '',
            $s['name'] ?? '',
            $s['father_name'] ?? '',
            $s['mother_name'] ?? '',
            GenderName($s['gender'] ?? ''),
            ReligionName($s['religion'] ?? ''),
            $this->dob($s['dob'] ?? ''),
            EducationType($s['education_type'] ?? ''),
            $s['phone'] ?? '',
        ];
    }

    /** Standard institute-table headings. */
    private function instituteHeadings(): array
    {
        return ['Sl', 'Institute Name', 'Center Name', 'Director', 'Mobile', 'Email', 'Membership'];
    }

    /** Map institute rows to the standard table. */
    private function instituteRows(array $institutes): array
    {
        $rows = [];
        $i = 1;
        foreach ($institutes as $ins) {
            $rows[] = [
                $i++, $ins['name'] ?? '', $ins['center_name'] ?? '', $ins['director_name'] ?? '',
                $ins['phone'] ?? '', $ins['email'] ?? '', MembershipType($ins['membership_type'] ?? ''),
            ];
        }
        return $rows;
    }

    /** Embed a student photo as a small base64 thumbnail. */
    private function photoData(?string $photo): string
    {
        if ($photo === null || $photo === '') {
            return '';
        }
        $path = BASE_PATH . '/public/images/students/' . basename($photo);
        if (!is_file($path)) {
            return '';
        }

        $binary = @file_get_contents($path);
        if ($binary === false) {
            return '';
        }

        // Downscale to a thumbnail: report sheets can hold thousands of photos.
        if (function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($binary);
            if ($src !== false) {
                $w = max(1, imagesx($src));
                $h = max(1, imagesy($src));
                $max = 48;
                $scale = min($max / $w, $max / $h, 1.0);
                $nw = max(1, (int) round($w * $scale));
                $nh = max(1, (int) round($h * $scale));
                $dst = imagecreatetruecolor($nw, $nh);
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($src);
                ob_start();
                imagejpeg($dst, null, 70);
                $thumb = (string) ob_get_clean();
                imagedestroy($dst);
                if ($thumb !== '') {
                    return 'data:image/jpeg;base64,' . base64_encode($thumb);
                }
            }
        }

        $mime = @mime_content_type($path) ?: 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    /** Format a date for a report; empty when missing. */
    private function dob(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $ts = strtotime($date);
        return $ts !== false ? date('m/d/Y', $ts) : $date;
    }

    /** Sanitise a value for use in a download filename. */
    private function safeName(string $name): string
    {
        $name = trim((string) preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $name));
        return $name !== '' ? $name : 'report';
    }
}
