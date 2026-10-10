<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Validator;
use App\Core\View;
use App\Models\Rollplace;
use App\Models\Setting;

/**
 * Settings hub — ported from original SettingController (routes/api.php:
 * Route::apiResource('settings') + GET /api/rollplace/{id}/{sta}).
 *
 * URL + JSON contracts:
 *   GET    /api/settings/{id}     -> {settingById:{...}, session:<activeSession>}
 *   PUT    /api/settings/{id}     -> {status:'success'}
 *   POST   /api/rollplace/{id}/{sta} -> {status:'success'}  (original was GET;
 *          converted because it writes data — original returned an empty body)
 *   Pages  /settings, /instructions, /role-place
 *
 * Original bugs fixed while porting:
 *   - rollplace() ignored its URL params and dispatched a queued job whose
 *     base-roll fallback crashed on NULL (`$one->roll` when no student had a
 *     roll yet). Runs synchronously with a per-class base fallback here.
 *   - update() mass-assigned $request->all(); now validates first.
 */
final class SettingController extends Controller
{
    // ---------------------------------------------------------------
    // API
    // ---------------------------------------------------------------

    /** GET /api/settings/{id} — settings row + active session (instructions page uses it too). */
    public function show(Request $request): array
    {
        $id = (int) $request->params['id'];
        $setting = Setting::find($id);
        if ($setting === null) {
            throw new HttpException(404, 'Setting not found');
        }

        return [
            'settingById' => $setting,
            'session' => Auth::activeSession(),
        ];
    }

    /** PUT /api/settings/{id} — original update() (only editable columns). */
    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (Setting::find($id) === null) {
            throw new HttpException(404, 'Setting not found');
        }

        $existing = Setting::find($id);

        $data = Validator::validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'active' => 'nullable|boolean',
        ]);

        Setting::updateRow($id, [
            'title' => $data['title'],
            'description' => $data['description'],
            // The Vue form only posts title/description — keep the stored flag.
            'active' => isset($data['active']) && $data['active'] !== null
                ? (int) (bool) $data['active']
                : (int) $existing['active'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['status' => 'success'];
    }

    /** GET /api/settings — Laravel apiResource index (paginator). */
    public function index(Request $request): array
    {
        return Setting::query()->orderBy('id', 'desc')->paginate(20, null, '/api/settings');
    }

    /** POST /api/settings — Laravel apiResource store. */
    public function store(Request $request): array
    {
        $data = Validator::validate($request, [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'active' => 'nullable|boolean',
        ]);

        $id = Setting::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'active' => isset($data['active']) && $data['active'] !== null ? (int) (bool) $data['active'] : 1,
            'user_id' => Auth::id() ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'status' => 'success',
            'setting' => Setting::find($id),
        ];
    }

    /** DELETE /api/settings/{id} — Laravel apiResource destroy. */
    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (Setting::find($id) === null) {
            throw new HttpException(404, 'Setting not found');
        }

        Setting::deleteRow($id);

        return ['status' => 'success'];
    }

    /**
     * POST /api/rollplace/{id}/{sta} — assign rolls to every student of the
     * active session that has none, then tag them with this run's place_id.
     * Original: SettingController::rollplace() + App\Jobs\RollPlaceJob.
     *
     * Rolls continue from the highest already assigned per class (or restart
     * at the class base 10000..60000), ordered by center → institute name →
     * registration, exactly like the original job.
     */
    public function rollplace(Request $request): array
    {
        $session = (int) Auth::activeSession();
        $placeId = Rollplace::create([
            'session' => $session,
            'is_placed' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assignRolls($session, $placeId);

        return ['status' => 'success'];
    }

    // ---------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------

    /** GET /settings — active-session switcher + instruction settings form. */
    public function pageIndex(Request $request): string
    {
        return View::render('settings.index', [
            'title' => 'Settings',
        ], 'layouts.dashboard');
    }

    /** GET /instructions — published instructions (original: settings/instructions.vue). */
    public function pageInstructions(Request $request): string
    {
        return View::render('settings.instructions', [
            'title' => 'Instructions',
        ], 'layouts.dashboard');
    }

    /** GET /role-place — roll/seat placement trigger (original: settings/rolePlace.vue). */
    public function pageRolePlace(Request $request): string
    {
        return View::render('settings.rolePlace', [
            'title' => 'Role Place',
        ], 'layouts.dashboard');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Port of App\Jobs\RollPlaceJob::handle() — synchronous, all classes in
     * one pass. Only students whose roll is still NULL get a roll (the
     * original also only updated whereNull('roll')).
     */
    private function assignRolls(int $session, int $placeId): void
    {
        $bases = [1 => 10000, 2 => 20000, 3 => 30000, 4 => 40000, 5 => 50000, 6 => 60000];

        Database::transaction(function () use ($session, $placeId, $bases): void {
            foreach ($bases as $class => $base) {
                // Original: `($one && $one->roll < base) ? base : $one->roll`
                // (fatals when no rolled student exists) — here MAX() over
                // non-null rolls with the class base as the floor.
                $max = (int) (Database::selectOne(
                    'SELECT MAX(`roll`) AS m FROM `students` WHERE `session` = ? AND `class` = ?',
                    [$session, $class]
                )['m'] ?? 0);
                $roll = max($base, $max);

                $pending = Database::select(
                    'SELECT s.`id`
                       FROM `students` s
                       JOIN `institutes` i ON i.`id` = s.`institute_id`
                      WHERE s.`session` = ? AND s.`class` = ? AND s.`roll` IS NULL
                      ORDER BY s.`center_id` ASC, i.`name` ASC, s.`registration` ASC',
                    [$session, $class]
                );

                foreach ($pending as $row) {
                    $roll++;
                    Database::update(
                        'students',
                        ['roll' => $roll, 'place_id' => $placeId],
                        'id = ?',
                        [(int) $row['id']]
                    );
                }
            }
        });
    }
}
