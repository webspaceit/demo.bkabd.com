<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Validator;
use App\Models\District;

/**
 * Districts apiResource (original DistrictController).
 *
 *   GET    /api/districts           -> {districtList:[...]}  (full list with nested division, as original)
 *   POST   /api/districts           -> {status:'success'}
 *   GET    /api/districts/{id}      -> {districtById:{...}}
 *   PUT    /api/districts/{id}      -> {status:'success'}
 *   DELETE /api/districts/{id}      -> {status:'success'}
 *   POST   /api/districtUpdate/{id} -> {status:'success'}  (original separate update route)
 *   GET    /api/districtById2/{id}  -> {districtById:{...}} (original edit route)
 */
class DistrictController extends Controller
{
    /** Original: District::with('division')->orderby('name','asc')->get() */
    public function index(Request $request): array
    {
        $rows = District::query()
            ->select(
                'districts.*',
                'divisions.id AS parent_id',
                'divisions.name AS parent_name',
                'divisions.user_id AS parent_user_id',
                'divisions.created_at AS parent_created_at',
                'divisions.updated_at AS parent_updated_at'
            )
            ->leftJoin('divisions', 'divisions.id', '=', 'districts.division_id')
            ->orderByRaw('`districts`.`name` ASC')
            ->get();

        $districtList = [];
        foreach ($rows as $row) {
            $districtList[] = self::attachDivision($row);
        }
        return ['districtList' => $districtList];
    }

    public function store(Request $request): array
    {
        $data = Validator::validate($request, [
            'name' => 'required|string|max:255|unique:districts,name',
            'division_id' => 'required|integer|exists:divisions,id',
        ]);

        District::create([
            'name' => $data['name'],
            'division_id' => (int) $data['division_id'],
            'user_id' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['status' => 'success'];
    }

    public function show(Request $request): array
    {
        return ['districtById' => District::findOrFail($request->params['id'])];
    }

    /** Original edit route (/api/districtById2/{id}). */
    public function edit(Request $request): array
    {
        return ['districtById' => District::findOrFail($request->params['id'])];
    }

    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        District::findOrFail($id);

        $data = Validator::validate($request, [
            'name' => 'required|string|max:255|unique:districts,name,' . $id,
            'division_id' => 'required|integer|exists:divisions,id',
        ]);

        District::updateRow($id, [
            'name' => $data['name'],
            'division_id' => (int) $data['division_id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['status' => 'success'];
    }

    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        District::findOrFail($id);
        District::deleteRow($id);
        return ['status' => 'success'];
    }

    /** Wrap the joined division columns into the `division` object Eloquent serializes. */
    private static function attachDivision(array $row): array
    {
        $division = $row['parent_id'] === null ? null : [
            'id' => $row['parent_id'],
            'name' => $row['parent_name'],
            'user_id' => $row['parent_user_id'],
            'created_at' => $row['parent_created_at'],
            'updated_at' => $row['parent_updated_at'],
        ];
        foreach (['parent_id', 'parent_name', 'parent_user_id', 'parent_created_at', 'parent_updated_at'] as $col) {
            unset($row[$col]);
        }
        $row['division'] = $division;
        return $row;
    }
}
