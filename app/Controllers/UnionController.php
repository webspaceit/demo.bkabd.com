<?php

namespace App\Controllers;

use App\Core\Request;
use App\Models\Union;

/**
 * Unions of an upazila.
 *
 *   GET /api/unionById/{id} -> {unionById:[...]}
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
}
