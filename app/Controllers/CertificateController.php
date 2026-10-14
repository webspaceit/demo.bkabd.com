<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Query;
use App\Core\Request;
use App\Core\View;

/**
 * Student and institute certificates (ported from the original ReportController
 * certificate() / institute_certificate() and their Vue pages).
 *
 * The original rendered printable HTML (the browser prints it), so the
 * certificate endpoints return HTML views, not PDFs.
 *
 *   GET /student-certificate                           -> picker page
 *   GET /institute-certificate                         -> picker page
 *   GET /api/certificate/{instituteId}/{class}         -> printable student certificates
 *   GET /api/institute-certificate/{instituteId}       -> printable institute certificate
 */
final class CertificateController extends Controller
{
    /** GET /student-certificate — picker page. */
    public function pageStudent(Request $request): string
    {
        return View::render('certificates.student_index', [
            'title' => 'Student Certificate',
        ], 'layouts.dashboard');
    }

    /** GET /institute-certificate — picker page. */
    public function pageInstitute(Request $request): string
    {
        return View::render('certificates.institute_index', [
            'title' => 'Institute Certificate',
        ], 'layouts.dashboard');
    }

    /**
     * Students of an institute in the active session, class and grade 1-3,
     * ordered by roll (original: whereIn('grade',[1,2,3])->orderBy('roll')).
     */
    public function student(Request $request): string
    {
        $session = Auth::activeSession();
        // RLS/BOLA: center users may only print certificates for their own institutes.
        Auth::assertInstituteAccess((int) $request->params['instituteId']);

        $students = (new Query('students'))
            ->leftJoin('centers', 'centers.id', '=', 'students.center_id')
            ->leftJoin('users', 'users.id', '=', 'centers.user_id')
            ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
            ->select('students.*', 'users.name AS center_name', 'institutes.name AS institute_name')
            ->where('students.institute_id', (int) $request->params['instituteId'])
            ->where('students.session', $session)
            ->where('students.class', (int) $request->params['class'])
            // Approval workflow: pending registrations get no certificate
            // (qualified students are Active by definition).
            ->where('students.active', 1)
            ->whereIn('students.grade', [1, 2, 3])
            ->orderBy('students.roll', 'asc')
            ->get();

        return View::render('certificates.student', [
            'students' => $students,
            'session' => $session,
        ]);
    }

    /**
     * Institute membership certificate. The original hit dd() when no validity
     * date was set; now it returns a 422 with a readable message.
     */
    public function institute(Request $request): string
    {
        // RLS/BOLA: center users may only print certificates for their own institutes.
        Auth::assertInstituteAccess((int) $request->params['instituteId']);

        $institute = (new Query('institutes'))
            ->where('id', (int) $request->params['instituteId'])
            ->where('active', 1)
            ->first();
        if ($institute === null) {
            throw new HttpException(404, 'Institute not found');
        }
        if (empty($institute['validity_date'])) {
            throw new HttpException(422, 'Please set the validity date before downloading the institute certificate.');
        }

        return View::render('certificates.institute', [
            'institute' => $institute,
        ]);
    }
}
