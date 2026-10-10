<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Validator;
use App\Models\District;
use App\Models\Division;
use App\Models\Upazila;

/**
 * Upazilas apiResource (original UpazilaController).
 *
 *   GET    /api/upazilas        -> raw Laravel paginator JSON (data rows carry nested `district`)
 *                                  supports ?page= &per_page= &search= (name or district name)
 *   POST   /api/upazilas        -> {status:'success'}
 *   GET    /api/upazilas/{id}   -> {upazilaById, districts, district, division}
 *   PUT    /api/upazilas/{id}   -> {status:'success'}
 *   DELETE /api/upazilas/{id}   -> {status:'success'}
 */
class UpazilaController extends Controller
{
    public function index(Request $request): array
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(200, $perPage ?: 20));
        $search = trim((string) $request->query('search', ''));

        $query = Upazila::query()
            ->select(
                'upozilas.*',
                'districts.id AS parent_id',
                'districts.name AS parent_name',
                'districts.division_id AS parent_division_id',
                'districts.user_id AS parent_user_id',
                'districts.created_at AS parent_created_at',
                'districts.updated_at AS parent_updated_at'
            )
            ->leftJoin('districts', 'districts.id', '=', 'upozilas.district_id');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->whereRaw('(`upozilas`.`name` LIKE ? OR `districts`.`name` LIKE ?)', [$like, $like]);
        }

        $pager = $query->orderByRaw('`upozilas`.`name` ASC')->paginate($perPage);

        $pager['data'] = array_map(function (array $row): array {
            return self::attachDistrict($row);
        }, $pager['data']);
        return $pager;
    }

    public function store(Request $request): array
    {
        $data = Validator::validate($request, [
            'name' => 'required|string|max:255',
            'district_id' => 'required|integer|exists:districts,id',
        ]);

        $now = date('Y-m-d H:i:s');
        // `upozilas.id` has no AUTO_INCREMENT in this schema — allocate it from MAX(id).
        Database::transaction(function () use ($data, $now) {
            $max = (int) (Database::selectOne('SELECT COALESCE(MAX(`id`), 0) AS m FROM `upozilas`')['m'] ?? 0);
            Database::insert('upozilas', [
                'id' => $max + 1,
                'name' => $data['name'],
                'district_id' => (int) $data['district_id'],
                'user_id' => Auth::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
        return ['status' => 'success'];
    }

    public function show(Request $request): array
    {
        $upazila = Upazila::findOrFail($request->params['id']);
        $district = District::findOrFail($upazila['district_id']);
        $division = Division::findOrFail($district['division_id']);
        $districts = District::query()
            ->where('division_id', (int) $district['division_id'])
            ->orderBy('name', 'asc')
            ->get();

        return [
            'upazilaById' => $upazila,
            'districts' => $districts,
            'district' => $district,
            'division' => $division,
        ];
    }

    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        Upazila::findOrFail($id);

        $data = Validator::validate($request, [
            'name' => 'required|string|max:255',
            'district_id' => 'required|integer|exists:districts,id',
        ]);

        Upazila::updateRow($id, [
            'name' => $data['name'],
            'district_id' => (int) $data['district_id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['status' => 'success'];
    }

    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        Upazila::findOrFail($id);
        Upazila::deleteRow($id);
        return ['status' => 'success'];
    }

    /** Wrap the joined district columns into the `district` object Eloquent serializes. */
    private static function attachDistrict(array $row): array
    {
        $district = $row['parent_id'] === null ? null : [
            'id' => $row['parent_id'],
            'name' => $row['parent_name'],
            'division_id' => $row['parent_division_id'],
            'user_id' => $row['parent_user_id'],
            'created_at' => $row['parent_created_at'],
            'updated_at' => $row['parent_updated_at'],
        ];
        foreach (['parent_id', 'parent_name', 'parent_division_id', 'parent_user_id', 'parent_created_at', 'parent_updated_at'] as $col) {
            unset($row[$col]);
        }
        $row['district'] = $district;
        return $row;
    }
}
