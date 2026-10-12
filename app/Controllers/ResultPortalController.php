<?php

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Services\CaptchaService;
use App\Services\ResultPortalService;

/**
 * Public result portal (no auth) — original Laravel ResultController:
 *   GET  /scholarship-result  → showForm()
 *   POST /result/search       → search()   (plain form post: renders the
 *                               result sheet or redirects back with the
 *                               original messages)
 *   GET  /result/show?data=…  → show()     (deep link; JSON 404 when the
 *                               session's result is not published)
 */
final class ResultPortalController extends Controller
{
    /** GET /scholarship-result — search form with server-issued math captcha. */
    public function showForm(): string
    {
        // App flashes these on redirect-back (one-shot pull): validation
        // errors + old input, or the gate / no-student error message.
        $errors = (array) flash('errors', []);
        $old = (array) flash('old', []);

        return View::render('public.result', [
            'title' => 'Scholarship Exam Result',
            'sessions' => ResultPortalService::resultSessions(),
            'captchaEquation' => CaptchaService::issue(),
            'errors' => $errors,
            'old' => $old,
            'gateError' => flash('error'),
            'pageScripts' => ['/assets/js/result.js'],
        ], 'layouts.guest');
    }

    /** POST /result/search — validate + captcha + gate + lookup. */
    public function search(Request $request): string|array
    {
        // Read the captcha answer, then RELEASE the session file lock
        // before the heavy queries — under result-day load we must not
        // serialize requests per session while searching.
        $expectedAnswer = CaptchaService::consume();
        Session::close();

        // Lightweight per-IP throttle (30/min) — 429 page/JSON when exceeded.
        ResultPortalService::assertSearchThrottle($request);

        $data = Validator::validate($request, [
            'captcha' => 'required|numeric',
            // Registration is UNIQUE — it alone is enough to search.
            'registration' => 'nullable|string',
            // Fallback: (session, class, roll) is a UNIQUE index.
            'session_id' => 'nullable',
            'class' => 'nullable',
            'roll' => 'nullable',
            // Accepted for old bookmarked forms / deep links; not required.
            'division_id' => 'nullable',
            'center_id' => 'nullable',
        ], [
            'captcha.numeric' => 'The captcha answer must be a number.',
        ]);

        $registration = trim((string) ($data['registration'] ?? ''));
        $sessionId = trim((string) ($data['session_id'] ?? ''));
        $class = trim((string) ($data['class'] ?? ''));
        $roll = trim((string) ($data['roll'] ?? ''));

        // Need a registration number, or the full fallback lookup set.
        if ($registration === '' && !($sessionId !== '' && $class !== '' && $roll !== '')) {
            throw new ValidationException(['registration' => ['Enter your Registration No — or fill Session, Class and Roll No.']]);
        }

        // Session-backed answer — the original's hidden `captcha_answer`
        // field could be forged, this cannot (fresh problem per form GET).
        if (!CaptchaService::isCorrect($expectedAnswer, $data['captcha'])) {
            throw new ValidationException(['captcha' => ['The captcha answer is incorrect.']]);
        }

        if ($registration !== '') {
            $student = ResultPortalService::findStudent(['registration' => $registration]);
            if ($student === null) {
                $this->notFound($request);
            }
            $sessionRow = ResultPortalService::gate((string) $student['session']);
            if ($sessionRow === null) {
                $this->notPublished($request, (string) $student['session']);
            }
        } else {
            $sessionRow = ResultPortalService::gate($sessionId);
            if ($sessionRow === null) {
                $this->notPublished($request, $sessionId);
            }
            $student = ResultPortalService::findStudent(['session_id' => $sessionId, 'class' => $class, 'roll' => $roll]);
            if ($student === null) {
                $this->notFound($request);
            }
        }

        if ($request->expectsJson()) {
            // Original searchAjax() success payload (keys kept identical).
            return ['result' => [
                'center_id' => (string) ($student['center_id'] ?? ''),
                'division_id' => (string) ($student['division_id'] ?? ''),
                'class' => $student['class'],
                'registration' => $student['registration'],
                'session' => $student['session'],
                'roll' => $student['roll'],
            ]];
        }

        // Success: render the result sheet directly.
        return View::render('public.result_show', $this->sheet($student, $sessionRow, $request), 'layouts.guest');
    }

