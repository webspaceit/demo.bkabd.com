<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Validator;
use App\Models\District;
use App\Models\Division;
use App\Models\Upazila;

/**
 * Divisions resource + geo cascade lookups (original DivisionController).
 *
 *   GET    /api/divisionList        -> {divisionList:[...]}
 *   POST   /api/divisionSave        -> {status:'success'}
 *   GET    /api/divisionById/{id}   -> {divisionById:{...}}
 *   POST   /api/divisionUpdate/{id} -> {status:'success'}
 *   DELETE /api/divisionDelete/{id} -> {status:'success'}   (original: GET — converted)
 *   GET    /api/districtById/{id}   -> {districtById:[...]}  (districts of a division)
 *   GET    /api/upazilaById/{id}    -> {upazilaById:[...]}   (upazilas of a district)
 */
class DivisionController extends Controller
{
    public function index(Request $request): array
    {
        $divisionList = Division::query()->orderBy('name', 'asc')->get();
        return ['divisionList' => $divisionList];
    }

    public function store(Request $request): array
    {
        $data = Validator::validate($request, [
            'name' => 'required|string|max:255|unique:divisions,name',
        ]);

        Division::create([
            'name' => $data['name'],
            'user_id' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['status' => 'success'];
    }

    public function edit(Request $request): array
    {
        return ['divisionById' => Division::findOrFail($request->params['id'])];
    }

    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        Division::findOrFail($id);

        $data = Validator::validate($request, [
            'name' => 'required|string|max:255|unique:divisions,name,' . $id,
        ]);

        Division::updateRow($id, [
            'name' => $data['name'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['status' => 'success'];
    }

    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        Division::findOrFail($id);
        Division::deleteRow($id);
        return ['status' => 'success'];
    }

    /** Districts of a division (cascading dropdown, ordered by name). */
    public function districtById(Request $request): array
    {
        $districtById = District::query()
            ->where('division_id', (int) $request->params['id'])
            ->orderBy('name', 'asc')
            ->get();
        return ['districtById' => $districtById];
    }

    /** Upazilas of a district (cascading dropdown, ordered by name). */
    public function upazilaById(Request $request): array
    {
        $upazilaById = Upazila::query()
            ->where('district_id', (int) $request->params['id'])
            ->orderBy('name', 'asc')
            ->get();
        return ['upazilaById' => $upazilaById];
    }
}
