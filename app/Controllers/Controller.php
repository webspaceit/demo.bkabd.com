<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;

/**
 * Base controller — mirrors the original Laravel BaseController +
 * Controller helpers (activeSession / isAdmin / recentSession).
 */
abstract class Controller
{
    /** Exam session in effect (admin override via ?session / updateSession). */
    protected function activeSession(): string|int
    {
        return Auth::activeSession();
    }

    protected function isAdmin(): bool
    {
        return Auth::isAdmin();
    }

    /** Latest session regardless of admin override. */
    protected function recentSession(): string|int
    {
        return Auth::recentSession();
    }

    /** Success envelope: {success:true, data:..., message:...} (HTTP 200). */
    protected function sendResponse($result, $message)
    {
        return [
            'success' => true,
            'data' => $result,
            'message' => $message,
        ];
    }

    /**
     * Error envelope: {success:false, message:...[, data:errors]}.
     * Emits JSON immediately (original returned response()->json with $code).
     */
    protected function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];
        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }
        Response::json($response, $code);
    }
}
