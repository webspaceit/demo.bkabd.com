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
            'divisions' => ResultPortalService::divisions(),
            'centers' => ResultPortalService::centers(),
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
            'session_id' => 'required',
            'division_id' => 'required',
            'center_id' => 'required',
            'class' => 'required',
            'registration' => 'required',
            'roll' => 'required',
            'captcha' => 'required|numeric',
        ], [
            'session_id.required' => 'Please select session.',
            'division_id.required' => 'Please select division.',
            'center_id.required' => 'Please select center.',
            'captcha.numeric' => 'The captcha answer must be a number.',
        ]);
        // Session-backed answer — the original's hidden `captcha_answer`
        // field could be forged, this cannot (fresh problem per form GET).
        if (!CaptchaService::isCorrect($expectedAnswer, $data['captcha'])) {
            throw new ValidationException(['captcha' => ['The captcha answer is incorrect.']]);
        }

        $sessionRow = ResultPortalService::gate((string) $data['session_id']);
        if ($sessionRow === null) {
            if ($request->expectsJson()) {
                // Original searchAjax()/show() JSON contract (keys kept identical).
                Response::json(['error' => 'Scholarship Result -' . $data['session_id']
                    . ' have not been publish yet. Please wait till specified date & time. Thank You.'], 404);
            }
            Session::flash('error', 'Scholarship Result for session ' . $data['session_id']
                . ' has not been published yet. Please wait till the specified date & time.');
            Response::redirect($this->backPath($request));
        }

        $student = ResultPortalService::findStudent($data);
        if ($student === null) {
            if ($request->expectsJson()) {
                Response::json(['error' => 'No student found.Please give correct information.'], 404);
            }
            Session::flash('error', 'No student found. Please provide correct information.');
            Response::redirect($this->backPath($request));
        }

        if ($request->expectsJson()) {
            // Original searchAjax() success payload.
            return ['result' => [
                'center_id' => $data['center_id'],
                'division_id' => $data['division_id'],
                'class' => $data['class'],
                'registration' => $data['registration'],
                'session' => $data['session_id'],
                'roll' => $data['roll'],
            ]];
        }

        // Success: render the result sheet directly (original search() returned the view).
        return View::render('public.result_show', $this->sheet($student, $sessionRow), 'layouts.guest');
    }

    /** GET /result/show?data={json} — deep link to a result sheet. */
    public function show(Request $request): string
    {
        $raw = $request->input('data', '{}');
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($data) ? $data : [];
        $sessionName = (string) ($data['session'] ?? '');

        $sessionRow = ResultPortalService::gate($sessionName);
        if ($sessionRow === null) {
            // Original behavior: JSON 404 with its exact message.
            Response::json(['error' => 'Scholarship Result -' . $sessionName
                . ' have not been publish yet. Please wait till specified date & time. Thank You.'], 404);
        }

        $student = ResultPortalService::findStudent([
            'session_id' => $sessionName,
            'division_id' => (string) ($data['division_id'] ?? ''),
            'center_id' => (string) ($data['center_id'] ?? ''),
            'class' => (string) ($data['class'] ?? ''),
            'registration' => (string) ($data['registration'] ?? ''),
            'roll' => (string) ($data['roll'] ?? ''),
        ]);
        // Original crashed with a 500 here — return a proper 404 instead.
        if ($student === null) {
            throw new HttpException(404, 'No student found.Please give correct information.');
        }

        return View::render('public.result_show', $this->sheet($student, $sessionRow), 'layouts.guest');
    }

    /** Shared view payload for the result sheet (one lookup + cached full marks). */
    private function sheet(array $student, array $sessionRow): array
    {
        return [
            'title' => 'Scholarship Result ' . $student['session'],
            'student' => $student,
            'fullMarks' => ResultPortalService::fullMarks((string) $student['session']),
            'resultTime' => $sessionRow['result_time'],
        ];
    }

    /** Where redirect-back lands (the form) when no Referer is sent. */
    private function backPath(Request $request): string
    {
        $referer = $request->header('Referer');
        return $referer ? (parse_url($referer, PHP_URL_PATH) ?: '/scholarship-result') : '/scholarship-result';
    }
}
