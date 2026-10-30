<?php

namespace App\Services;

use App\Core\Config;
use PDO;
use RuntimeException;

/** Read-only exam-schema comparison and explicitly allow-listed demo migrations. */
final class DatabaseSyncService
{
    /** @return array<string,mixed> */
    public function readiness(): array
    {
        $source = $this->sourceSettings();
        $target = $this->targetSettings();
        $targetHost = $target['host'];
        $targetDatabase = $target['database'];
        $missing = [];
        foreach (['SYNC_SOURCE_HOST', 'SYNC_SOURCE_DATABASE', 'SYNC_SOURCE_USERNAME', 'SYNC_SOURCE_PASSWORD', 'SYNC_TARGET_HOST', 'SYNC_TARGET_DATABASE', 'SYNC_TARGET_USERNAME'] as $key) {
            if (trim((string) Config::get($key, '')) === '') {
                $missing[] = $key;
            }
        }
        $targetIsLocal = in_array(strtolower(trim($targetHost)), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
        if (!$targetIsLocal && trim((string) Config::get('SYNC_TARGET_PASSWORD', '')) === '') {
            $missing[] = 'SYNC_TARGET_PASSWORD';
        }
        if (!Config::getBool('DB_SYNC_ENABLED', false)) {
            $missing[] = 'DB_SYNC_ENABLED must be explicitly enabled on the demo server';
        }
        if ($source['database'] !== '' && strcasecmp($source['database'], $targetDatabase) === 0
            && strcasecmp($source['host'], $targetHost) === 0
            && $source['port'] === $target['port']) {
            $missing[] = 'exam source and demo target point to the same database';
        }

        $migrationNames = $this->migrationNames();
        $pendingMigrations = $migrationNames;
        if ($missing === [] && $migrationNames !== []) {
            $pdo = $this->connectTarget();
            $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = \'codex_schema_migrations\'');
            $check->execute([$targetDatabase]);
            if ((int) $check->fetchColumn() > 0) {
                $applied = $pdo->query('SELECT `migration` FROM `codex_schema_migrations`')->fetchAll(PDO::FETCH_COLUMN);
                $pendingMigrations = array_values(array_diff($migrationNames, $applied));
            }
        }

        return [
            'ready' => $missing === [],
            'missing' => array_values(array_unique($missing)),
            'source' => ['label' => 'Exam reference', 'host' => $source['host'], 'database' => $source['database'], 'access' => 'Read-only connection'],
            'target' => ['label' => 'Demo destination', 'host' => $targetHost, 'database' => $targetDatabase, 'access' => 'Dedicated demo connection'],
            'pending_migrations' => $pendingMigrations,
        ];
    }

    /** Compare only metadata; never query application records on the source. */
    public function compare(): array
    {
        $this->assertReady();
        $source = $this->connectSource();
        $target = $this->connectTarget();
        $sourceSchema = (string) Config::get('SYNC_SOURCE_DATABASE');
        $targetSchema = (string) Config::get('SYNC_TARGET_DATABASE');
        try {
            $sourceTables = $this->schemaSnapshot($source, $sourceSchema);
            $targetTables = $this->schemaSnapshot($target, $targetSchema);
        } finally {
            if ($source->inTransaction()) {
                $source->rollBack();
            }
        }
        $differences = [];
        foreach ($sourceTables as $table => $sourceTable) {
            if (!isset($targetTables[$table])) {
                $differences[] = ['kind' => 'missing_table', 'table' => $table, 'detail' => 'Table exists in exam but not demo'];
                continue;
            }
            foreach ($sourceTable['columns'] as $column => $definition) {
                if (!isset($targetTables[$table]['columns'][$column])) {
                    $differences[] = ['kind' => 'missing_column', 'table' => $table, 'detail' => "Column `{$column}` exists in exam but not demo"];
                } elseif ($targetTables[$table]['columns'][$column] !== $definition) {
                    $differences[] = ['kind' => 'column_mismatch', 'table' => $table, 'detail' => "Column `{$column}` has a different definition"];
                }
            }
            foreach ($sourceTable['indexes'] as $index => $definition) {
                if (!isset($targetTables[$table]['indexes'][$index])) {
                    $differences[] = ['kind' => 'missing_index', 'table' => $table, 'detail' => "Index `{$index}` exists in exam but not demo"];
                } elseif ($targetTables[$table]['indexes'][$index] !== $definition) {
                    $differences[] = ['kind' => 'index_mismatch', 'table' => $table, 'detail' => "Index `{$index}` has a different definition"];
                }
            }
        }
        $ignored = [];
        $differences = array_values(array_filter($differences, function (array $difference) use (&$ignored): bool {
            if ($this->isIgnoredDifference($difference)) {
                $ignored[] = $difference;
                return false;
            }
            return true;
        }));

        return [
            'source' => $sourceSchema,
            'target' => $targetSchema,
            'differences' => $differences,
            'ignored' => $ignored,
            'note' => 'Comparison is read-only and reports exam-side additions or differences. It does not create SQL or change either database.',
        ];
    }

    /** Return a small, read-only view of recent exam registrations; never copy rows to demo. */
    public function latestStudents(int $limit = 20): array
    {
        $this->assertReady();
        $limit = max(1, min(50, $limit));
        $source = $this->connectSource();
        try {
            $sql = 'SELECT s.`registration`, s.`name`, s.`class`, s.`session`, s.`created_at`, s.`active`, '
                . 'NULLIF(cu.`name`, \'\') AS `center_name`, '
                . 'i.`name` AS `institute_name` '
                . 'FROM `students` s '
                . 'LEFT JOIN `centers` c ON c.`id` = s.`center_id` '
                . 'LEFT JOIN `institutes` i ON i.`id` = s.`institute_id` '
                . 'LEFT JOIN `users` cu ON cu.`id` = c.`user_id` '
                . 'ORDER BY s.`created_at` DESC, s.`id` DESC LIMIT ' . $limit;
            return $source->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            if ($source->inTransaction()) {
                $source->rollBack();
            }
        }
    }

