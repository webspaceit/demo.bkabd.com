<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\DatabaseSyncService;
use PDOException;
use RuntimeException;

final class DatabaseSyncController extends Controller
{
    public function status(): array
    {
        return (new DatabaseSyncService())->readiness();
    }

    public function compare(): array
    {
        try {
            return (new DatabaseSyncService())->compare();
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] compare failed: ' . $e->getCode());
            return ['error' => 'Could not connect to the read-only exam database. Check the server connection settings and read-only account.'];
        }
    }

    public function apply(Request $request): array
    {
        $host = strtolower((string) preg_replace('/:\\d+$/', '', (string) $request->header('Host', '')));
        if ($host !== 'demo.bkabd.com') {
            return ['error' => 'Migrations can only be applied from the demo.bkabd.com dashboard.'];
        }
        try {
            return (new DatabaseSyncService())->applyPending();
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] apply failed: ' . $e->getCode());
            return ['error' => 'A demo schema migration failed. Check the demo database and application error log before retrying.'];
        }
    }
}
