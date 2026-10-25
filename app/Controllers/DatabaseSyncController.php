<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Auth;
use App\Services\DatabaseSyncService;
use PDOException;
use RuntimeException;

final class DatabaseSyncController extends Controller
{
    public function status(): array
    {
        try {
            $status = (new DatabaseSyncService())->readiness();
            $status['active_session'] = (int) Auth::activeSession();
            return $status;
        } catch (PDOException $e) {
            error_log('[database-sync] status failed: ' . $e->getCode());
            return ['ready' => false, 'active_session' => 0, 'missing' => ['Could not connect to the configured demo database; check host, database, and target account.'], 'source' => [], 'target' => [], 'pending_migrations' => []];
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
            return ['error' => 'Center-name sync failed safely. Check UPDATE permission on demo centers.display_name and review the server log.'];
        }
    }

    public function syncStudentPhotos(Request $request): array
    {
        if (!$this->isSyncHostAllowed($request)) {
            return ['error' => 'Student-photo sync can run only from demo.bkabd.com or an explicitly allowed local dashboard.'];
        }
        $afterId = max(0, (int) $request->input('after_id', 0));
        $runId = trim((string) $request->input('run_id', ''));
        $service = new DatabaseSyncService();
        try {
            $activeSession = (int) Auth::activeSession();
            $requestedSession = (int) $request->input('session', 0);
            if ($activeSession <= 0 || $requestedSession !== $activeSession) {
                return ['error' => 'The active session changed or is unavailable. Reload this page and start photo sync again.'];
            }
            return $service->syncStudentPhotoBatch($activeSession, $afterId, 50, $runId);
        } catch (RuntimeException $e) {
            $service->failStudentPhotoSync($runId, $e->getMessage());
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            $service->failStudentPhotoSync($runId, 'Database error (' . (string) $e->getCode() . ').');
            error_log('[database-sync] student-photo sync failed: ' . $e->getCode());
            return ['error' => 'Student-photo sync stopped safely. Check database permissions and the server error log.'];
        }
    }

    public function studentPhotoSyncHistory(): array
    {
        try {
            return (new DatabaseSyncService())->studentPhotoSyncHistory();
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] student-photo history failed: ' . $e->getCode());
            return ['error' => 'Could not load student-photo sync history. Check demo database read access.'];
        }
    }

    public function studentPhotoSyncRunDetails(Request $request): array
    {
        try {
            return (new DatabaseSyncService())->studentPhotoSyncRunDetails(
                trim((string) $request->query('run_id', '')),
                (int) Auth::activeSession()
            );
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] student-photo detail lookup failed: ' . $e->getCode());
            return ['error' => 'Could not compare the run with current exam and demo student records.'];
        }
    }

    public function previewStudentPhotos(): array
    {
        try {
            return (new DatabaseSyncService())->previewStudentPhotoSync((int) Auth::activeSession());
        } catch (RuntimeException $e) {
            return ['error' => $e->getMessage()];
        } catch (PDOException $e) {
            error_log('[database-sync] student-photo preview failed: ' . $e->getCode());
            return ['error' => 'Could not count current-session student photos. Check read access to students on exam and demo.'];
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
