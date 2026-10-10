<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Validator;
use App\Core\View;
use App\Models\Routine;

/**
 * Exam routine — ported from original RoutineController.
 *
 * URL + JSON contracts identical to original routes/api.php (apiResource 'routines'):
 *   GET    /api/routines       -> raw Laravel paginator JSON (search on subject)
 *   POST   /api/routines       -> {status:'success'}
 *   GET    /api/routines/{id}  -> {routineById:{...}}
 *   PUT    /api/routines/{id}  -> {status:'success'}
 *   DELETE /api/routines/{id}  -> {status:'success'}
 *
 * Gated with the exact permission names: routine-list / routine-create /
 * routine-edit / routine-delete (the original constructor middleware).
 */
final class RoutineController extends Controller
{
    /** GET /api/routines — paginated list (original returned the paginator object verbatim). */
    public function index(Request $request): array
    {
        $q = Routine::query()->orderBy('id', 'desc');

        $search = $request->input('search');
        if ($search !== null && $search !== '') {
            $q->where('subject', 'LIKE', '%' . $search . '%');
        }

        return $q->paginate((int) $request->input('per_page', 20), null, '/api/routines');
    }

    /** POST /api/routines — original store() (subject/date/order required). */
    public function store(Request $request): array
    {
        $data = $this->validated($request);

        Routine::create([
            'subject' => (string) $data['subject'],
            'date' => (string) $data['date'],
            'day' => (string) $request->input('day', ''),
            'time' => (string) $request->input('time', ''),
            'class' => (string) $request->input('class', ''),
            'order' => (int) $data['order'],
            'active' => 1,
            'user_id' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['status' => 'success'];
    }

    /** GET /api/routines/{id} — original show()/edit() both returned {routineById:...}. */
    public function show(Request $request): array
    {
        $row = Routine::find((int) $request->params['id']);
        if ($row === null) {
            throw new HttpException(404, 'Routine not found');
        }

        return ['routineById' => $row];
    }

    /** PUT /api/routines/{id} — original update() (only sent fields are rewritten). */
    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        $existing = Routine::find($id);
        if ($existing === null) {
            throw new HttpException(404, 'Routine not found');
        }

        $data = $this->validated($request);

        $row = [
            'subject' => (string) $data['subject'],
            'date' => (string) $data['date'],
            'order' => (int) $data['order'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        foreach (['day', 'time', 'class'] as $col) {
            if ($request->has($col)) {
                $row[$col] = (string) $request->input($col);
            }
        }

        Routine::updateRow($id, $row);

        return ['status' => 'success'];
    }

    /** DELETE /api/routines/{id} — original destroy() (404 instead of fatal on null). */
    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (Routine::find($id) === null) {
            throw new HttpException(404, 'Routine not found');
        }

        Routine::deleteRow($id);

        return ['status' => 'success'];
    }

    // ---------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------

    /** GET /routines — list page. */
    public function pageIndex(Request $request): string
    {
        return View::render('routines.index', [
            'title' => 'Routines',
        ], 'layouts.dashboard');
    }

    /** GET /createRoutine — create form (original: resources/js/components/routines/create.vue). */
    public function pageCreate(Request $request): string
    {
        return View::render('routines.create', [
            'title' => 'Add Routine',
        ], 'layouts.dashboard');
    }

    /** GET /editRoutine/{id} — edit form, data loads from GET /api/routines/{id}. */
    public function pageEdit(Request $request): string
    {
        $id = (int) $request->params['id'];
        if (Routine::find($id) === null) {
            throw new HttpException(404, 'Routine not found');
        }

        return View::render('routines.edit', [
            'title' => 'Edit Routine',
            'routineId' => $id,
        ], 'layouts.dashboard');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Original rules: subject/date/order required. Added string length limits
     * and an integer check for `order` so garbage cannot reach MySQL strict
     * mode (blueprint hard rule 7).
     *
     * @return array{subject:string,date:string,order:int}
     */
    private function validated(Request $request): array
    {
        $data = Validator::validate($request, [
            'subject' => 'required|string|max:255',
            'date' => 'required|string|max:255',
            'order' => 'required|integer',
        ]);

        return [
            'subject' => (string) $data['subject'],
            'date' => (string) $data['date'],
            'order' => (int) $data['order'],
        ];
    }
}
