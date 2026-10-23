<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\LoginHistoryService;
use PDOException;

final class LoginHistoryController extends Controller
{
    public function index(Request $request): array
    {
        try {
            return LoginHistoryService::page(
                (int) $request->query('page', 1),
                (int) $request->query('per_page', 25),
                (string) $request->query('search', '')
            );
        } catch (PDOException $e) {
            error_log('[login-history] list failed: ' . $e->getCode());
            return ['error' => 'Login history is not available yet. Apply the pending login history database update, then reload this page.'];
        }
    }
}
