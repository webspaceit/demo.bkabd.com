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
        try {
            return (new DatabaseSyncService())->readiness();
        } catch (PDOException $e) {
            error_log('[database-sync] status failed: ' . $e->getCode());
            return ['ready' => false, 'missing' => ['Could not connect to the configured demo database; check host, database, and target account.'], 'source' => [], 'target' => [], 'pending_migrations' => []];
        }
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

    public function latestStudents(Request $request): array
    {
        $limit = (int) $request->query('limit', 20);
        try {
            return ['students' => (new DatabaseSyncService())->latestStudents($limit)];
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] latest students failed: ' . $e->getCode());
            return ['error' => 'Could not load recent exam registrations. The exam account needs read-only SELECT access to students, centers, and institutes.'];
        }
    }

    public function previewStudentImport(): array
    {
        try {
            return (new DatabaseSyncService())->previewStudentImport();
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] student import preview failed: ' . $e->getCode());
            return ['error' => 'Could not compare exam and demo registrations. Check read access on exam and read access on demo.'];
        }
    }

    public function previewCenterNameSync(): array
    {
        try {
            return (new DatabaseSyncService())->previewCenterNameSync();
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] center-name preview failed: ' . $e->getCode());
            return ['error' => 'Could not compare center names. Check read access to centers and users on both databases.'];
        }
    }

    public function syncCenterNames(Request $request): array
    {
        if (!$this->isSyncHostAllowed($request)) {
            return ['error' => 'Center-name sync can run only from demo.bkabd.com or an explicitly allowed local dashboard.'];
        }
        try {
            return (new DatabaseSyncService())->syncCenterNames();
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] center-name sync failed: ' . $e->getCode());
            return ['error' => 'Center-name sync failed safely. Check UPDATE permission for demo center-user names and review the server log.'];
        }
    }

    private function isSyncHostAllowed(Request $request): bool
    {
        $host = strtolower((string) preg_replace('/:\\d+$/', '', (string) $request->header('Host', '')));
        return $host === 'demo.bkabd.com'
            || (in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)
                && \App\Core\Config::getBool('DB_SYNC_ALLOW_LOCAL', false));
    }

    public function importStudents(Request $request): array
    {
        $host = strtolower((string) preg_replace('/:\\d+$/', '', (string) $request->header('Host', '')));
        $localHosts = ['localhost', '127.0.0.1', '[::1]'];
        $isAllowedLocal = in_array($host, $localHosts, true)
            && \App\Core\Config::getBool('DB_SYNC_ALLOW_LOCAL', false);
        if ($host !== 'demo.bkabd.com' && !$isAllowedLocal) {
            return ['error' => 'Student imports can run only from demo.bkabd.com or an explicitly allowed local dashboard.'];
        }
        $afterId = max(0, (int) $request->input('after_id', 0));
        try {
            return (new DatabaseSyncService())->importStudentBatch($afterId, 1000);
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] student import failed: ' . $e->getCode());
            return ['error' => 'The import stopped safely. Check the demo database permissions and table structure, then review the server error log.'];
        }
    }

    public function apply(Request $request): array
    {
        $host = strtolower((string) preg_replace('/:\\d+$/', '', (string) $request->header('Host', '')));
        $localHosts = ['localhost', '127.0.0.1', '[::1]'];
        $isAllowedLocal = in_array($host, $localHosts, true)
            && \App\Core\Config::getBool('DB_SYNC_ALLOW_LOCAL', false);
        if ($host !== 'demo.bkabd.com' && !$isAllowedLocal) {
            return ['error' => 'Run migrations from demo.bkabd.com, or explicitly allow the local dashboard in its private .env file.'];
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
