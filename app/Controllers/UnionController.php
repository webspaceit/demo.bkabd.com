<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\District;
use App\Models\Division;
use App\Models\Union;
use App\Models\Upazila;

/**
 * Unions of an upazila.
 *
 * Cascade lookup (used by the registration form):
 *   GET /api/unionById/{id} -> {unionById:[...]}
 *
 * Manage Area resource (Union Info page, mirrors the upazila resource):
 *   GET    /api/unions        -> raw paginator JSON (data rows carry nested
 *                                `upozila` and `district`)
 *                                supports ?page= &per_page= &search=
 *   POST   /api/unions        -> {status:'success'}
 *   GET    /api/unions/{id}   -> {unionById, upozila, district, division, districts, upozilas}
 *   PUT    /api/unions/{id}   -> {status:'success'}
 *   DELETE /api/unions/{id}   -> {status:'success'}
 *
 * Original bug fixed: DivisionController::unionById queried the *upozilas*
 * table with a wrong column — it must select from `unions` (column `upozila_id`).
 */
class UnionController extends Controller
{
    public function unionById(Request $request): array
    {
        $unionById = Union::query()
            ->where('upozila_id', (int) $request->params['id'])
            ->orderBy('name', 'asc')
            ->get();
        return ['unionById' => $unionById];
    }

    public function index(Request $request): array
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(200, $perPage ?: 20));
        $search = trim((string) $request->query('search', ''));

        $query = Union::query()
            ->select(
                'unions.*',
                'upozilas.id AS parent_id',
                'upozilas.name AS parent_name',
                'upozilas.district_id AS parent_district_id',
                'districts.id AS grand_id',
                'districts.name AS grand_name',
                'districts.division_id AS grand_division_id'
            )
            ->leftJoin('upozilas', 'upozilas.id', '=', 'unions.upozila_id')
            ->leftJoin('districts', 'districts.id', '=', 'upozilas.district_id');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->whereRaw(
                '(`unions`.`name` LIKE ? OR `upozilas`.`name` LIKE ? OR `districts`.`name` LIKE ?)',
                [$like, $like, $like]
            );
        }

        $pager = $query->orderByRaw('`unions`.`name` ASC')->paginate($perPage);

        $pager['data'] = array_map([self::class, 'attachParents'], $pager['data']);
        return $pager;
    }

    public function store(Request $request): array
    {
        $data = Validator::validate($request, [
            'name' => 'required|string|max:255',
            'upozila_id' => 'required|integer|exists:upozilas,id',
        ]);

        $this->assertUniqueName((string) $data['name'], (int) $data['upozila_id'], null);

        $now = date('Y-m-d H:i:s');
        Union::create([
            'name' => $data['name'],
            'upozila_id' => (int) $data['upozila_id'],
            'user_id' => Auth::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return ['status' => 'success'];
    }

    public function show(Request $request): array
    {
        $union = Union::findOrFail($request->params['id']);
        $upozila = Upazila::findOrFail($union['upozila_id']);
        $district = District::findOrFail($upozila['district_id']);
        $division = Division::findOrFail($district['division_id']);

        $districts = District::query()
            ->where('division_id', (int) $division['id'])
            ->orderBy('name', 'asc')
            ->get();
        $upozilas = Upazila::query()
            ->where('district_id', (int) $district['id'])
            ->orderBy('name', 'asc')
            ->get();

        return [
            'unionById' => $union,
            'upozila' => $upozila,
            'district' => $district,
            'division' => $division,
            'districts' => $districts,
            'upozilas' => $upozilas,
        ];
    }

    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        Union::findOrFail($id);

        $data = Validator::validate($request, [
            'name' => 'required|string|max:255',
            'upozila_id' => 'required|integer|exists:upozilas,id',
        ]);

        $this->assertUniqueName((string) $data['name'], (int) $data['upozila_id'], $id);

        Union::updateRow($id, [
            'name' => $data['name'],
            'upozila_id' => (int) $data['upozila_id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['status' => 'success'];
    }

    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        Union::findOrFail($id);
        Union::deleteRow($id);
        return ['status' => 'success'];
    }

    /**
     * The unions table is UNIQUE(upozila_id, name); the generic Validator can
     * only express a single-column unique, so enforce the composite here.
     */
    private function assertUniqueName(string $name, int $upozilaId, ?int $ignoreId): void
    {
        $sql = 'SELECT COUNT(*) c FROM `unions` WHERE `name` = ? AND `upozila_id` = ?';
        $args = [$name, $upozilaId];
        if ($ignoreId !== null) {
            $sql .= ' AND `id` != ?';
            $args[] = $ignoreId;
        }
        $count = (int) (Database::selectOne($sql, $args)['c'] ?? 0);
        if ($count > 0) {
            throw new ValidationException([
                'name' => ['This union name already exists in the selected upazila.'],
            ]);
        }
    }

    /** Wrap the joined upozila/district columns into nested objects. */
    private static function attachParents(array $row): array
    {
        $upozila = $row['parent_id'] === null ? null : [
            'id' => $row['parent_id'],
            'name' => $row['parent_name'],
            'district_id' => $row['parent_district_id'],
        ];
        $district = $row['grand_id'] === null ? null : [
            'id' => $row['grand_id'],
            'name' => $row['grand_name'],
            'division_id' => $row['grand_division_id'],
        ];
        foreach (['parent_id', 'parent_name', 'parent_district_id', 'grand_id', 'grand_name', 'grand_division_id'] as $col) {
            unset($row[$col]);
        }
        $row['upozila'] = $upozila;
        $row['district'] = $district;
        return $row;
    }
}