    /** Copy exam student photos to demo for registrations already present there. */
    public function syncStudentPhotoBatch(int $session, int $afterId = 0, int $batchSize = 25, string $runId = ''): array
    {
        $this->assertReady();
        if ($session <= 0) {
            throw new RuntimeException('The current exam session could not be determined.');
        }
        $batchSize = max(1, min(50, $batchSize));
        if (!preg_match('/^[a-f0-9-]{36}$/i', $runId)) {
            throw new RuntimeException('A valid photo-sync run ID is required. Reload the page and start again.');
        }
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            if ($afterId === 0) {
                $this->assertPhotoSyncHistoryTable($target);
                $start = $target->prepare('INSERT INTO `codex_student_photo_sync_runs` (`run_id`, `session`, `status`, `started_at`, `updated_at`) VALUES (?, ?, \'running\', NOW(), NOW())');
                $start->execute([$runId, $session]);
            } else {
                // Resume only the exact saved checkpoint. This prevents a stale browser
                // tab from overwriting a newer run or skipping source records.
                $resume = $target->prepare('SELECT `status`, `last_source_id` FROM `codex_student_photo_sync_runs` WHERE `run_id` = ? AND `session` = ?');
                $resume->execute([$runId, $session]);
                $savedRun = $resume->fetch(PDO::FETCH_ASSOC);
                if (!$savedRun || (int) $savedRun['last_source_id'] !== $afterId || !in_array($savedRun['status'], ['running', 'failed'], true)) {
                    throw new RuntimeException('This photo-sync checkpoint is no longer resumable. Refresh the page and start a new sync.');
                }
                $resume = $target->prepare('UPDATE `codex_student_photo_sync_runs` SET `status` = \'running\', `finished_at` = NULL, `failure_message` = NULL, `updated_at` = NOW() WHERE `run_id` = ? AND `session` = ?');
                $resume->execute([$runId, $session]);
            }
            $stmt = $source->prepare(
                'SELECT `id`, `registration`, `photo` FROM `students` WHERE `session` = ? AND `id` > ? '
                . 'AND `registration` IS NOT NULL AND TRIM(`registration`) <> \'\' '
                . 'AND `photo` IS NOT NULL AND TRIM(`photo`) <> \'\' ORDER BY `id` ASC LIMIT ' . $batchSize
            );
            $stmt->execute([$session, max(0, $afterId)]);
            $sourceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($sourceRows === []) {
                $this->recordPhotoSyncBatch($target, $runId, 0, 0, 0, 0, $afterId, true);
                return ['done' => true, 'session' => $session, 'next_after_id' => $afterId, 'checked' => 0, 'updated' => 0, 'already_synced' => 0, 'missing_demo' => 0, 'unavailable' => 0];
            }

            $registrations = array_values(array_unique(array_map(static fn(array $row): string => trim((string) $row['registration']), $sourceRows)));
            $targetStmt = $target->prepare(
                'SELECT `id`, `registration`, `photo` FROM `students` WHERE `session` = ? AND `registration` IN ('
                . implode(',', array_fill(0, count($registrations), '?')) . ')'
            );
            $targetStmt->execute(array_merge([$session], $registrations));
            $targetByRegistration = [];
            foreach ($targetStmt->fetchAll(PDO::FETCH_ASSOC) as $targetRow) {
                $key = mb_strtolower(trim((string) $targetRow['registration']), 'UTF-8');
                if (isset($targetByRegistration[$key])) {
                    $targetByRegistration[$key] = null;
                } elseif (!array_key_exists($key, $targetByRegistration)) {
                    $targetByRegistration[$key] = $targetRow;
                }
            }

            $update = $target->prepare('UPDATE `students` SET `photo` = ?, `updated_at` = NOW() WHERE `id` = ?');
            $counts = ['updated' => 0, 'already_synced' => 0, 'missing_demo' => 0, 'unavailable' => 0];
            $lastId = (int) $sourceRows[array_key_last($sourceRows)]['id'];
            $matched = [];
            $primaryUrls = [];
            foreach ($sourceRows as $sourceRow) {
                $key = mb_strtolower(trim((string) $sourceRow['registration']), 'UTF-8');
                $targetRow = $targetByRegistration[$key] ?? null;
                if ($targetRow === null) {
                    $counts['missing_demo']++;
                    continue;
                }
                $storedPath = (string) $sourceRow['photo'];
                $expectedPath = 'uploads/students/sync_' . (int) $targetRow['id'] . '_' . substr(hash('sha256', $storedPath), 0, 16) . '.jpg';
                $currentPath = ltrim(str_replace('\\', '/', trim((string) ($targetRow['photo'] ?? ''))), '/');
                if ($currentPath === $expectedPath && is_file(BASE_PATH . '/public/' . $expectedPath)) {
                    $counts['already_synced']++;
                    continue;
                }
                // On a shared VPS, read protected exam uploads directly from
                // the configured local folder instead of requesting a login page.
                $localBytes = $this->readLocalStudentPhoto($storedPath);
                $urls = $localBytes === null ? $this->studentPhotoUrls($storedPath) : [];
                if ($localBytes === null && $urls === []) {
                    $counts['unavailable']++;
                    continue;
                }
                $jobId = count($matched);
                $matched[$jobId] = ['source' => $sourceRow, 'target' => $targetRow, 'urls' => $urls, 'local_bytes' => $localBytes];
                if ($urls !== []) { $primaryUrls[$jobId] = $urls[0]; }
            }

            // Fetch each chunk concurrently so latency is paid once per batch instead of once per photo.
            $photoBytesByJob = $this->fetchStudentPhotoUrls($primaryUrls);
            $fallbackUrls = [];
            foreach ($matched as $jobId => $job) {
                if (empty($photoBytesByJob[$jobId]) && isset($job['urls'][1])) {
                    $fallbackUrls[$jobId] = $job['urls'][1];
                }
            }
            if ($fallbackUrls !== []) {
                foreach ($this->fetchStudentPhotoUrls($fallbackUrls) as $jobId => $bytes) {
                    if ($bytes !== null) { $photoBytesByJob[$jobId] = $bytes; }
                }
            }

            foreach ($matched as $jobId => $job) {
                $photoBytes = $job['local_bytes'] ?? ($photoBytesByJob[$jobId] ?? null);
                $photo = $this->storeStudentPhoto($photoBytes, (string) $job['source']['photo'], (int) $job['target']['id']);
                if ($photo === null) {
                    $counts['unavailable']++;
                    continue;
                }
                [$relativePath, $absolutePath, $photoBytes] = $photo;
                if (@file_put_contents($absolutePath, $photoBytes, LOCK_EX) !== strlen($photoBytes)) {
                    @unlink($absolutePath);
                    $counts['unavailable']++;
                    continue;
                }
                try {
                    $update->execute([$relativePath, (int) $job['target']['id']]);
                } catch (\Throwable $e) {
                    @unlink($absolutePath);
                    throw $e;
                }
                $counts['updated']++;
            }

            $done = count($sourceRows) < $batchSize;
            $this->recordPhotoSyncBatch($target, $runId, count($sourceRows), $counts['updated'], $counts['missing_demo'], $counts['unavailable'], $lastId, $done);
            return ['done' => $done, 'session' => $session, 'next_after_id' => $lastId, 'checked' => count($sourceRows)] + $counts;
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
            if ($target->inTransaction()) { $target->rollBack(); }
        }
    }

    /** List recent current-destination photo-sync runs and their accumulated results. */
    public function studentPhotoSyncHistory(int $limit = 10): array
    {
        $this->assertReady();
        $target = $this->connectTarget();
        $check = $target->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $check->execute(['codex_student_photo_sync_runs']);
        if (!(int) $check->fetchColumn()) {
            return ['requires_migration' => true, 'runs' => []];
        }
        $limit = max(1, min(20, $limit));
        $rows = $target->query('SELECT `run_id`, `session`, `status`, `started_at`, `finished_at`, `updated_at`, `last_source_id`, `checked_count`, `copied_count`, `missing_demo_count`, `unavailable_count`, `failure_message` FROM `codex_student_photo_sync_runs` ORDER BY `id` DESC LIMIT ' . $limit)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['already_synced_count'] = max(0, (int) $row['checked_count'] - (int) $row['copied_count'] - (int) $row['missing_demo_count'] - (int) $row['unavailable_count']);
            if ($row['status'] === 'running' && strtotime((string) $row['updated_at']) < time() - 1800) {
                $row['status'] = 'interrupted';
            }
        }
        unset($row);
        return ['requires_migration' => false, 'runs' => $rows];
    }

    /** Reconstruct which processed students still lack their synced photo. */
    public function studentPhotoSyncRunDetails(string $runId, int $session): array
    {
        $this->assertReady();
        if (!preg_match('/^[a-f0-9-]{36}$/i', $runId)) { throw new RuntimeException('Invalid photo-sync run.'); }
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            $runStmt = $target->prepare('SELECT `run_id`, `session`, `status`, `started_at`, `last_source_id`, `checked_count`, `copied_count`, `unavailable_count` FROM `codex_student_photo_sync_runs` WHERE `run_id` = ? AND `session` = ?');
            $runStmt->execute([$runId, $session]);
            $run = $runStmt->fetch(PDO::FETCH_ASSOC);
            if (!$run) { throw new RuntimeException('This photo-sync run could not be found.'); }

            $sourceStmt = $source->prepare(
                'SELECT s.`id`, s.`registration`, s.`name`, s.`class`, s.`photo`, s.`center_id`, s.`institute_id`, NULLIF(cu.`name`, \'\') AS `center_name`, i.`name` AS `institute_name` '
                . 'FROM `students` s LEFT JOIN `centers` c ON c.`id` = s.`center_id` '
                . 'LEFT JOIN `institutes` i ON i.`id` = s.`institute_id` '
                . 'LEFT JOIN `users` cu ON cu.`id` = c.`user_id` '
                . 'WHERE s.`session` = ? AND s.`id` > ? AND s.`id` <= ? '
                . 'AND s.`registration` IS NOT NULL AND TRIM(s.`registration`) <> \'\' '
                . 'AND s.`photo` IS NOT NULL AND TRIM(s.`photo`) <> \'\' ORDER BY s.`id` ASC LIMIT 500'
            );
            $candidates = [];
            $afterId = 0;
            $lastSourceId = (int) $run['last_source_id'];
            do {
                $sourceStmt->execute([$session, $afterId, $lastSourceId]);
                $rows = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($rows === []) { break; }
                $afterId = (int) $rows[array_key_last($rows)]['id'];
                $registrations = array_values(array_unique(array_map(static fn(array $row): string => trim((string) $row['registration']), $rows)));
                $targetStmt = $target->prepare('SELECT `id`, `registration`, `photo` FROM `students` WHERE `registration` IN (' . implode(',', array_fill(0, count($registrations), '?')) . ')');
                $targetStmt->execute($registrations);
                $targetByRegistration = [];
                foreach ($targetStmt->fetchAll(PDO::FETCH_ASSOC) as $targetRow) {
                    $key = mb_strtolower(trim((string) $targetRow['registration']), 'UTF-8');
                    if (array_key_exists($key, $targetByRegistration)) { $targetByRegistration[$key] = null; }
                    else { $targetByRegistration[$key] = $targetRow; }
                }
                foreach ($rows as $row) {
                    $targetRow = $targetByRegistration[mb_strtolower(trim((string) $row['registration']), 'UTF-8')] ?? null;
                    $storedPath = (string) $row['photo'];
                    $urls = $this->studentPhotoUrls($storedPath);
                    $expectedPath = $targetRow === null ? '' : 'uploads/students/sync_' . (int) $targetRow['id'] . '_' . substr(hash('sha256', $storedPath), 0, 16) . '.jpg';
                    $actualPath = str_replace('\\', '/', trim((string) ($targetRow['photo'] ?? '')));
                    $pathMatches = $expectedPath !== '' && ltrim($actualPath, '/') === $expectedPath;
                    $fileExists = $pathMatches && is_file(BASE_PATH . '/public/' . $expectedPath);
                    if ($targetRow !== null && $fileExists) { continue; }
                    $candidates[] = [
                        'source_id' => (int) $row['id'],
                        'registration' => (string) $row['registration'],
                        'name' => (string) $row['name'],
                        'class' => (string) $row['class'],
                        'center_id' => (int) ($row['center_id'] ?? 0),
                        'institute_id' => (int) ($row['institute_id'] ?? 0),
                        'center' => (string) ($row['center_name'] ?? ''),
                        'institute' => (string) ($row['institute_name'] ?? ''),
                        'exam_photo_file' => basename(str_replace('\\', '/', $storedPath)),
                        'reason' => $targetRow === null ? 'No unique demo registration match' : ($pathMatches ? 'Synced photo file is missing on this server' : 'No matching synced photo is currently saved on demo'),
                        'source_photo_url' => $urls[0] ?? null,
                    ];
                }
            } while (count($rows) === 500);
            return ['run' => $run, 'candidate_count' => count($candidates), 'candidates' => $candidates];
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    /** Email a selected portion of a photo-attention run as a CSV attachment. */
    public function emailPhotoAttentionCsv(string $runId, int $session, ?int $centerId, ?int $instituteId, string $recipient): array
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid recipient email address.');
        }
        $details = $this->studentPhotoSyncRunDetails($runId, $session);
        $candidates = array_values(array_filter($details['candidates'], static function (array $student) use ($centerId, $instituteId): bool {
            return ($centerId === null || (int) ($student['center_id'] ?? 0) === $centerId)
                && ($instituteId === null || (int) ($student['institute_id'] ?? 0) === $instituteId);
        }));
        if ($candidates === []) {
            throw new RuntimeException('No students match the selected center and institute filters.');
        }
        usort($candidates, static fn(array $a, array $b): int => strcasecmp((string) $a['center'], (string) $b['center'])
            ?: strcasecmp((string) $a['institute'], (string) $b['institute'])
            ?: strcasecmp((string) $a['name'], (string) $b['name'])
            ?: strcmp((string) $a['registration'], (string) $b['registration']));

        $csvRows = [['Session', 'Registration', 'Student', 'Class', 'Center', 'Institute', 'Current status', 'Exam photo file']];
        foreach ($candidates as $student) {
            $csvRows[] = [$session, $student['registration'], $student['name'], $student['class'], $student['center'], $student['institute'], $student['reason'], $student['exam_photo_file']];
        }
        $csv = "\xEF\xBB\xBF";
        foreach ($csvRows as $row) {
            $cells = array_map(static function ($value): string {
                $value = (string) ($value ?? '');
                if (preg_match('/^[\\s]*[=+@\\-]/u', $value)) { $value = "'" . $value; }
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row);
            $csv .= implode(',', $cells) . "\r\n";
        }

        $filename = 'student-photo-attention-session-' . $session . '.csv';
        $centerName = $centerId === null ? 'All centers' : (string) ($candidates[0]['center'] ?: 'Unassigned center');
        $instituteName = $instituteId === null ? 'All institutes' : (string) ($candidates[0]['institute'] ?: 'Unassigned institute');
        $subject = 'Student photo attention report - Session ' . $session;
        $body = "Attached is the student photo attention report for session {$session}.\r\n"
            . "Center: {$centerName}\r\nInstitute: {$instituteName}\r\nStudents: " . count($candidates) . "\r\n\r\n"
            . 'The status is reconstructed from current exam records and demo photo paths; it is not the original per-student transfer error.';
        (new SmtpMailer())->sendWithCsvAttachment($recipient, $subject, $body, $filename, $csv);

        return ['sent' => true, 'recipient' => $recipient, 'count' => count($candidates)];
    }

    /** Record an API failure without masking the original sync error. */
    public function failStudentPhotoSync(string $runId, string $reason): void
    {
        if (!preg_match('/^[a-f0-9-]{36}$/i', $runId)) { return; }
        try {
            $target = $this->connectTarget();
            $stmt = $target->prepare('UPDATE `codex_student_photo_sync_runs` SET `status` = \'failed\', `finished_at` = NOW(), `updated_at` = NOW(), `failure_message` = ? WHERE `run_id` = ? AND `status` = \'running\'');
            $stmt->execute([mb_substr($reason, 0, 500), $runId]);
        } catch (\Throwable $ignored) {
            // History is best-effort when the database itself is unavailable.
        }
    }

    private function assertPhotoSyncHistoryTable(PDO $target): void
    {
        $check = $target->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $check->execute(['codex_student_photo_sync_runs']);
        if (!(int) $check->fetchColumn()) {
            throw new RuntimeException('Apply the pending student-photo sync history migration on the demo database, then try again.');
        }
    }

    private function recordPhotoSyncBatch(PDO $target, string $runId, int $checked, int $copied, int $missing, int $unavailable, int $lastId, bool $done): void
    {
        $stmt = $target->prepare('UPDATE `codex_student_photo_sync_runs` SET `checked_count` = `checked_count` + ?, `copied_count` = `copied_count` + ?, `missing_demo_count` = `missing_demo_count` + ?, `unavailable_count` = `unavailable_count` + ?, `last_source_id` = ?, `status` = ?, `finished_at` = IF(? = 1, NOW(), `finished_at`), `updated_at` = NOW() WHERE `run_id` = ? AND `status` = \'running\'');
        $stmt->execute([$checked, $copied, $missing, $unavailable, $lastId, $done ? 'completed' : 'running', $done ? 1 : 0, $runId]);
    }

    /** Count current-session photos already copied by this tool and still pending. */
    public function previewStudentPhotoSync(int $session): array
    {
        $this->assertReady();
        if ($session <= 0) {
            throw new RuntimeException('The current exam session could not be determined.');
        }
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            // Read destination registrations once. Previously each 500-row
            // source batch triggered another destination query, which caused
            // many repeated scans on large student databases.
            $targetStmt = $target->prepare(
                'SELECT `registration`, `photo` FROM `students` WHERE `session` = ? '
                . 'AND `registration` IS NOT NULL AND TRIM(`registration`) <> \'\''
            );
            $targetStmt->execute([$session]);
            $targetByRegistration = [];
            while ($targetRow = $targetStmt->fetch(PDO::FETCH_ASSOC)) {
                $key = mb_strtolower(trim((string) $targetRow['registration']), 'UTF-8');
                if (array_key_exists($key, $targetByRegistration)) {
                    $targetByRegistration[$key] = null;
                } else {
                    $targetByRegistration[$key] = $targetRow;
                }
            }
            $targetStmt->closeCursor();

            // Stream the exam photo registrations and count them in one pass.
            $sourceRows = $source->prepare(
                'SELECT `registration` FROM `students` WHERE `session` = ? '
                . 'AND `photo` IS NOT NULL AND TRIM(`photo`) <> \'\''
            );
            $sourceRows->execute([$session]);
            $counts = ['source_photos' => 0, 'without_registration' => 0, 'matched' => 0, 'uploaded' => 0, 'pending' => 0, 'missing_demo' => 0];
            while ($row = $sourceRows->fetch(PDO::FETCH_ASSOC)) {
                $counts['source_photos']++;
                $registration = trim((string) ($row['registration'] ?? ''));
                if ($registration === '') {
                    $counts['without_registration']++;
                    continue;
                }
                $key = mb_strtolower($registration, 'UTF-8');
                $targetRow = $targetByRegistration[$key] ?? null;
                if ($targetRow === null) {
                    $counts['missing_demo']++;
                    continue;
                }
                $counts['matched']++;
                $photoPath = str_replace('\\', '/', (string) ($targetRow['photo'] ?? ''));
                if (preg_match('#(?:^|/)sync_[^/]+\.(?:jpe?g|png|gif)$#i', $photoPath)) {
                    $counts['uploaded']++;
                } else {
                    $counts['pending']++;
                }
            }
            $sourceRows->closeCursor();

            return [
                'session' => $session,
                'source_photos' => $counts['source_photos'],
                'without_registration' => $counts['without_registration'],
            ] + $counts + [
                'note' => 'Already synced counts demo photo paths created by this sync tool. Pending counts matched students whose demo photo has not been synced by this tool yet; sync replaces the current demo photo when an exam photo is available.',
            ];
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
            if ($target->inTransaction()) { $target->rollBack(); }
        }
    }

    /** @return list<string> safe source URLs, with the legacy path as fallback when applicable */
    private function studentPhotoUrls(string $storedPath): array
    {
        if (!function_exists('curl_init') || !function_exists('curl_multi_init')) {
            throw new RuntimeException('Student photo sync requires the PHP cURL extension on demo.');
        }
        $base = rtrim((string) Config::get('SYNC_SOURCE_FILES_BASE_URL', 'https://exam.bkabd.com'), '/');
        $baseParts = parse_url($base);
        if (!is_array($baseParts) || strtolower((string) ($baseParts['scheme'] ?? '')) !== 'https' || empty($baseParts['host'])) {
            throw new RuntimeException('SYNC_SOURCE_FILES_BASE_URL must be an HTTPS exam-site URL.');
        }

        $path = trim(str_replace('\\', '/', $storedPath));
        if (preg_match('#^https?://#i', $path)) {
            $parts = parse_url($path);
            if (!is_array($parts) || strtolower((string) ($parts['host'] ?? '')) !== strtolower((string) $baseParts['host'])) { return []; }
            $path = (string) ($parts['path'] ?? '');
        }
        $path = ltrim($path, '/');
        if ($path === '' || preg_match('/[\x00-\x20?#]/', $path)) { return []; }
        $paths = str_contains($path, '/') ? [$path] : ['uploads/students/' . $path, 'images/students/' . $path];
        $urls = [];
        foreach ($paths as $candidate) {
            $segments = explode('/', $candidate);
            foreach ($segments as $segment) {
                if ($segment === '' || $segment === '.' || $segment === '..') { return []; }
            }
            $urls[] = $base . '/' . implode('/', array_map('rawurlencode', $segments));
        }
        return $urls;
    }

    /** Read a protected exam upload from the shared VPS filesystem, if configured. */
    private function readLocalStudentPhoto(string $storedPath): ?string
    {
        $directory = trim((string) Config::get('SYNC_SOURCE_PHOTO_DIR', ''));
        if ($directory === '') { return null; }
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('SYNC_SOURCE_PHOTO_DIR is configured but is not a readable directory on this server.');
        }

        $normalized = trim(str_replace('\\', '/', $storedPath));
        if (preg_match('#^https?://#i', $normalized)) {
            $parts = parse_url($normalized);
            $normalized = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        }
        $name = basename($normalized);
        if ($name === '' || $name === '.' || $name === '..' || preg_match('/[\\x00-\\x1f]/', $name)) { return null; }
        $candidate = realpath($root . DIRECTORY_SEPARATOR . $name);
        if ($candidate === false || !is_file($candidate)
            || !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)
            || !is_readable($candidate)) { return null; }
        $size = filesize($candidate);
        if ($size === false || $size < 1 || $size > 2 * 1024 * 1024) { return null; }
        $bytes = @file_get_contents($candidate);
        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /** @param array<int,string> $urls @return array<int,string|null> */
    private function fetchStudentPhotoUrls(array $urls): array
    {
        if ($urls === []) { return []; }
        $multi = curl_multi_init();
        $handles = [];
        $buffers = [];
        $results = [];
        try {
            foreach ($urls as $jobId => $url) {
                $buffers[$jobId] = '';
                $handle = curl_init($url);
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$buffers, $jobId): int {
                        if (strlen($buffers[$jobId]) + strlen($chunk) > 2 * 1024 * 1024) { return 0; }
                        $buffers[$jobId] .= $chunk;
                        return strlen($chunk);
                    },
                ]);
                curl_multi_add_handle($multi, $handle);
                $handles[$jobId] = $handle;
            }
            do {
                do { $status = curl_multi_exec($multi, $running); } while ($status === CURLM_CALL_MULTI_PERFORM);
                if ($running > 0) {
                    $selected = curl_multi_select($multi, 0.5);
                    if ($selected === -1) { usleep(10000); }
                }
            } while ($running > 0 && $status === CURLM_OK);

            foreach ($handles as $jobId => $handle) {
                $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                $results[$jobId] = curl_errno($handle) === 0 && $statusCode === 200 && $buffers[$jobId] !== '' ? $buffers[$jobId] : null;
            }
        } finally {
            foreach ($handles as $handle) {
                curl_multi_remove_handle($multi, $handle);
            }
            curl_multi_close($multi);
        }
        return $results;
    }

    /** @return array{0:string,1:string,2:string}|null relative path, disk path, bytes */
    private function storeStudentPhoto(?string $bytes, string $storedPath, int $studentId): ?array
    {
        if ($bytes === null || $bytes === '') { return null; }
        $imageInfo = @getimagesizefromstring($bytes);
        $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
        if ($imageInfo === false || !isset($allowed[(int) $imageInfo[2]]) || $imageInfo[0] > 8000 || $imageInfo[1] > 8000) { return null; }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            throw new RuntimeException('Student photo resizing requires the PHP GD extension on demo.');
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) { return null; }
        $bytes = $this->encodeStudentPhoto($image);
        unset($image);
        $ext = 'jpg';
        $directory = BASE_PATH . '/public/uploads/students';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) { return null; }
        $name = 'sync_' . $studentId . '_' . substr(hash('sha256', $storedPath), 0, 16) . '.' . $ext;
        return ['uploads/students/' . $name, $directory . '/' . $name, $bytes];
    }

    private function encodeStudentPhoto($image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = 60;
        $targetHeight = 70;
        $targetRatio = $targetWidth / $targetHeight;
        if ($width / $height > $targetRatio) {
            $cropHeight = $height;
            $cropWidth = max(1, (int) round($height * $targetRatio));
            $sourceX = (int) floor(($width - $cropWidth) / 2);
            $sourceY = 0;
        } else {
            $cropWidth = $width;
            $cropHeight = max(1, (int) round($width / $targetRatio));
            $sourceX = 0;
            $sourceY = (int) floor(($height - $cropHeight) / 2);
        }
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
        ob_start();
        imagejpeg($canvas, null, 86);
        $encoded = (string) ob_get_clean();
        if ($encoded === '') { throw new RuntimeException('A student photo could not be encoded on demo.'); }
        return $encoded;
    }

    /** Preview center display-name changes using a unique center code as identity. */
    public function previewCenterNameSync(): array
    {
        $this->assertReady();
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            $this->assertCenterDisplayNameSchema($target);
            $plan = $this->centerNameSyncPlan($source, $target);
            return [
                'matched' => $plan['matched'],
                'changes' => count($plan['updates']),
                'contact_changes' => count(array_filter($plan['updates'], static fn(array $row): bool => $row['old_phone'] !== $row['new_phone'] || $row['old_email'] !== $row['new_email'])),
                'authority_changes' => count(array_filter($plan['updates'], static fn(array $row): bool => $row['old_authority'] !== $row['new_authority'])),
                'ambiguous' => $plan['ambiguous'],
                'unmatched' => $plan['unmatched'],
                'examples' => array_slice(array_map(static fn(array $row): array => [
                    'code' => $row['code'], 'from' => $row['old_name'], 'to' => $row['new_name'],
                    'authority_from' => $row['old_authority'], 'authority_to' => $row['new_authority'],
                    'phone' => $row['new_phone'], 'email' => $row['new_email'],
                ], $plan['updates']), 0, 100),
                'note' => 'Matches by a unique center code; duplicate codes are disambiguated by venue and geographic names. Applying updates center display names, authority person, and phone/email contact fields in demo. Blank exam values never erase existing demo values. It does not create accounts, copy passwords, or modify exam.',
            ];
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    /** Apply center display names, authority person, and contact fields to matched demo centers. */
    public function syncCenterNames(): array
    {
        $this->assertReady();
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            $this->assertCenterDisplayNameSchema($target);
            $plan = $this->centerNameSyncPlan($source, $target);
            $target->beginTransaction();
            try {
                $update = $target->prepare('UPDATE `centers` SET `display_name` = ?, `authority_person` = ?, `contact_phone` = ?, `contact_email` = ?, `updated_at` = NOW() WHERE `id` = ? AND `display_name` <=> ? AND `authority_person` <=> ? AND `contact_phone` <=> ? AND `contact_email` <=> ?');
                $updated = 0;
                foreach ($plan['updates'] as $row) {
                    $update->execute([$row['new_name'], $row['new_authority'], $row['new_phone'], $row['new_email'], $row['center_id'], $row['stored_name'], $row['old_authority'], $row['old_phone'], $row['old_email']]);
                    $updated += $update->rowCount();
                }
                $target->commit();
                \App\Core\Cache::forget('schema:centers.display_name');
                \App\Core\Cache::forget('schema:centers.contact_phone');
                \App\Core\Cache::forget('schema:centers.contact_email');
                \App\Core\Cache::forget('filter:active_centers_named_v2');
            } catch (\Throwable $e) {
                if ($target->inTransaction()) { $target->rollBack(); }
                throw $e;
            }
            return ['updated' => $updated, 'matched' => $plan['matched'], 'ambiguous' => $plan['ambiguous'], 'unmatched' => $plan['unmatched'], 'contact_updates' => count(array_filter($plan['updates'], static fn(array $row): bool => $row['old_phone'] !== $row['new_phone'] || $row['old_email'] !== $row['new_email'])), 'authority_updates' => count(array_filter($plan['updates'], static fn(array $row): bool => $row['old_authority'] !== $row['new_authority']))];
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    /** Preview missing center and institute reference rows using stable identities only. */
    public function previewReferenceSync(): array
    {
        $this->assertReady();
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            $this->assertReferenceSyncSchema($target);
            $plan = $this->referenceSyncPlan($source, $target);
            return [
                'centers' => count($plan['centers']),
                'institutes' => count($plan['institutes']),
                'locations' => array_sum(array_map('count', $plan['locations'])),
                'ambiguous_locations' => $plan['ambiguous_locations'],
                'ambiguous_centers' => $plan['ambiguous_centers'],
                'ambiguous_institutes' => $plan['ambiguous_institutes'],
                'unmatched_institutes' => $plan['unmatched_institutes'],
                'examples' => [
                    'centers' => array_slice(array_map(static fn(array $r): array => ['code' => $r['code'], 'name' => $r['name'], 'venue' => $r['venue']], $plan['centers']), 0, 100),
                    'institutes' => array_slice(array_map(static fn(array $r): array => ['name' => $r['name'], 'certificate_no' => $r['certificate_no'], 'center' => $r['center_name']], $plan['institutes']), 0, 100),
                    'locations' => array_map(static fn(array $rows): array => array_slice(array_map(static fn(array $r): array => ['name' => $r['name']], $rows), 0, 100), $plan['locations']),
                ],
                'note' => 'Adds only uniquely matched missing reference rows to demo, including geographic parents before children. Existing rows are unchanged; no accounts, passwords, photos, or private contact details are copied. Exam remains read-only.',
            ];
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    /** Add only previewed missing reference rows, rechecking identities inside the target transaction. */
    public function syncReferences(): array
    {
        $this->assertReady();
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            $this->assertReferenceSyncSchema($target);
            $target->beginTransaction();
            try {
                $lock = (int) $target->query("SELECT GET_LOCK('codex_demo_reference_sync', 10)")->fetchColumn();
                if ($lock !== 1) { throw new RuntimeException('Another reference sync is running. Wait a moment and retry.'); }
                $plan = $this->referenceSyncPlan($source, $target);
                $resolvedIds = [];
                $locationMaps = $plan['location_maps'];
                $nextUpazilaId = (int) $target->query('SELECT COALESCE(MAX(`id`),0)+1 FROM `upozilas`')->fetchColumn();
                foreach (['divisions','districts','upozilas','unions'] as $table) {
                    foreach ($plan['locations'][$table] as $row) {
                        $parent = $row['parent_target_id'] === null ? null : ($resolvedIds[$row['parent_target_id']] ?? $row['parent_target_id']);
                        if ($row['parent_column'] !== null && $parent === null) { continue; }
                        if ($table === 'divisions') {
                            $stmt=$target->prepare('INSERT INTO `divisions` (`name`,`user_id`,`created_at`,`updated_at`) VALUES (?,NULL,NOW(),NOW())'); $stmt->execute([$row['name']]);
                        } elseif ($table === 'districts') {
                            $stmt=$target->prepare('INSERT INTO `districts` (`name`,`division_id`,`user_id`,`created_at`,`updated_at`) VALUES (?,?,NULL,NOW(),NOW())'); $stmt->execute([$row['name'],$parent]);
                        } elseif ($table === 'upozilas') {
                            $stmt=$target->prepare('INSERT INTO `upozilas` (`id`,`name`,`district_id`,`user_id`,`created_at`,`updated_at`) VALUES (?,?,?,NULL,NOW(),NOW())'); $stmt->execute([$nextUpazilaId,$row['name'],$parent]);
                            $nextUpazilaId++;
                        } else {
                            $stmt=$target->prepare('INSERT INTO `unions` (`name`,`upozila_id`,`user_id`,`created_at`,`updated_at`) VALUES (?,?,NULL,NOW(),NOW())'); $stmt->execute([$row['name'],$parent]);
                        }
                        $createdId = $table === 'upozilas' ? $nextUpazilaId - 1 : (int)$target->lastInsertId();
                        $resolvedIds[$row['token']] = $createdId;
                        $locationMaps[$table][(int)$row['source_id']] = $createdId;
                    }
                }
                $centerInsert = $target->prepare('INSERT INTO `centers` (`user_id`,`division_id`,`district_id`,`upazila_id`,`union_id`,`institute_name`,`code`,`display_name`,`active`,`created_at`,`updated_at`) VALUES (NULL,?,?,?,?,?,?,?,?,NOW(),NOW())');
                $centerMap = $plan['center_map'];
                foreach ($plan['centers'] as $row) {
                    $centerInsert->execute([$resolvedIds[$row['division_id']]??$row['division_id'], $resolvedIds[$row['district_id']]??$row['district_id'], $resolvedIds[$row['upazila_id']]??$row['upazila_id'], $resolvedIds[$row['union_id']]??$row['union_id'], $row['venue'], $row['code'], $row['name'], $row['active']]);
                    $centerMap[(int) $row['source_id']] = (int) $target->lastInsertId();
                }
                $instituteInsert = $target->prepare('INSERT INTO `institutes` (`center_id`,`name`,`division_id`,`district_id`,`upazila_id`,`union_id`,`certificate_no`,`active`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
                foreach ($plan['institutes'] as $row) {
                    $centerId = $centerMap[(int) $row['source_center_id']] ?? null;
                    if ($centerId === null) { continue; }
                    $instituteInsert->execute([$centerId, $row['name'], $resolvedIds[$row['division_id']]??$row['division_id'], $resolvedIds[$row['district_id']]??$row['district_id'], $resolvedIds[$row['upazila_id']]??$row['upazila_id'], $resolvedIds[$row['union_id']]??$row['union_id'], $row['certificate_no'], $row['active']]);
                }
                $target->commit();
            } catch (\Throwable $e) {
                if ($target->inTransaction()) { $target->rollBack(); }
                throw $e;
            }
            \App\Core\Cache::forget('filter:active_centers_named_v2');
            try { $target->query("SELECT RELEASE_LOCK('codex_demo_reference_sync')"); } catch (\Throwable) {}
            return ['centers_added' => count($plan['centers']), 'institutes_added' => count($plan['institutes']), 'locations_added' => array_sum(array_map('count', $plan['locations'])), 'ambiguous_centers' => $plan['ambiguous_centers'], 'ambiguous_institutes' => $plan['ambiguous_institutes'], 'unmatched_institutes' => $plan['unmatched_institutes']];
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    private function assertReferenceSyncSchema(PDO $target): void
    {
        $db = (string) $this->targetSettings()['database'];
        foreach (['centers'=>'user_id','divisions'=>'user_id','districts'=>'user_id','upozilas'=>'user_id','unions'=>'user_id'] as $table=>$column) {
            $stmt=$target->prepare('SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$db,$table,$column]);
            if ($stmt->fetchColumn() !== 'YES') { throw new RuntimeException('Apply the pending center and location reference sync migrations on demo, then preview again.'); }
        }
        $stmt=$target->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = \'centers\' AND COLUMN_NAME = \'display_name\'');
        $stmt->execute([$db]);
        if ((int)$stmt->fetchColumn()===0) {
            throw new RuntimeException('Apply the pending center display-name migration on demo, then preview again.');
        }
    }

    /** @return array{centers:array,institutes:array,center_map:array,ambiguous_centers:int,ambiguous_institutes:int,unmatched_institutes:int} */
    private function referenceSyncPlan(PDO $source, PDO $target): array
    {
        $centerSql = 'SELECT c.`id`,c.`code`,c.`institute_name`,c.`division_id`,c.`district_id`,c.`upazila_id`,c.`union_id`,c.`active`,u.`name` AS `name`,d.`name` AS `division_name`,di.`name` AS `district_name`,up.`name` AS `upazila_name`,un.`name` AS `union_name` FROM `centers` c LEFT JOIN `users` u ON u.`id`=c.`user_id` LEFT JOIN `divisions` d ON d.`id`=c.`division_id` LEFT JOIN `districts` di ON di.`id`=c.`district_id` LEFT JOIN `upozilas` up ON up.`id`=c.`upazila_id` LEFT JOIN `unions` un ON un.`id`=c.`union_id`';
        $sourceCenters = $source->query($centerSql)->fetchAll(PDO::FETCH_ASSOC);
        $targetCenters = $target->query(str_replace('u.`name` AS `name`', 'COALESCE(NULLIF(c.`display_name`,\'\'),u.`name`) AS `name`', $centerSql))->fetchAll(PDO::FETCH_ASSOC);
        $sourceByCode = $targetByCode = [];
        foreach ($sourceCenters as $r) { $k = $this->normalizeCenterIdentity($r['code'] ?? ''); if ($k !== '') $sourceByCode[$k][] = $r; }
        foreach ($targetCenters as $r) { $k = $this->normalizeCenterIdentity($r['code'] ?? ''); if ($k !== '') $targetByCode[$k][] = $r; }
        $centerMap = $centerPlans = [];
        $ambiguousCenters = 0;
        foreach ($sourceByCode as $code => $sources) {
            $targets = $targetByCode[$code] ?? [];
            if (count($targets) === 1 && count($sources) === 1) { $centerMap[(int)$sources[0]['id']] = (int)$targets[0]['id']; continue; }
            if ($targets !== []) {
                foreach ($sources as $s) {
                    $identity = $this->centerSecondaryIdentity($s);
                    $matches = $identity === null ? [] : array_values(array_filter($targets, fn($t) => $this->centerSecondaryIdentity($t) === $identity));
                    if (count($matches) === 1) { $centerMap[(int)$s['id']] = (int)$matches[0]['id']; }
                    else { $ambiguousCenters++; }
                }
                continue;
            }
            $identities = [];
            foreach ($sources as $s) { $identities[$this->centerSecondaryIdentity($s) ?? ''][] = $s; }
            foreach ($sources as $s) {
                $name = trim((string)($s['name'] ?? ''));
                $identity = $this->centerSecondaryIdentity($s);
                if ($name === '' || $identity === null || count($identities[$identity] ?? []) !== 1) { $ambiguousCenters++; continue; }
                $centerPlans[] = ['source_id'=>(int)$s['id'],'code'=>(string)$s['code'],'name'=>$name,'venue'=>(string)($s['institute_name']??''),'active'=>(int)($s['active']??1),'division_id'=>null,'district_id'=>null,'upazila_id'=>null,'union_id'=>null];
                $centerMap[(int)$s['id']] = -count($centerPlans);
            }
        }
        // Map geographic IDs by unique name under mapped parents, leaving unknown IDs NULL.
        $geos = [];
        foreach (['divisions'=>['name'],'districts'=>['name','division_id'],'upozilas'=>['name','district_id'],'unions'=>['name','upozila_id']] as $table=>$cols) {
            $geos[$table] = [$this->lookupAllIds($source,$table,$cols),$this->lookupAllIds($target,$table,$cols)];
        }
        $token = -1000000000; $ambiguousLocations = 0; $unmatchedLocations = 0;
        [$divisionMap,$divisionPlans] = $this->planMissingLocations($geos['divisions'][0],$geos['divisions'][1],null,null,[], $token,$ambiguousLocations,$unmatchedLocations);
        [$districtMap,$districtPlans] = $this->planMissingLocations($geos['districts'][0],$geos['districts'][1],'division_id','divisions',$divisionMap,$token,$ambiguousLocations,$unmatchedLocations);
        [$upazilaMap,$upazilaPlans] = $this->planMissingLocations($geos['upozilas'][0],$geos['upozilas'][1],'district_id','districts',$districtMap,$token,$ambiguousLocations,$unmatchedLocations);
        [$unionMap,$unionPlans] = $this->planMissingLocations($geos['unions'][0],$geos['unions'][1],'upozila_id','upozilas',$upazilaMap,$token,$ambiguousLocations,$unmatchedLocations);
        $locationMaps=['divisions'=>$divisionMap,'districts'=>$districtMap,'upozilas'=>$upazilaMap,'unions'=>$unionMap];
        $locationPlans=['divisions'=>$divisionPlans,'districts'=>$districtPlans,'upozilas'=>$upazilaPlans,'unions'=>$unionPlans];
        $maps = ['division_id'=>$divisionMap,'district_id'=>$districtMap,'upazila_id'=>$upazilaMap,'union_id'=>$unionMap];
        foreach ($centerPlans as &$p) {
            $sourceRow = null; foreach ($sourceCenters as $s) { if ((int)$s['id']===$p['source_id']) { $sourceRow=$s; break; } }
            foreach ($maps as $field=>$map) { $p[$field] = $map[(int)($sourceRow[$field]??0)] ?? null; }
        } unset($p);

        $sourceInstitutes = $source->query('SELECT `id`,`center_id`,`name`,`certificate_no`,`division_id`,`district_id`,`upazila_id`,`union_id`,`active` FROM `institutes`')->fetchAll(PDO::FETCH_ASSOC);
        $targetInstitutes = $target->query('SELECT `id`,`center_id`,`name`,`certificate_no` FROM `institutes`')->fetchAll(PDO::FETCH_ASSOC);
        $sourceCert = $targetCert = [];
        $sourceNameCount = [];
        foreach ($sourceInstitutes as $r) { $k=$this->normalizeCenterIdentity($r['certificate_no']??''); if($k!=='')$sourceCert[$k][]=$r; }
        foreach ($sourceInstitutes as $r) { $key=(int)($r['center_id']??0)."\x1F".$this->normalizeCenterIdentity($r['name']??''); if (!str_ends_with($key,"\x1F")) $sourceNameCount[$key]=($sourceNameCount[$key]??0)+1; }
        foreach ($targetInstitutes as $r) { $k=$this->normalizeCenterIdentity($r['certificate_no']??''); if($k!=='')$targetCert[$k][]=$r; }
        $institutePlans=[]; $ambiguousInstitutes=0; $unmatchedInstitutes=0;
        foreach ($sourceInstitutes as $r) {
            $sourceCenter=(int)($r['center_id']??0); $demoCenter=$centerMap[$sourceCenter]??null;
            if ($demoCenter === null) { $unmatchedInstitutes++; continue; }
            $cert=$this->normalizeCenterIdentity($r['certificate_no']??'');
            if ($cert!=='' && count($targetCert[$cert]??[])===1) {
                $found = reset($targetCert[$cert]);
                if ($demoCenter !== null && $demoCenter > 0 && (int)($found['center_id']??0) === $demoCenter) { continue; }
                $ambiguousInstitutes++; continue;
            }
            if ($cert!=='' && count($targetCert[$cert]??[])>1) { $ambiguousInstitutes++; continue; }
            if ($cert!=='' && count($sourceCert[$cert]??[])>1) { $ambiguousInstitutes++; continue; }
            $centerId=$demoCenter>0?$demoCenter:null;
            $name=trim((string)$r['name']); if ($name==='') {$unmatchedInstitutes++;continue;}
            if (($sourceNameCount[$sourceCenter."\x1F".$this->normalizeCenterIdentity($name)]??0)>1) { $ambiguousInstitutes++; continue; }
            if ($centerId!==null) {
                $same=array_values(array_filter($targetInstitutes,fn($t)=>(int)($t['center_id']??0)===$centerId && $this->sameReference($t['name'],$name)));
                if (count($same)===1) { continue; }
                if (count($same)>1) { $ambiguousInstitutes++; continue; }
            } else {
                // Institutes under a not-yet-created center are safely keyed by a unique center+name pair in source.
                $same=array_values(array_filter($sourceInstitutes,fn($s)=>(int)($s['center_id']??0)===$sourceCenter && $this->sameReference($s['name'],$name)));
                if (count($same)>1) { $ambiguousInstitutes++; continue; }
            }
            $mappedGeo=[]; foreach($maps as $field=>$map) { $mappedGeo[$field]=$map[(int)($r[$field]??0)]??null; }
            $centerRow=$sourceCenters[array_search($sourceCenter,array_column($sourceCenters,'id'))]??[];
            $institutePlans[]=['source_id'=>(int)$r['id'],'source_center_id'=>$sourceCenter,'name'=>$name,'certificate_no'=>($r['certificate_no']??null)?:null,'active'=>(int)($r['active']??1),'center_name'=>(string)($centerRow['name']??''),'division_id'=>$mappedGeo['division_id'],'district_id'=>$mappedGeo['district_id'],'upazila_id'=>$mappedGeo['upazila_id'],'union_id'=>$mappedGeo['union_id']];
        }
        return ['centers'=>$centerPlans,'institutes'=>$institutePlans,'locations'=>$locationPlans,'location_maps'=>$locationMaps,'ambiguous_locations'=>$ambiguousLocations,'unmatched_locations'=>$unmatchedLocations,'center_map'=>$centerMap,'ambiguous_centers'=>$ambiguousCenters,'ambiguous_institutes'=>$ambiguousInstitutes,'unmatched_institutes'=>$unmatchedInstitutes];
    }

    private function planMissingLocations(array $sourceRows,array $targetRows,?string $parentColumn,?string $parentTable,array $parentMap,int &$token,int &$ambiguous,int &$unmatched): array
    {
        $sourceGroups=[]; $targetGroups=[];
        foreach($sourceRows as $id=>$row){
            $name=$this->normalizeCenterIdentity($row['name']??''); if($name===''){ $unmatched++; continue; }
            $parentTarget=null;
            if($parentColumn!==null){ $parentTarget=$parentMap[(int)($row[$parentColumn]??0)]??null; if($parentTarget===null){$unmatched++;continue;} }
            $key=$name."\x1F".(string)($parentTarget??0); $sourceGroups[$key][]=['id'=>(int)$id,'row'=>$row,'parent_target'=>$parentTarget,'name'=>$name];
        }
        foreach($targetRows as $id=>$row){
            $name=$this->normalizeCenterIdentity($row['name']??''); if($name==='')continue;
            $parent=$parentColumn===null?null:(int)($row[$parentColumn]??0);
            $targetGroups[$name."\x1F".(string)($parent??0)][]=(int)$id;
        }
        $map=[];$plans=[];
        foreach($sourceGroups as $key=>$group){
            $targets=$targetGroups[$key]??[];
            if(count($group)===1 && count($targets)===1){$map[$group[0]['id']]=$targets[0];continue;}
            if($targets!==[]){$ambiguous+=count($group);continue;}
            // District names are globally unique in this schema; a same-name row under another division is a conflict.
            if($parentTable==='divisions'){
                $sameName=array_filter($targetRows,fn($r)=>$this->sameReference($r['name']??'',$group[0]['row']['name']??''));
                if($sameName!==[]){$ambiguous+=count($group);continue;}
            }
            if(count($group)!==1){$ambiguous+=count($group);continue;}
            $entry=$group[0]; $token--;
            $map[$entry['id']]=$token;
            $plans[]=['source_id'=>$entry['id'],'name'=>(string)$entry['row']['name'],'parent_column'=>$parentColumn,'parent_table'=>$parentTable,'source_parent_id'=>$parentColumn===null?null:(int)$entry['row'][$parentColumn],'parent_target_id'=>$entry['parent_target'],'token'=>$token];
        }
        return [$map,$plans];
    }

    private function assertCenterDisplayNameSchema(PDO $target): void
    {
        $check = $target->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = \'centers\' AND COLUMN_NAME = \'display_name\'');
        $check->execute([(string) $this->targetSettings()['database']]);
        if ((int) $check->fetchColumn() === 0) {
            throw new RuntimeException('Apply the pending `center_display_name` demo schema migration first, then preview center names again.');
        }
        $check = $target->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = \'centers\' AND COLUMN_NAME IN (\'contact_phone\', \'contact_email\')');
        $check->execute([(string) $this->targetSettings()['database']]);
        if ((int) $check->fetchColumn() !== 2) {
            throw new RuntimeException('Apply the pending center contact sync migration on demo first, then preview center updates again.');
        }
    }

    /** @return array{updates:array<int,array<string,mixed>>,matched:int,ambiguous:int,unmatched:int} */
    private function centerNameSyncPlan(PDO $source, PDO $target): array
    {
        $selectCenters = 'SELECT c.`id`, c.`code`, c.`institute_name`, c.`authority_person`, c.`division_id`, c.`district_id`, c.`upazila_id`, c.`union_id`, '
            . 'd.`name` AS `division_name`, di.`name` AS `district_name`, up.`name` AS `upazila_name`, un.`name` AS `union_name`';
        $joins = ' FROM `centers` c LEFT JOIN `divisions` d ON d.`id` = c.`division_id` '
            . 'LEFT JOIN `districts` di ON di.`id` = c.`district_id` '
            . 'LEFT JOIN `upozilas` up ON up.`id` = c.`upazila_id` '
            . 'LEFT JOIN `unions` un ON un.`id` = c.`union_id` ';
        $sourceRows = $source->query($selectCenters . ', u.`name` AS `source_name`, u.`type`, u.`phone` AS `source_phone`, u.`email` AS `source_email`' . $joins . 'LEFT JOIN `users` u ON u.`id` = c.`user_id`')->fetchAll(PDO::FETCH_ASSOC);
        $targetRows = $target->query($selectCenters . ', c.`display_name`, c.`contact_phone`, c.`contact_email`, u.`name` AS `user_name`' . $joins . 'LEFT JOIN `users` u ON u.`id` = c.`user_id`')->fetchAll(PDO::FETCH_ASSOC);
        $byCode = static function (array $rows): array {
            $grouped = [];
            foreach ($rows as $row) {
                $code = mb_strtolower(trim((string) ($row['code'] ?? '')), 'UTF-8');
                if ($code !== '') { $grouped[$code][] = $row; }
            }
            return $grouped;
        };
        $sourceByCode = $byCode($sourceRows);
        $targetByCode = $byCode($targetRows);
        $updates = [];
        $matched = 0;
        $ambiguous = 0;
        $unmatched = 0;
        $codes = array_unique(array_merge(array_keys($sourceByCode), array_keys($targetByCode)));
        foreach ($codes as $code) {
            $sourceMatches = $sourceByCode[$code] ?? [];
            $targetMatches = $targetByCode[$code] ?? [];
            if ($sourceMatches === []) { continue; }
            $pairs = [];
            if (count($sourceMatches) === 1 && count($targetMatches) === 1) {
                $pairs[] = [$sourceMatches[0], $targetMatches[0]];
            } elseif ($targetMatches === []) {
                $unmatched += count($sourceMatches);
                continue;
            } else {
                $sourceSecondary = [];
                $targetSecondary = [];
                foreach ($sourceMatches as $row) {
                    $identity = $this->centerSecondaryIdentity($row);
                    if ($identity !== null) { $sourceSecondary[$identity][] = $row; }
                }
                foreach ($targetMatches as $row) {
                    $identity = $this->centerSecondaryIdentity($row);
                    if ($identity !== null) { $targetSecondary[$identity][] = $row; }
                }
                foreach ($sourceMatches as $sourceRow) {
                    $identity = $this->centerSecondaryIdentity($sourceRow);
                    if ($identity === null || !isset($targetSecondary[$identity])) { $unmatched++; continue; }
                    if (count($sourceSecondary[$identity] ?? []) !== 1 || count($targetSecondary[$identity]) !== 1) { $ambiguous++; continue; }
                    $pairs[] = [$sourceRow, $targetSecondary[$identity][0]];
                }
            }
            foreach ($pairs as [$sourceRow, $targetRow]) {
                $sourceName = trim((string) ($sourceRow['source_name'] ?? ''));
                if ($sourceName === '' || (int) ($sourceRow['type'] ?? -1) !== 4) { $unmatched++; continue; }
                $matched++;
                $targetCenterId = (int) $targetRow['id'];
                $storedName = $targetRow['display_name'];
                $oldPhone = $targetRow['contact_phone'];
                $oldEmail = $targetRow['contact_email'];
                $oldAuthority = $targetRow['authority_person'];
                $sourcePhone = trim((string) ($sourceRow['source_phone'] ?? ''));
                $sourceEmail = trim((string) ($sourceRow['source_email'] ?? ''));
                $sourceAuthority = trim((string) ($sourceRow['authority_person'] ?? ''));
                // Blank source values must never erase a contact already in demo.
                $newPhone = $sourcePhone !== '' ? $sourcePhone : $oldPhone;
                $newEmail = $sourceEmail !== '' ? $sourceEmail : $oldEmail;
                $newAuthority = $sourceAuthority !== '' ? $sourceAuthority : $oldAuthority;
                $oldName = trim((string) $storedName) !== ''
                    ? trim((string) $storedName)
                    : trim((string) ($targetRow['user_name'] ?? ''));
                if ($oldName !== $sourceName || $oldAuthority !== $newAuthority || $oldPhone !== $newPhone || $oldEmail !== $newEmail) {
                    $updates[$targetCenterId] = [
                        'center_id' => $targetCenterId,
                        'code' => (string) $targetRow['code'],
                        'old_name' => $oldName,
                        'stored_name' => $storedName,
                        'new_name' => $sourceName,
                        'old_authority' => $oldAuthority,
                        'new_authority' => $newAuthority,
                        'old_phone' => $oldPhone,
                        'new_phone' => $newPhone,
                        'old_email' => $oldEmail,
                        'new_email' => $newEmail,
                    ];
                }
            }
        }
        return ['updates' => array_values($updates), 'matched' => $matched, 'ambiguous' => $ambiguous, 'unmatched' => $unmatched];
    }

    private function centerSecondaryIdentity(array $row): ?string
    {
        $venue = $this->normalizeCenterIdentity($row['institute_name'] ?? '');
        $division = $this->normalizeCenterIdentity($row['division_name'] ?? '');
        $district = $this->normalizeCenterIdentity($row['district_name'] ?? '');
        $upazila = $this->normalizeCenterIdentity($row['upazila_name'] ?? '');
        $union = $this->normalizeCenterIdentity($row['union_name'] ?? '');
        if ($venue === '' || ($district === '' && $upazila === '')) { return null; }
        return implode("\x1F", [$venue, $division, $district, $upazila, $union]);
    }

    private function normalizeCenterIdentity(mixed $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    /** Count missing registration numbers without transferring any records. */
    public function previewStudentImport(): array
    {
        $this->assertReady();
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            $total = (int) $source->query('SELECT COUNT(*) FROM `students`')->fetchColumn();
            $sourceRegistrations = $source->query("SELECT `registration` FROM `students` WHERE `registration` IS NOT NULL AND TRIM(`registration`) <> ''")->fetchAll(PDO::FETCH_COLUMN);
            $targetRegistrations = $target->query("SELECT `registration` FROM `students` WHERE `registration` IS NOT NULL AND TRIM(`registration`) <> ''")->fetchAll(PDO::FETCH_COLUMN);
            $targetSet = [];
            foreach ($targetRegistrations as $registration) {
                $targetSet[mb_strtolower(trim((string) $registration), 'UTF-8')] = true;
            }
            $seen = [];
            $existing = 0;
            $missing = 0;
            foreach ($sourceRegistrations as $registration) {
                $key = mb_strtolower(trim((string) $registration), 'UTF-8');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                if (isset($targetSet[$key])) { $existing++; } else { $missing++; }
            }
            return [
                'source_total' => $total,
                'already_in_demo' => $existing,
                'missing_in_demo' => $missing,
                'missing_registration' => $total - count($sourceRegistrations),
                'note' => 'Preview checks registration numbers only. During import, center and institute references are matched by unique stable identifiers (center code or name/venue; institute certificate number or name within the matched center). Ambiguous, unmatched, conflicting-roll, and unsafe references are skipped.',
            ];
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    /** Preview roll numbers that can safely fill blank demo rows for one session. */
    public function previewStudentRollSync(int $session): array
    {
        $this->assertReady();
        if ($session <= 0) { throw new RuntimeException('Select a valid active exam session.'); }
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            $plan = $this->studentRollSyncPlan($source, $target, $session);
            return [
                'session' => $session,
                'changes' => count($plan['updates']),
                'matched' => $plan['matched'],
                'already_set' => $plan['already_set'],
                'no_source_roll' => $plan['no_source_roll'],
                'unmatched' => $plan['unmatched'],
                'ambiguous' => $plan['ambiguous'],
                'class_mismatch' => $plan['class_mismatch'],
                'conflicts' => $plan['conflicts'],
                'examples' => array_slice(array_map(static fn(array $row): array => [
                    'registration' => $row['registration'], 'student' => $row['name'],
                    'class' => $row['class'], 'from' => $row['old_roll'], 'to' => $row['new_roll'],
                ], $plan['updates']), 0, 100),
                'note' => 'Matches registration number and session. Only blank demo roll numbers are filled; existing rolls are never overwritten. Duplicate, ambiguous, unmatched, class-mismatched, or conflicting rolls are skipped. Exam remains read-only.',
            ];
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    /** Fill only safe blank demo roll numbers for one session; never edit exam. */
    public function syncStudentRolls(int $session): array
    {
        $this->assertReady();
        if ($session <= 0) { throw new RuntimeException('Select a valid active exam session.'); }
        $source = $this->connectSource();
        $target = $this->connectTarget();
        $lockAcquired = false;
        try {
            $target->beginTransaction();
            try {
                $lockAcquired = (int) $target->query("SELECT GET_LOCK('codex_student_roll_sync', 10)")->fetchColumn() === 1;
                if (!$lockAcquired) { throw new RuntimeException('Another student roll sync is running. Wait a moment and retry.'); }
                $plan = $this->studentRollSyncPlan($source, $target, $session);
                $update = $target->prepare("UPDATE `students` SET `roll` = ? WHERE `id` = ? AND `session` = ? AND `class` <=> ? AND TRIM(`registration`) = ? AND (`roll` IS NULL OR TRIM(CAST(`roll` AS CHAR)) = '')");
                $updated = 0;
                foreach ($plan['updates'] as $row) {
                    $update->execute([$row['new_roll'], $row['target_id'], $session, $row['class'], $row['registration']]);
                    $updated += $update->rowCount();
                }
                $target->commit();
                return [
                    'session' => $session, 'updated' => $updated, 'matched' => $plan['matched'],
                    'already_set' => $plan['already_set'], 'no_source_roll' => $plan['no_source_roll'],
                    'unmatched' => $plan['unmatched'], 'ambiguous' => $plan['ambiguous'],
                    'class_mismatch' => $plan['class_mismatch'], 'conflicts' => $plan['conflicts'],
                ];
            } catch (\Throwable $e) {
                if ($target->inTransaction()) { $target->rollBack(); }
                throw $e;
            }
        } finally {
            if ($lockAcquired) {
                try { $target->query("SELECT RELEASE_LOCK('codex_student_roll_sync')"); } catch (\Throwable) {}
            }
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    /** @return array{updates:array<int,array<string,mixed>>,matched:int,already_set:int,no_source_roll:int,unmatched:int,ambiguous:int,class_mismatch:int,conflicts:int} */
    private function studentRollSyncPlan(PDO $source, PDO $target, int $session): array
    {
        $sourceStmt = $source->prepare("SELECT `id`,`registration`,`name`,`class`,`session`,`roll` FROM `students` WHERE `session` = ? AND `registration` IS NOT NULL AND TRIM(`registration`) <> ''");
        $sourceStmt->execute([$session]);
        $sourceRows = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);
        $targetStmt = $target->prepare("SELECT `id`,`registration`,`name`,`class`,`session`,`roll` FROM `students` WHERE `session` = ? AND `registration` IS NOT NULL AND TRIM(`registration`) <> ''");
        $targetStmt->execute([$session]);
        $targetRows = $targetStmt->fetchAll(PDO::FETCH_ASSOC);

        $regKey = static fn(array $row): string => (int) $row['session'] . ':' . mb_strtolower(trim((string) $row['registration']), 'UTF-8');
        $rollValue = static fn(array $row): string => trim((string) ($row['roll'] ?? ''));
        $rollKey = static fn(array $row): ?string => $rollValue($row) === '' ? null : implode(':', [(int) $row['session'], trim((string) $row['class']), $rollValue($row)]);
        $sourceByReg = $targetByReg = $sourceRollRegistrations = $targetRolls = [];
        foreach ($sourceRows as $row) {
            $sourceByReg[$regKey($row)][] = $row;
            $key = $rollKey($row);
            if ($key !== null) { $sourceRollRegistrations[$key][$regKey($row)] = true; }
        }
        foreach ($targetRows as $row) {
            $targetByReg[$regKey($row)][] = $row;
            $key = $rollKey($row);
            if ($key !== null) { $targetRolls[$key] = true; }
        }

        $updates = []; $matched = $alreadySet = $noSourceRoll = $unmatched = $ambiguous = $classMismatch = $conflicts = 0;
        foreach ($sourceByReg as $key => $sources) {
            $sourceWithRoll = array_values(array_filter($sources, static fn(array $row): bool => $rollValue($row) !== ''));
            $targets = $targetByReg[$key] ?? [];
            if ($sourceWithRoll === []) {
                if ($targets !== []) { $noSourceRoll += count($sources); }
                continue;
            }
            if ($targets === []) { $unmatched += count($sourceWithRoll); continue; }
            if (count($sources) !== 1 || count($targets) !== 1) { $ambiguous += count($sourceWithRoll); continue; }
            $sourceRow = $sourceWithRoll[0];
            $targetRow = $targets[0];
            $matched++;
            if ($rollValue($targetRow) !== '') { $alreadySet++; continue; }
            if ((string) $sourceRow['class'] !== (string) $targetRow['class']) { $classMismatch++; continue; }
            $keyRoll = $rollKey($sourceRow);
            if ($keyRoll === null) { $noSourceRoll++; continue; }
            $otherSourceRegistrations = array_diff(array_keys($sourceRollRegistrations[$keyRoll] ?? []), [$key]);
            if ($otherSourceRegistrations !== [] || isset($targetRolls[$keyRoll])) { $conflicts++; continue; }
            $updates[] = [
                'target_id' => (int) $targetRow['id'], 'registration' => trim((string) $sourceRow['registration']),
                'name' => (string) ($sourceRow['name'] ?? ''), 'class' => $sourceRow['class'],
                'old_roll' => $targetRow['roll'], 'new_roll' => $sourceRow['roll'],
            ];
            $targetRolls[$keyRoll] = true;
        }
        return [
            'updates' => $updates, 'matched' => $matched, 'already_set' => $alreadySet,
            'no_source_roll' => $noSourceRoll, 'unmatched' => $unmatched,
            'ambiguous' => $ambiguous, 'class_mismatch' => $classMismatch,
            'conflicts' => $conflicts,
        ];
    }

    /** Import only missing registrations; only the demo connection is written. */
    public function importStudentBatch(int $afterId = 0, int $batchSize = 1000): array
    {
        $this->assertReady();
        // Larger batches substantially reduce the per-request connection and
        // metadata overhead when scanning a large source database.
        $batchSize = max(1, min(1000, $batchSize));
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            // Determine missing registrations from the narrow indexed key
            // column first. The old flow loaded every full student row in
            // 1,000-row batches and made one browser request per batch, even
            // when only a handful of registrations were missing.
            $targetRegistrations = $target->query("SELECT `registration` FROM `students` WHERE `registration` IS NOT NULL AND TRIM(`registration`) <> ''")->fetchAll(PDO::FETCH_COLUMN);
            $presentRegistrations = [];
            foreach ($targetRegistrations as $registration) {
                $presentRegistrations[mb_strtolower(trim((string) $registration), 'UTF-8')] = true;
            }
            $sourceRegistrations = $source->query("SELECT `id`, `registration` FROM `students` WHERE `registration` IS NOT NULL AND TRIM(`registration`) <> '' ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
            $missingIds = [];
            $seenRegistrations = [];
            foreach ($sourceRegistrations as $sourceRegistration) {
                $key = mb_strtolower(trim((string) $sourceRegistration['registration']), 'UTF-8');
                if ($key === '' || isset($seenRegistrations[$key])) { continue; }
                $seenRegistrations[$key] = true;
                $sourceId = (int) $sourceRegistration['id'];
                if ($sourceId > max(0, $afterId) && !isset($presentRegistrations[$key])) {
                    $missingIds[] = $sourceId;
                    if (count($missingIds) > $batchSize) { break; }
                }
            }

            $hasMoreMissing = count($missingIds) > $batchSize;
            $missingIds = array_slice($missingIds, 0, $batchSize);
            $rows = [];
            foreach (array_chunk($missingIds, 500) as $idChunk) {
                $sourceStmt = $source->prepare('SELECT * FROM `students` WHERE `id` IN (' . implode(',', array_fill(0, count($idChunk), '?')) . ') ORDER BY `id` ASC');
                $sourceStmt->execute($idChunk);
                array_push($rows, ...$sourceStmt->fetchAll(PDO::FETCH_ASSOC));
            }
            if ($rows === []) {
                return ['done' => true, 'next_after_id' => $afterId, 'processed' => 0, 'imported' => 0, 'already_present' => 0, 'skipped' => 0];
            }

            $originalRowsById = [];
            foreach ($rows as $sourceRow) { $originalRowsById[(int) $sourceRow['id']] = $sourceRow; }
            $this->assertStudentColumnsCompatible($source, $target);
            $referenceMaps = $this->studentReferenceMaps($source, $target, $rows);
            $unmappedIds = [];
            foreach ($rows as &$row) {
                $referenceFailure = '';
                $mapped = $this->remapStudentReferences($row, $referenceMaps, $referenceFailure);
                if ($mapped === null) { $unmappedIds[(int) $row['id']] = $referenceFailure; }
                else { $row = $mapped; }
            }
            unset($row);
            $registrations = [];
            $fingerprints = [];
            $rollConditions = [];
            foreach ($rows as $row) {
                if (trim((string) ($row['registration'] ?? '')) !== '') { $registrations[] = trim((string) $row['registration']); }
                if (!isset($unmappedIds[(int) $row['id']])) { $fingerprints[] = \App\Services\StudentService::duplicateFingerprint($row); }
                if (($row['roll'] ?? null) !== null && ($row['session'] ?? null) !== null && ($row['class'] ?? null) !== null) {
                    $rollConditions[] = [(int) $row['session'], (int) $row['roll'], (int) $row['class']];
                }
            }
            $presentRegistrations = $this->existingValues($target, 'students', 'registration', $registrations);
            $duplicateKeysAvailable = $this->tableExists($target, (string) $this->targetSettings()['database'], 'student_duplicate_keys');
            $presentFingerprints = $duplicateKeysAvailable
                ? $this->existingValues($target, 'student_duplicate_keys', 'fingerprint', $fingerprints)
                : [];
            // The duplicate-key table is backfilled by its migration and is
            // maintained by student writes. Avoid the much slower row-by-row
            // fallback query when that indexed source of truth is available.
            if (!$duplicateKeysAvailable) {
                $mappedRows = array_values(array_filter($rows, static fn(array $row): bool => !isset($unmappedIds[(int) $row['id']])));
                foreach ($this->existingStudentFingerprints($target, $mappedRows) as $fingerprint => $_present) {
                    $presentFingerprints[$fingerprint] = true;
                }
            }
            $presentRolls = $this->existingRolls($target, $rollConditions);
            $columns = $this->importStudentColumns($rows[0]);
            $manualStudentIds = !$this->studentIdAutoIncrements($target);
            if ($manualStudentIds) {
                array_unshift($columns, 'id');
            }
            $quotedColumns = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
            $insert = $target->prepare('INSERT INTO `students` (' . $quotedColumns . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
            $keyInsert = $duplicateKeysAvailable ? $target->prepare('INSERT IGNORE INTO `student_duplicate_keys` (`fingerprint`) VALUES (?)') : null;
            $counts = ['imported' => 0, 'already_present' => 0, 'skipped' => 0];
            $skippedDetails = [];
            $alreadyPresentDetails = [];
            $target->beginTransaction();
            try {
                $nextStudentId = null;
                if ($manualStudentIds) {
                    // Lock the highest primary-key row so concurrent import batches
                    // cannot allocate the same IDs on schemas without AUTO_INCREMENT.
                    $lastId = $target->query('SELECT `id` FROM `students` ORDER BY `id` DESC LIMIT 1 FOR UPDATE')->fetchColumn();
                    $nextStudentId = ((int) $lastId) + 1;
                }
                foreach ($rows as $row) {
                    $registration = trim((string) ($row['registration'] ?? ''));
                    if ($registration === '') {
                        $counts['skipped']++;
                        $skippedDetails[] = $this->studentImportSkipDetail($row, 'Missing registration number.', $referenceMaps);
                        continue;
                    }
                    if (isset($unmappedIds[(int) $row['id']])) {
                        $counts['skipped']++;
                        $skippedDetails[] = $this->studentImportSkipDetail($row, $unmappedIds[(int) $row['id']], $referenceMaps);
                        continue;
                    }
                    $regKey = mb_strtolower($registration, 'UTF-8');
                    if (isset($presentRegistrations[$regKey])) {
                        $counts['already_present']++;
                        $alreadyPresentDetails[] = $this->studentImportSkipDetail(
                            $originalRowsById[(int) $row['id']] ?? $row,
                            'Registration number already exists in demo.',
                            $referenceMaps
                        );
                        continue;
                    }
                    $fingerprint = \App\Services\StudentService::duplicateFingerprint($row);
                    if (isset($presentFingerprints[$fingerprint])) {
                        $counts['already_present']++;
                        $alreadyPresentDetails[] = $this->studentImportSkipDetail(
                            $originalRowsById[(int) $row['id']] ?? $row,
                            'Matching student details already exist in demo.',
                            $referenceMaps
                        );
                        continue;
                    }
                    $rollKey = $this->rollKey($row);
                    if ($rollKey !== null && isset($presentRolls[$rollKey])) {
                        $counts['skipped']++;
                        $skippedDetails[] = $this->studentImportSkipDetail($row, 'Another demo student already uses this session, class, and roll.', $referenceMaps);
                        continue;
                    }
                    $values = [];
                    foreach ($columns as $column) {
                        if ($manualStudentIds && $column === 'id') { continue; }
                        $values[] = $row[$column] ?? null;
                    }
                    if ($manualStudentIds) {
                        array_unshift($values, $nextStudentId++);
                    }
                    try {
                        $insert->execute($values);
                    } catch (\PDOException $e) {
                        if (($e->errorInfo[1] ?? 0) === 1062) {
                            $counts['skipped']++;
                            $skippedDetails[] = $this->studentImportSkipDetail($row, 'A unique-value conflict occurred while inserting this registration.', $referenceMaps);
                            continue;
                        }
                        throw $e;
                    }
                    if ($keyInsert !== null) { $keyInsert->execute([$fingerprint]); }
                    $presentRegistrations[$regKey] = true;
                    $presentFingerprints[$fingerprint] = true;
                    if ($rollKey !== null) { $presentRolls[$rollKey] = true; }
                    $counts['imported']++;
                }
                $target->commit();
            } catch (\Throwable $e) {
                if ($target->inTransaction()) { $target->rollBack(); }
                throw $e;
            }
            if ($counts['imported'] > 0) {
                \App\Core\Cache::forgetPrefix('pending_students');
            }
            $lastId = (int) $rows[array_key_last($rows)]['id'];
            return ['done' => !$hasMoreMissing, 'next_after_id' => $lastId, 'processed' => count($rows), 'skipped_details' => $skippedDetails, 'already_present_details' => $alreadyPresentDetails] + $counts;
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    private function assertStudentColumnsCompatible(PDO $source, PDO $target): void
    {
        $sourceColumns = $this->columnDefinitions($source, (string) $this->sourceSettings()['database']);
        $targetColumns = $this->columnDefinitions($target, (string) $this->targetSettings()['database']);
        foreach ($sourceColumns['students'] ?? [] as $column => $definition) {
            if ($column === 'id') { continue; }
            if (!isset($targetColumns['students'][$column]) || $targetColumns['students'][$column] !== $definition) {
                $targetDefinition = $targetColumns['students'][$column] ?? '<missing>';
                $sourceSettings = $this->sourceSettings();
                $targetSettings = $this->targetSettings();
                throw new RuntimeException(sprintf(
                    'Student import is paused: students.%s differs. Exam source (%s:%d/%s): [%s]. Destination (%s:%d/%s): [%s].',
                    $column,
                    $sourceSettings['host'],
                    $sourceSettings['port'],
                    $sourceSettings['database'],
                    $definition,
                    $targetSettings['host'],
                    $targetSettings['port'],
                    $targetSettings['database'],
                    $targetDefinition
                ));
            }
        }
    }

    private function columnDefinitions(PDO $pdo, string $schema): array
    {
        $stmt = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?');
        $stmt->execute([$schema]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $columnType = strtolower((string) $row['COLUMN_TYPE']);
            // MySQL 8 omits the legacy display width for integer types, while
            // older MySQL/MariaDB versions may report e.g. int(11). Width does
            // not change the integer range; keep modifiers such as UNSIGNED.
            if (!str_contains($columnType, 'zerofill')) {
                $columnType = preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', $columnType) ?? $columnType;
            }
            $columnDefault = $row['COLUMN_DEFAULT'];
            // MySQL reports DEFAULT NULL for a nullable column as SQL NULL,
            // while MariaDB may expose the same nullable default as the
            // unquoted string "NULL". MariaDB quotes a literal string default
            // as 'NULL', so normalize only its unquoted nullable form here.
            if ($columnDefault === null
                || (($row['IS_NULLABLE'] ?? '') === 'YES' && $columnDefault === 'NULL')) {
                $columnDefault = '<NULL>';
            } else {
                $columnDefault = (string) $columnDefault;
            }
            $out[$row['TABLE_NAME']][$row['COLUMN_NAME']] = implode('|', [
                $columnType, $row['IS_NULLABLE'], $columnDefault,
                $row['EXTRA'], $row['CHARACTER_SET_NAME'] ?? '', $row['COLLATION_NAME'] ?? '',
            ]);
        }
        return $out;
    }

    private function importStudentColumns(array $row): array
    {
        $excluded = ['id', 'user_id', 'photo', 'bangla', 'english', 'math', 'total', 'percentage', 'grade', 'ml', 'place_id', 'marker_id'];
        return array_values(array_diff(array_keys($row), $excluded));
    }

    private function studentIdAutoIncrements(PDO $pdo): bool
    {
        $stmt = $pdo->prepare('SELECT `EXTRA` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = \'students\' AND COLUMN_NAME = \'id\'');
        $stmt->execute([(string) $this->targetSettings()['database']]);
        return str_contains(strtolower((string) $stmt->fetchColumn()), 'auto_increment');
    }

    private function studentReferenceMaps(PDO $source, PDO $target, array $rows): array
    {
        $ids = ['centers' => [], 'institutes' => [], 'divisions' => [], 'districts' => [], 'upozilas' => [], 'unions' => []];
        foreach ($rows as $row) {
            foreach (['center_id' => 'centers', 'institute_id' => 'institutes', 'division_id' => 'divisions', 'district_id' => 'districts', 'upazila_id' => 'upozilas', 'union_id' => 'unions'] as $field => $table) {
                if (!empty($row[$field])) { $ids[$table][] = (int) $row[$field]; }
            }
        }
        foreach ($ids as $table => $values) { $ids[$table] = array_values(array_unique($values)); }
        $sourceMaps = [
            'centers' => $this->lookupCenterReferences($source, $ids['centers']),
            'institutes' => $this->lookupIds($source, 'institutes', ['name', 'center_id', 'certificate_no'], $ids['institutes']),
            'divisions' => $this->lookupAllIds($source, 'divisions', ['name']),
            'districts' => $this->lookupAllIds($source, 'districts', ['name', 'division_id']),
            'upozilas' => $this->lookupAllIds($source, 'upozilas', ['name', 'district_id']),
            'unions' => $this->lookupAllIds($source, 'unions', ['name', 'upozila_id']),
        ];
        $targetMaps = [
            'centers' => $this->lookupCenterReferences($target, []),
            'institutes' => $this->lookupAllIds($target, 'institutes', ['name', 'center_id', 'certificate_no']),
            'divisions' => $this->lookupAllIds($target, 'divisions', ['name']),
            'districts' => $this->lookupAllIds($target, 'districts', ['name', 'division_id']),
            'upozilas' => $this->lookupAllIds($target, 'upozilas', ['name', 'district_id']),
            'unions' => $this->lookupAllIds($target, 'unions', ['name', 'upozila_id']),
        ];
        $centerMap = [];
        foreach ($sourceMaps['centers'] as $sourceId => $sourceCenter) {
            $byCode = $this->uniqueReferenceMatch($targetMaps['centers'], 'code', $sourceCenter['code'] ?? '');
            $match = $byCode;
            if ($match === null && trim((string) ($sourceCenter['display_name'] ?? '')) !== ''
                && trim((string) ($sourceCenter['institute_name'] ?? '')) !== '') {
                $candidates = array_filter($targetMaps['centers'], fn(array $candidate): bool =>
                    $this->sameReference($candidate['display_name'] ?? '', $sourceCenter['display_name'])
                    && $this->sameReference($candidate['institute_name'] ?? '', $sourceCenter['institute_name'] ?? '')
                );
                if (count($candidates) === 1) { $match = reset($candidates); }
            }
            if ($match !== null) { $centerMap[$sourceId] = (int) $match['id']; }
        }
        $instituteMap = [];
        foreach ($sourceMaps['institutes'] as $sourceId => $sourceInstitute) {
            $mappedCenter = $centerMap[(int) ($sourceInstitute['center_id'] ?? 0)] ?? null;
            $match = $this->uniqueReferenceMatch($targetMaps['institutes'], 'certificate_no', $sourceInstitute['certificate_no'] ?? '');
            if ($match === null && $mappedCenter !== null) {
                $candidates = array_filter($targetMaps['institutes'], fn(array $candidate): bool =>
                    (int) ($candidate['center_id'] ?? 0) === $mappedCenter
                    && $this->sameReference($candidate['name'] ?? '', $sourceInstitute['name'] ?? '')
                );
                if (count($candidates) === 1) { $match = reset($candidates); }
            }
            if ($match !== null && ($mappedCenter === null || (int) ($match['center_id'] ?? 0) === $mappedCenter)) {
                $instituteMap[$sourceId] = (int) $match['id'];
            }
        }
        // Geographic IDs are database-local. Resolve locations by unique names
        // within their already-mapped parent instead of requiring equal IDs.
        $divisionMap = $this->mapLocationReferences($sourceMaps['divisions'], $targetMaps['divisions'], null, []);
        $districtMap = $this->mapLocationReferences($sourceMaps['districts'], $targetMaps['districts'], 'division_id', $divisionMap);
        $upazilaMap = $this->mapLocationReferences($sourceMaps['upozilas'], $targetMaps['upozilas'], 'district_id', $districtMap);
        $unionMap = $this->mapLocationReferences($sourceMaps['unions'], $targetMaps['unions'], 'upozila_id', $upazilaMap);
        return [
            'source' => $sourceMaps, 'target' => $targetMaps,
            'center_map' => $centerMap, 'institute_map' => $instituteMap,
            'location_map' => [
                'divisions' => $divisionMap, 'districts' => $districtMap,
                'upozilas' => $upazilaMap, 'unions' => $unionMap,
            ],
        ];
    }

    /** Match a geography level by unique name and the mapped parent ID. */
    private function mapLocationReferences(array $sourceRows, array $targetRows, ?string $parentColumn, array $parentMap): array
    {
        $sourceGroups = [];
        $targetGroups = [];
        foreach ($sourceRows as $id => $row) {
            $name = mb_strtolower(trim((string) ($row['name'] ?? '')), 'UTF-8');
            if ($name === '') { continue; }
            $parentId = 0;
            if ($parentColumn !== null) {
                $sourceParentId = (int) ($row[$parentColumn] ?? 0);
                if (!isset($parentMap[$sourceParentId])) { continue; }
                $parentId = (int) $parentMap[$sourceParentId];
            }
            $key = $name . "\x1F" . $parentId;
            $sourceGroups[$key][] = (int) $id;
        }
        foreach ($targetRows as $id => $row) {
            $name = mb_strtolower(trim((string) ($row['name'] ?? '')), 'UTF-8');
            if ($name === '') { continue; }
            $parentId = $parentColumn === null ? 0 : (int) ($row[$parentColumn] ?? 0);
            $key = $name . "\x1F" . $parentId;
            $targetGroups[$key][] = (int) $id;
        }
        $map = [];
        foreach ($sourceGroups as $key => $sourceIds) {
            $targetIds = $targetGroups[$key] ?? [];
            if (count($sourceIds) === 1 && count($targetIds) === 1) {
                $map[$sourceIds[0]] = $targetIds[0];
            }
        }
        return $map;
    }

    /** Load center names from their linked users without copying user accounts. */
    private function lookupCenterReferences(PDO $pdo, array $ids): array
    {
        $where = $ids === [] ? '' : ' WHERE c.`id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $stmt = $pdo->prepare('SELECT c.`id`, c.`institute_name`, c.`code`, c.`division_id`, c.`district_id`, c.`upazila_id`, c.`union_id`, u.`name` AS `display_name` FROM `centers` c LEFT JOIN `users` u ON u.`id` = c.`user_id`' . $where);
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $out[(int) $row['id']] = $row; }
        return $out;
    }

    private function uniqueReferenceMatch(array $rows, string $column, mixed $value): ?array
    {
        if (trim((string) $value) === '') { return null; }
        $matches = array_filter($rows, fn(array $row): bool => $this->sameReference($row[$column] ?? '', $value));
        return count($matches) === 1 ? reset($matches) : null;
    }

    private function sameReference(mixed $left, mixed $right): bool
    {
        return mb_strtolower(trim((string) $left), 'UTF-8') === mb_strtolower(trim((string) $right), 'UTF-8');
    }

    private function remapStudentReferences(array $row, array $maps, ?string &$failureReason = null): ?array
    {
        $sourceCenterId = (int) ($row['center_id'] ?? 0);
        $sourceInstituteId = (int) ($row['institute_id'] ?? 0);
        $centerId = $maps['center_map'][$sourceCenterId] ?? null;
        $instituteId = $maps['institute_map'][$sourceInstituteId] ?? null;
        if ($centerId === null) { $failureReason = 'Center reference did not match one unique center in demo.'; return null; }
        if ($instituteId === null) { $failureReason = 'Institute reference did not match one unique institute in demo.'; return null; }
        $sourceInstitute = $maps['source']['institutes'][$sourceInstituteId] ?? null;
        if ($sourceInstitute === null || (int) ($sourceInstitute['center_id'] ?? 0) !== $sourceCenterId) { $failureReason = 'Exam institute is missing or is not linked to the student’s exam center.'; return null; }
        $targetInstitute = $maps['target']['institutes'][$instituteId] ?? null;
        if ($targetInstitute === null || (int) ($targetInstitute['center_id'] ?? 0) !== $centerId) { $failureReason = 'Matched demo institute is not linked to the matched demo center.'; return null; }
        foreach (['division_id' => 'divisions', 'district_id' => 'districts', 'upazila_id' => 'upozilas', 'union_id' => 'unions'] as $field => $table) {
            $id = (int) ($row[$field] ?? 0);
            if ($id === 0 && $field === 'union_id') { continue; }
            $targetId = $maps['location_map'][$table][$id] ?? null;
            if ($targetId === null) { $failureReason = ucfirst(str_replace('_id', '', $field)) . ' has no unique name-and-parent match in demo.'; return null; }
            $row[$field] = $targetId;
        }
        $row['center_id'] = $centerId;
        $row['institute_id'] = $instituteId;
        return $row;
    }

    private function studentImportSkipDetail(array $row, string $reason, array $maps): array
    {
        $center = $maps['source']['centers'][(int) ($row['center_id'] ?? 0)] ?? [];
        $institute = $maps['source']['institutes'][(int) ($row['institute_id'] ?? 0)] ?? [];
        return [
            'registration' => trim((string) ($row['registration'] ?? '')),
            'name' => trim((string) ($row['name'] ?? '')),
            'class' => (string) ($row['class'] ?? ''),
            'session' => (string) ($row['session'] ?? ''),
            'roll' => (string) ($row['roll'] ?? ''),
            'center' => trim((string) ($center['display_name'] ?? '')) ?: trim((string) ($center['institute_name'] ?? '')),
            'institute' => trim((string) ($institute['name'] ?? '')),
            'reason' => $reason,
        ];
    }

    private function lookupIds(PDO $pdo, string $table, array $columns, array $ids): array
    {
        if ($ids === []) { return []; }
        $select = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', array_merge(['id'], $columns)));
        $sql = 'SELECT ' . $select . ' FROM `' . $table . '` WHERE `id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $out[(int) $row['id']] = $row; }
        return $out;
    }

    private function lookupAllIds(PDO $pdo, string $table, array $columns): array
    {
        $select = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', array_merge(['id'], $columns)));
        $out = [];
        foreach ($pdo->query('SELECT ' . $select . ' FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    private function existingValues(PDO $pdo, string $table, string $column, array $values): array
    {
        $values = array_values(array_unique(array_filter($values, static fn($value) => $value !== '')));
        if ($values === []) { return []; }
        $stmt = $pdo->prepare('SELECT `' . $column . '` FROM `' . $table . '` WHERE `' . $column . '` IN (' . implode(',', array_fill(0, count($values), '?')) . ')');
        $stmt->execute($values);
        $set = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $set[$column === 'registration' ? mb_strtolower(trim((string) $value), 'UTF-8') : (string) $value] = true;
        }
        return $set;
    }

    private function existingRolls(PDO $pdo, array $conditions): array
    {
        if ($conditions === []) { return []; }
        $where = [];
        $params = [];
        foreach ($conditions as [$session, $roll, $class]) {
            $where[] = '(`session` = ? AND `roll` = ? AND `class` = ?)';
            array_push($params, $session, $roll, $class);
        }
        $stmt = $pdo->prepare('SELECT `session`, `roll`, `class` FROM `students` WHERE ' . implode(' OR ', $where));
        $stmt->execute($params);
        $set = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $set[$this->rollKey($row)] = true; }
        return $set;
    }

    private function existingStudentFingerprints(PDO $pdo, array $sourceRows): array
    {
        $fields = ['phone', 'father_name', 'mother_name', 'name', 'session', 'class', 'center_id', 'institute_id'];
        $where = [];
        $params = [];
        foreach ($sourceRows as $sourceRow) {
            $terms = [];
            foreach ($fields as $field) {
                $terms[] = '`' . $field . '` <=> ?';
                $params[] = $sourceRow[$field] ?? null;
            }
            $where[] = '(' . implode(' AND ', $terms) . ')';
        }
        if ($where === []) { return []; }
        $stmt = $pdo->prepare('SELECT `phone`, `father_name`, `mother_name`, `name`, `session`, `class`, `center_id`, `institute_id` FROM `students` WHERE ' . implode(' OR ', $where));
        $stmt->execute($params);
        $set = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $set[StudentService::duplicateFingerprint($row)] = true;
        }
        return $set;
    }

    private function rollKey(array $row): ?string
    {
        if (($row['roll'] ?? null) === null || ($row['session'] ?? null) === null || ($row['class'] ?? null) === null) { return null; }
        return (int) $row['session'] . ':' . (int) $row['roll'] . ':' . (int) $row['class'];
    }

    private function tableExists(PDO $pdo, string $schema, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $stmt->execute([$schema, $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Known intentional differences that should not be proposed for syncing. */
    private function isIgnoredDifference(array $difference): bool
    {
        if (($difference['kind'] ?? '') === 'missing_table' && ($difference['table'] ?? '') === 'login_logs') {
            return true;
        }
        if (($difference['table'] ?? '') === 'unions'
            && ($difference['kind'] ?? '') === 'column_mismatch'
            && ($difference['detail'] ?? '') === 'Column `id` has a different definition') {
            return true;
        }
        if (($difference['table'] ?? '') === 'centers'
            && ($difference['kind'] ?? '') === 'column_mismatch'
            && ($difference['detail'] ?? '') === 'Column `user_id` has a different definition') {
            return true; // Demo allows reference-only centers without login accounts.
        }
        if (in_array(($difference['table'] ?? ''), ['divisions','districts','upozilas','unions'], true)
            && ($difference['kind'] ?? '') === 'column_mismatch'
            && ($difference['detail'] ?? '') === 'Column `user_id` has a different definition') {
            return true; // Demo-only reference rows do not need an owner account.
        }
        return ($difference['table'] ?? '') === 'unions'
            && ($difference['kind'] ?? '') === 'missing_index'
            && ($difference['detail'] ?? '') === 'Index `unions_name_unique` exists in exam but not demo';
    }

    /** Apply only explicitly reviewed files from database/sync_migrations to current app DB. */
    public function applyPending(): array
    {
        $this->assertReady();
        $files = $this->migrationFiles();
        if ($files === []) {
            return ['applied' => [], 'message' => 'There are no reviewed demo schema migration files to apply.'];
        }
        $pdo = $this->connectTarget();
        $lock = (int) $pdo->query("SELECT GET_LOCK('codex_demo_schema_sync', 10)")->fetchColumn();
        if ($lock !== 1) {
            throw new RuntimeException('Another database sync is running. Wait a moment and retry.');
        }
        try {
            return $this->runPendingMigrations($pdo, $files);
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('codex_demo_schema_sync')");
        }
    }

    private function runPendingMigrations(PDO $pdo, array $files): array
    {
        $this->ensureMigrationTable($pdo);
        $applied = $pdo->query('SELECT `migration` FROM `codex_schema_migrations`')->fetchAll(PDO::FETCH_COLUMN);
        $appliedMap = array_fill_keys($applied, true);
        $done = [];

        foreach ($files as $name => $file) {
            if (isset($appliedMap[$name])) {
                continue;
            }
            $statements = require $file;
            if (!is_array($statements) || $statements === []) {
                throw new RuntimeException("Migration {$name} must return a non-empty list of SQL statements.");
            }
            foreach ($statements as $sql) {
                if (!is_string($sql) || !$this->isAllowedDdl($sql)) {
                    throw new RuntimeException("Migration {$name} contains a statement outside the allowed additive schema changes.");
                }
            }
            // Record only after every statement completes. Migration scripts should be idempotent
            // (for example, use CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).
            foreach ($statements as $sql) {
                $this->executeAdditiveDdl($pdo, $sql);
            }
            $stmt = $pdo->prepare('INSERT INTO `codex_schema_migrations` (`migration`, `applied_at`) VALUES (?, NOW())');
            $stmt->execute([$name]);
            $done[] = $name;
            if (in_array($name, ['2026_10_24_center_display_name', '2026_10_26_center_reference_sync', '2026_10_30_center_contact_sync'], true)) {
                \App\Core\Cache::forget('schema:centers.display_name');
                \App\Core\Cache::forget('schema:centers.contact_phone');
                \App\Core\Cache::forget('schema:centers.contact_email');
                \App\Core\Cache::forget('filter:active_centers_named_v2');
            }
        }
        return ['applied' => $done, 'message' => $done ? 'Demo schema migrations applied.' : 'There are no pending demo schema migrations.'];
    }

    private function assertReady(): void
    {
        $state = $this->readiness();
        if (!$state['ready']) {
            throw new RuntimeException('Database sync is not configured safely: ' . implode('; ', $state['missing']));
        }
    }

    private function connectSource(): PDO
    {
        $pdo = $this->connect($this->sourceSettings());
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        return $pdo;
    }

    private function connectTarget(): PDO
    {
        $pdo = $this->connect($this->targetSettings());
        // MySQL DATETIME columns carry no timezone. Write sync history using
        // Bangladesh wall time so it can be displayed consistently everywhere.
        $pdo->exec("SET SESSION time_zone = '+06:00'");
        return $pdo;
    }

    private function connect(array $s): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $s['host'], $s['port'], $s['database'], $s['charset']);
        return new PDO($dsn, $s['username'], $s['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    private function sourceSettings(): array
    {
        return [
            'host' => trim((string) Config::get('SYNC_SOURCE_HOST', '')),
            'port' => Config::getInt('SYNC_SOURCE_PORT', 3306),
            'database' => trim((string) Config::get('SYNC_SOURCE_DATABASE', '')),
            'username' => (string) Config::get('SYNC_SOURCE_USERNAME', ''),
            'password' => (string) Config::get('SYNC_SOURCE_PASSWORD', ''),
            'charset' => (string) Config::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    private function targetSettings(): array
    {
        return [
            'host' => trim((string) Config::get('SYNC_TARGET_HOST', '')),
            'port' => Config::getInt('SYNC_TARGET_PORT', 3306),
            'database' => trim((string) Config::get('SYNC_TARGET_DATABASE', '')),
            'username' => (string) Config::get('SYNC_TARGET_USERNAME', ''),
            'password' => (string) Config::get('SYNC_TARGET_PASSWORD', ''),
            'charset' => (string) Config::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    private function schemaSnapshot(PDO $pdo, string $schema): array
    {
        $tables = [];
        $stmt = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'');
        $stmt->execute([$schema]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $tables[$table] = ['columns' => [], 'indexes' => []];
        }
        $stmt = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION');
        $stmt->execute([$schema]);
        foreach ($stmt->fetchAll() as $row) {
            if (!isset($tables[$row['TABLE_NAME']])) {
                continue; // Ignore views; this tool compares base-table structure only.
            }
            $tables[$row['TABLE_NAME']]['columns'][$row['COLUMN_NAME']] = implode('|', [
                $row['COLUMN_TYPE'], $row['IS_NULLABLE'], $row['COLUMN_DEFAULT'] === null ? '<NULL>' : (string) $row['COLUMN_DEFAULT'],
                $row['EXTRA'], $row['CHARACTER_SET_NAME'] ?? '', $row['COLLATION_NAME'] ?? '',
            ]);
        }
        $stmt = $pdo->prepare('SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX');
        $stmt->execute([$schema]);
        foreach ($stmt->fetchAll() as $row) {
            if (!isset($tables[$row['TABLE_NAME']])) {
                continue;
            }
            $tables[$row['TABLE_NAME']]['indexes'][$row['INDEX_NAME']][] = implode(':', [
                $row['NON_UNIQUE'], $row['SEQ_IN_INDEX'], $row['COLUMN_NAME'] ?? '', $row['SUB_PART'] ?? '', $row['INDEX_TYPE'],
            ]);
        }
        foreach ($tables as &$table) {
            foreach ($table['indexes'] as &$index) {
                $index = implode(',', $index);
            }
            unset($index);
            ksort($table['columns']);
            ksort($table['indexes']);
        }
        unset($table);
        ksort($tables);
        return $tables;
    }

    private function ensureMigrationTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `codex_schema_migrations` (`migration` varchar(190) NOT NULL, `applied_at` datetime NOT NULL, PRIMARY KEY (`migration`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** Execute MySQL-compatible additive DDL, including idempotent index additions. */
    private function executeAdditiveDdl(PDO $pdo, string $sql): void
    {
        if (preg_match('/^ALTER TABLE\s+`?([A-Za-z0-9_]+)`?\s+ADD COLUMN\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i', trim($sql), $match)) {
            $check = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
            $check->execute([$match[1], $match[2]]);
            if ($check->fetchColumn()) {
                return;
            }
            $sql = preg_replace('/\s+IF\s+NOT\s+EXISTS\s+/i', ' ', $sql, 1);
        }
        if (preg_match('/^ALTER TABLE\s+`?([A-Za-z0-9_]+)`?\s+ADD\s+(?:INDEX|KEY)\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i', trim($sql), $match)) {
            $check = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
            $check->execute([$match[1], $match[2]]);
            if ($check->fetchColumn()) {
                return;
            }
            $sql = preg_replace('/\s+IF\s+NOT\s+EXISTS\s+/i', ' ', $sql, 1);
        }
        $pdo->exec($sql);
    }

    private function isAllowedDdl(string $sql): bool
    {
        $sql = trim($sql);
        if ($sql === '' || str_contains($sql, ';') || str_contains($sql, '--') || str_contains($sql, '/*')) {
            return false;
        }
        // Explicitly reviewed exception: reference-only centers need no login user.
        if (preg_match('/^ALTER TABLE `(centers|divisions|districts|upozilas|unions)` MODIFY COLUMN `user_id` int DEFAULT NULL$/i', $sql)) {
            return true;
        }
        return (bool) preg_match('/^(CREATE TABLE IF NOT EXISTS|ALTER TABLE\s+`?[A-Za-z0-9_]+`?\s+ADD\s+(?:COLUMN|INDEX|KEY|UNIQUE)\s+IF\s+NOT\s+EXISTS\b)/i', $sql)
            && !(bool) preg_match('/\b(DROP|TRUNCATE|RENAME|MODIFY|CHANGE|DELETE|INSERT|UPDATE|REPLACE|GRANT|REVOKE|DATABASE|SCHEMA)\b/i', $sql)
            && !(bool) preg_match('/\bCREATE\s+TABLE\b.*\bAS\s+SELECT\b/i', $sql)
            && !preg_match('/(?:`[^`]+`|[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*(?:`[^`]+`|[A-Za-z_][A-Za-z0-9_]*)/', $sql);
    }

    private function migrationFiles(): array
    {
        $files = glob(BASE_PATH . '/database/sync_migrations/*.php') ?: [];
        sort($files, SORT_STRING);
        $out = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!preg_match('/^\d{4}_\d{2}_\d{2}_[a-z0-9_]+$/', $name)) {
                throw new RuntimeException('Invalid demo migration filename: ' . $name);
            }
            $out[$name] = $file;
        }
        return $out;
    }

    private function migrationNames(): array
    {
        return array_keys($this->migrationFiles());
    }
}
