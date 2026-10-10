<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Query;
use App\Core\Request;
use App\Core\Validator;
use App\Core\View;
use App\Models\Notice;

/**
 * Notice board — ported from original NoticeController.
 *
 * URL + JSON contracts identical to original routes/api.php (apiResource 'notices'):
 *   GET    /api/notices       -> {noticeList:[{id,title,description,center_id,center:{...,user:{...}}}]}
 *   POST   /api/notices       -> {status:'success'} (firstOrCreate semantics kept)
 *   GET    /api/notices/{id}  -> {noticeById:{...}}
 *   PUT    /api/notices/{id}  -> {status:'success'}
 *   DELETE /api/notices/{id}  -> {status:'success'}
 *   GET    /api/noticeById    -> {noticeById:[...]} (own-center + global notices)
 *
 * Fixes vs original: edit() had dd('oo') and an undefined variable (the Vue edit
 * page actually calls show() — ported as GET /api/notices/{id}); store() validated
 * nothing (empty center_id would blow up MySQL strict mode) — now validated like
 * the error slots in create.vue already expected; password/remember_token are
 * hidden inside center.user exactly like the original User::$hidden did.
 */
final class NoticeController extends Controller
{
    /** GET /api/notices — list with center.user eager-loaded (original: with('center.user')). */
    public function index(Request $request): array
    {
        $notices = (new Query('notices'))
            ->select('id', 'title', 'description', 'center_id')
            ->orderBy('id', 'desc')
            ->get();

        // Eager load center + user in two queries (no N+1).
        $centerIds = array_values(array_unique(array_filter(array_column($notices, 'center_id'), fn($v) => (int) $v > 0)));
        $centers = $centerIds !== []
            ? (new Query('centers'))->whereIn('id', $centerIds)->get()
            : [];
        $centersById = array_column($centers, null, 'id');

        $userIds = array_values(array_unique(array_filter(array_column($centers, 'user_id'), fn($v) => (int) $v > 0)));
        $users = $userIds !== []
            ? (new Query('users'))
                ->select('id', 'name', 'email', 'phone', 'type', 'created_at', 'updated_at')
                ->whereIn('id', $userIds)
                ->get()
            : [];
        $usersById = array_column($users, null, 'id');

        foreach ($notices as &$notice) {
            $centerId = (int) $notice['center_id'];
            $center = $centersById[$centerId] ?? null;
            if ($center !== null) {
                $uid = (int) ($center['user_id'] ?? 0);
                $center['user'] = $usersById[$uid] ?? null;
            }
            $notice['center'] = $center;
        }
        unset($notice);

        return ['noticeList' => $notices];
    }

    /** POST /api/notices — original store() used firstOrCreate (identical rows are not duplicated). */
    public function store(Request $request): array
    {
        $data = Validator::validate($request, [
            'center_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $row = [
            'title' => (string) $data['title'],
            'description' => (string) ($data['description'] ?? ''),
            'center_id' => (int) $data['center_id'],
            'user_id' => Auth::id(),
        ];

        $exists = (new Query('notices'))
            ->where('title', $row['title'])
            ->where('description', $row['description'])
            ->where('center_id', $row['center_id'])
            ->where('user_id', $row['user_id'])
            ->exists();

        if (!$exists) {
            Notice::create($row + [
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['status' => 'success'];
    }

    /** GET /api/notices/{id} — original show() (edit.vue fills the form from it). */
    public function show(Request $request): array
    {
        $row = Notice::find((int) $request->params['id']);
        if ($row === null) {
            throw new HttpException(404, 'Notice not found');
        }

        return ['noticeById' => $row];
    }

    /** PUT /api/notices/{id} — original update() (also re-stamps user_id). */
    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (Notice::find($id) === null) {
            throw new HttpException(404, 'Notice not found');
        }

        $data = Validator::validate($request, [
            'center_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Notice::updateRow($id, [
            'title' => (string) $data['title'],
            'description' => (string) ($data['description'] ?? ''),
            'center_id' => (int) $data['center_id'],
            'user_id' => Auth::id(),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['status' => 'success'];
    }

    /** DELETE /api/notices/{id} — original destroy() (404 instead of fatal on null). */
    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (Notice::find($id) === null) {
            throw new HttpException(404, 'Notice not found');
        }

        Notice::deleteRow($id);

        return ['status' => 'success'];
    }

    /**
     * GET /api/noticeById — dashboard feed: notices of the signed-in center's
     * user plus every global notice (center_id = 0). Original query kept as-is;
     * the combined condition goes through whereRaw because the builder backticks
     * whole column names ('users.id' would become the unknown column `users.id`).
     */
    public function noticeById(Request $request): array
    {
        $rows = (new Query('notices'))
            ->leftJoin('centers', 'centers.id', '=', 'notices.center_id')
            ->leftJoin('users', 'centers.user_id', '=', 'users.id')
            ->select('notices.*')
            ->whereRaw('(users.id = ? OR notices.center_id = 0)', [Auth::id()])
            ->get();

        return ['noticeById' => $rows];
    }

    // ---------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------

    /** GET /notices — list page. */
    public function pageIndex(Request $request): string
    {
        return View::render('notices.index', [
            'title' => 'Notices',
        ], 'layouts.dashboard');
    }

    /** GET /create-notice — create form (original: resources/js/components/notices/create.vue). */
    public function pageCreate(Request $request): string
    {
        return View::render('notices.create', [
            'title' => 'Add Notice',
        ], 'layouts.dashboard');
    }

    /** GET /editnotice/{id} — edit form, data loads from GET /api/notices/{id}. */
    public function pageEdit(Request $request): string
    {
        $id = (int) $request->params['id'];
        if (Notice::find($id) === null) {
            throw new HttpException(404, 'Notice not found');
        }

        return View::render('notices.edit', [
            'title' => 'Edit Notice',
            'noticeId' => $id,
        ], 'layouts.dashboard');
    }
}
