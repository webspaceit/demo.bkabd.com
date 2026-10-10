<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Query;
use App\Core\RateLimiter;
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

        // RLS/BOLA: center users may only pull numbers for their own center
        // (id=0 "every center" is admin-only — a center user's assert throws 403).
        Auth::assertCenterAccess($centerId);

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

        // RLS/BOLA: the center must be the caller's own (id=0 for "whole
        // center" is scoped by the center_id filter below; admins pass through).
        Auth::assertCenterAccess($centerId);

        if ($instituteId === 0) {
            $rows = (new Query('institutes'))
                ->select('phone')
                ->where('center_id', $centerId)
                ->get();
            $numbers = array_filter(array_column($rows, 'phone'), static fn($p) => trim((string) $p) !== '');

            return ['numbers' => implode(',', $numbers)];
        }

        // Institute must exist AND belong to the given center (kills the IDOR
        // where any institute's number could be read by guessing its id).
        $row = (new Query('institutes'))
            ->select('phone')
            ->where('id', $instituteId)
            ->where('center_id', $centerId)
            ->first();
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

        // Rate limiting: a per-user daily cap (SMS blast abuse) and a per-recipient
        // daily cap (number pumping). Legit bulk use stays well inside these.
        $userId = (int) Auth::id();
        RateLimiter::hit('sms:user:' . $userId, 200, 86400);
        RateLimiter::hit('sms:phone:' . $data['phone'], 50, 86400);

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
