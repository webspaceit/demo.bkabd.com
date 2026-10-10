<?php

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Query;
use App\Core\Request;
use App\Core\SmsService;
use App\Core\Validator;
use App\Core\View;

/**
 * SMS dashboard — ported from original SmsController (routes/api.php).
 *
 * URL + JSON contracts:
 *   GET  /api/getNumberCenter/{id}              -> {numbers:"017...,018..."}
 *   GET  /api/getNumberInstitute/{id}/{institute} -> {numbers:"..."}
 *   POST /api/smsSave                           -> {status:'success'}
 *   Page /sms
 *
 * Original bugs fixed while porting:
 *   - getNumberCenter()/getNumberInstitute() fataled on unknown id
 *     (`->first()->phone`) and returned null for missing phones.
 *   - The Vue page labelled type 1 "Non-Masking" / type 2 "Masking" while
 *     SmsSend does the opposite (type 1 -> mask sender "BKABD"). The ported
 *     page shows the correct labels; the API contract (1|2) is unchanged.
 *
 * The gateway key never appears here — SmsService reads it from .env.
 */
final class SmsController extends Controller
{
    /** GET /api/getNumberCenter/{id} — center contact number(s); id=0 → every center. */
    public function getNumberCenter(Request $request): array
    {
        $centerId = (int) $request->params['id'];

        $q = (new Query('users'))
            ->join('centers', 'centers.user_id', '=', 'users.id')
            ->select('users.phone');

        if ($centerId !== 0) {
            $row = $q->where('centers.id', $centerId)->first();
            if ($row === null) {
                throw new HttpException(404, 'Center not found');
            }
            return ['numbers' => (string) ($row['phone'] ?? '')];
        }

        $numbers = array_filter(array_column($q->get(), 'phone'), static fn($p) => trim((string) $p) !== '');

        return ['numbers' => implode(',', $numbers)];
    }

    /** GET /api/getNumberInstitute/{id}/{institute} — institute number(s); institute=0 → whole center. */
    public function getNumberInstitute(Request $request): array
    {
        $centerId = (int) $request->params['id'];
        $instituteId = (int) $request->params['institute'];

        if ($instituteId === 0) {
            $rows = (new Query('institutes'))
                ->select('phone')
                ->where('center_id', $centerId)
                ->get();
            $numbers = array_filter(array_column($rows, 'phone'), static fn($p) => trim((string) $p) !== '');

            return ['numbers' => implode(',', $numbers)];
        }

        $row = (new Query('institutes'))->select('phone')->where('id', $instituteId)->first();
        if ($row === null) {
            throw new HttpException(404, 'Institute not found');
        }

        return ['numbers' => (string) ($row['phone'] ?? '')];
    }

    /** POST /api/smsSave — dispatch through SmsService (dry-run aware). */
    public function store(Request $request): array
    {
        $data = Validator::validate($request, [
            'message' => 'required|string',
            'phone' => 'required|string',
            'type' => 'required|in:1,2',
        ]);

        // type 1 -> masking sender "BKABD", type 2 -> numeric sender (original SmsSend).
        SmsService::send((string) $data['phone'], (string) $data['message'], (string) $data['type'] === '1');

        return ['status' => 'success'];
    }

    /** GET /sms — SMS dashboard page. */
    public function pageIndex(Request $request): string
    {
        return View::render('sms.index', [
            'title' => 'SMS Dashboard',
        ], 'layouts.dashboard');
    }
}