    /**
     * GET /result/pdf?data={json} — the exact result sheet as a real PDF
     * (dompdf). Same deep-link payload as /result/show; this is what the QR on
     * the printed sheet points at.
     */
    public function pdf(Request $request): never
    {
        $raw = $request->input('data', '{}');
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($data) ? $data : [];

        $student = ResultPortalService::findStudent([
            'registration' => (string) ($data['registration'] ?? ''),
            'session_id' => (string) ($data['session'] ?? ''),
            'class' => (string) ($data['class'] ?? ''),
            'roll' => (string) ($data['roll'] ?? ''),
        ]);
        if ($student === null) {
            throw new HttpException(404, 'No student found.Please give correct information.');
        }

        $sessionRow = ResultPortalService::gate((string) $student['session']);
        if ($sessionRow === null) {
            throw new HttpException(404, 'The result has not been published yet.');
        }

        $html = View::render('public.result_pdf', $this->sheet($student, $sessionRow, $request));

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', BASE_PATH . '/public');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        // Always A4, portrait — also behind the QR link.
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        Response::inline(
            $dompdf->output(),
            'result-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $student['registration']) . '.pdf'
        );
    }

    /** GET /result/show?data={json} — deep link to a result sheet. */
    public function show(Request $request): string
    {
        $raw = $request->input('data', '{}');
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($data) ? $data : [];

        // Registration alone is enough; otherwise fall back to session/class/roll.
        $student = ResultPortalService::findStudent([
            'registration' => (string) ($data['registration'] ?? ''),
            'session_id' => (string) ($data['session'] ?? ''),
            'class' => (string) ($data['class'] ?? ''),
            'roll' => (string) ($data['roll'] ?? ''),
        ]);
        // Original crashed with a 500 here — return a proper 404 instead.
        if ($student === null) {
            throw new HttpException(404, 'No student found.Please give correct information.');
        }

        $sessionRow = ResultPortalService::gate((string) $student['session']);
        if ($sessionRow === null) {
            // Original behavior: JSON 404 with its exact message.
            Response::json(['error' => 'Scholarship Result -' . $student['session']
                . ' have not been publish yet. Please wait till specified date & time. Thank You.'], 404);
        }

        return View::render('public.result_show', $this->sheet($student, $sessionRow, $request), 'layouts.guest');
    }

    /** Shared view payload for the result sheet (one lookup + cached full marks). */
    private function sheet(array $student, array $sessionRow, Request $request): array
    {
        // The QR on the printed sheet opens this student's result as a PDF.
        $pdfLink = '/result/pdf?data=' . rawurlencode((string) json_encode([
            'registration' => (string) ($student['registration'] ?? ''),
        ]));

        return [
            'title' => 'Scholarship Result ' . $student['session'],
            'student' => $student,
            'fullMarks' => ResultPortalService::fullMarks((string) $student['session']),
            'resultTime' => $sessionRow['result_time'],
            'qrContent' => $this->absoluteUrl($request, $pdfLink),
        ];
    }

    /** Absolute URL for a path, honouring the proxy's forwarded protocol. */
    private function absoluteUrl(Request $request, string $path): string
    {
        $https = (($_SERVER['HTTPS'] ?? '') === 'on')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $host = (string) ($request->header('Host') ?? ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return ($https ? 'https://' : 'http://') . $host . $path;
    }

    /** No matching student: JSON 404 for Ajax, flash + redirect for plain posts. */
    private function notFound(Request $request): never
    {
        if ($request->expectsJson()) {
            Response::json(['error' => 'No student found.Please give correct information.'], 404);
        }
        Session::flash('error', 'No student found. Please provide correct information.');
        Response::redirect($this->backPath($request));
    }

    /** Result not published yet: original JSON message / flash + redirect. */
    private function notPublished(Request $request, string $session): never
    {
        if ($request->expectsJson()) {
            Response::json(['error' => 'Scholarship Result -' . $session
                . ' have not been publish yet. Please wait till specified date & time. Thank You.'], 404);
        }
        Session::flash('error', 'Scholarship Result for session ' . $session
            . ' has not been published yet. Please wait till the specified date & time.');
        Response::redirect($this->backPath($request));
    }

    /** Where redirect-back lands (the form) when no Referer is sent. */
    private function backPath(Request $request): string
    {
        $referer = $request->header('Referer');
        return $referer ? (parse_url($referer, PHP_URL_PATH) ?: '/scholarship-result') : '/scholarship-result';
    }
}
