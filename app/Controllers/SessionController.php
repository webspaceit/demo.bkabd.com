<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Validator;
use App\Core\ValidationException;
use App\Core\View;
use App\Models\ExamSession;

/**
 * Exam session management — ported from original SessionController.
 *
 * URL + JSON contracts identical to original routes/api.php (apiResource 'sessions'):
 *   GET    /api/sessions       -> raw Laravel paginator JSON (search on `session`)
 *   POST   /api/sessions       -> {status:'success'} (activates the new session)
 *   GET    /api/sessions/{id}  -> {sessionById:{...}}
 *   PUT    /api/sessions/{id}  -> {status:'success'}
 *   DELETE /api/sessions/{id}  -> {status:'success'}
 *   GET    /api/recentSession  -> {recentSession: <year>}
 *
 * The original had every session permission middleware commented out (and the
 * seed never granted session-* to Super Admin — ids 79-82 were skipped).
 * Here the routes are gated with the exact permission names from the task:
 * session-list / session-create / session-edit / session-delete.
 */
final class SessionController extends Controller
{
    /** GET /api/sessions — paginated list (original returned the paginator object verbatim). */
    public function index(Request $request): array
    {
        $q = ExamSession::query()->orderBy('id', 'desc');

        $search = $request->input('search');
        if ($search !== null && $search !== '') {
            $q->where('session', 'LIKE', '%' . $search . '%');
        }

        return $q->paginate((int) $request->input('per_page', 20), null, '/api/sessions');
    }

    /** POST /api/sessions — create, then make it the active session (original store()). */
    public function store(Request $request): array
    {
        $data = $this->validated($request);

        ExamSession::create([
            'session' => (int) $data['session'],
            'active' => 0,
            'result' => (int) $data['result'],
            'result_time' => $data['result_time'],
            'user_id' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        ExamSession::query()->where('active', 1)->update(['active' => 0]);
        ExamSession::query()->where('session', (int) $data['session'])->update(['active' => 1]);
        Cache::forgetPrefix('latest_session'); // recentSession() memo is stale now

        return ['status' => 'success'];
    }

    /** GET /api/sessions/{id} — original show()/edit() both returned {sessionById:...}. */
    public function show(Request $request): array
    {
        $row = ExamSession::find((int) $request->params['id']);
        if ($row === null) {
            throw new HttpException(404, 'Session not found');
        }

        return ['sessionById' => $row];
    }

    /** PUT /api/sessions/{id} — original update() (does not touch the active flag). */
    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        $existing = ExamSession::find($id);
        if ($existing === null) {
            throw new HttpException(404, 'Session not found');
        }

        $data = $this->validated($request);

        ExamSession::updateRow($id, [
            'session' => (int) $data['session'],
            'result' => (int) $data['result'],
            'result_time' => $data['result_time'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['status' => 'success'];
    }

    /** DELETE /api/sessions/{id} — original destroy() (404 instead of fatal on null). */
    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        $existing = ExamSession::find($id);
        if ($existing === null) {
            throw new HttpException(404, 'Session not found');
        }

        ExamSession::deleteRow($id);
        Cache::forgetPrefix('latest_session');

        return ['status' => 'success'];
    }

    /**
     * GET /api/recentSession — latest session year regardless of admin override.
     * (Named recentSessionApi: the base Controller already owns recentSession().)
     */
    public function recentSessionApi(Request $request): array
    {
        return ['recentSession' => $this->recentSession()];
    }

    // ---------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------

    /** GET /sessions — list page. */
    public function pageIndex(Request $request): string
    {
        return View::render('sessions.index', [
            'title' => 'Sessions',
        ], 'layouts.dashboard');
    }

    /** GET /createSession — create form (original: resources/js/components/sessions/create.vue). */
    public function pageCreate(Request $request): string
    {
        return View::render('sessions.create', [
            'title' => 'Add Session',
        ], 'layouts.dashboard');
    }

    /** GET /editSession/{id} — edit form, data loads from GET /api/sessions/{id}. */
    public function pageEdit(Request $request): string
    {
        $id = (int) $request->params['id'];
        if (ExamSession::find($id) === null) {
            throw new HttpException(404, 'Session not found');
        }

        return View::render('sessions.edit', [
            'title' => 'Edit Session',
            'sessionId' => $id,
        ], 'layouts.dashboard');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Original rules: session/result/result_time required. Added integer checks
     * so garbage cannot reach MySQL strict mode (blueprint hard rule 7).
     * result_time accepts both storage formats and the datetime-local format
     * the Vue form posts ('YYYY-MM-DDTHH:mm').
     *
     * @return array{session:int,result:int,result_time:string}
     */
    private function validated(Request $request): array
    {
        $data = Validator::validate($request, [
            'session' => 'required|integer',
            'result' => 'required|integer',
            'result_time' => 'required',
        ]);

        $normalized = str_replace('T', ' ', trim((string) $data['result_time']));
        $fmt = strlen($normalized) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
        $dt = \DateTime::createFromFormat($fmt, $normalized);
        if ($dt === false || $dt->format($fmt) !== $normalized) {
            throw new ValidationException([
                'result_time' => ['The result time does not match the format YYYY-MM-DD HH:MM.'],
            ]);
        }

        return [
            'session' => (int) $data['session'],
            'result' => (int) $data['result'],
            'result_time' => $normalized,
        ];
    }
}
