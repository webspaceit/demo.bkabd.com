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
        foreach (['SYNC_SOURCE_HOST', 'SYNC_SOURCE_DATABASE', 'SYNC_SOURCE_USERNAME', 'SYNC_SOURCE_PASSWORD', 'SYNC_TARGET_HOST', 'SYNC_TARGET_DATABASE', 'SYNC_TARGET_USERNAME', 'SYNC_TARGET_PASSWORD'] as $key) {
            if (trim((string) Config::get($key, '')) === '') {
                $missing[] = $key;
            }
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
                . 'c.`institute_name` AS `center_name`, i.`name` AS `institute_name` '
                . 'FROM `students` s '
                . 'LEFT JOIN `centers` c ON c.`id` = s.`center_id` '
                . 'LEFT JOIN `institutes` i ON i.`id` = s.`institute_id` '
                . 'ORDER BY s.`created_at` DESC, s.`id` DESC LIMIT ' . $limit;
            return $source->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            if ($source->inTransaction()) {
                $source->rollBack();
            }
        }
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
                'note' => 'Preview checks registration numbers only. During import, records with conflicting rolls or unmatched center/institute references are skipped safely.',
            ];
        } finally {
            if ($source->inTransaction()) { $source->rollBack(); }
        }
    }

    /** Import one small idempotent batch; only the demo connection is written. */
    public function importStudentBatch(int $afterId = 0, int $batchSize = 1000): array
    {
        $this->assertReady();
        // Larger batches substantially reduce the per-request connection and
        // metadata overhead when scanning a large source database.
        $batchSize = max(1, min(1000, $batchSize));
        $source = $this->connectSource();
        $target = $this->connectTarget();
        try {
            $sourceStmt = $source->prepare('SELECT * FROM `students` WHERE `id` > ? ORDER BY `id` ASC LIMIT ' . $batchSize);
            $sourceStmt->execute([max(0, $afterId)]);
            $rows = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                return ['done' => true, 'next_after_id' => $afterId, 'processed' => 0, 'imported' => 0, 'already_present' => 0, 'skipped' => 0];
            }

            $this->assertStudentColumnsCompatible($source, $target);
            $referenceMaps = $this->studentReferenceMaps($source, $target, $rows);
            $registrations = [];
            $fingerprints = [];
            $rollConditions = [];
            foreach ($rows as $row) {
                if (trim((string) ($row['registration'] ?? '')) !== '') { $registrations[] = trim((string) $row['registration']); }
                $fingerprints[] = \App\Services\StudentService::duplicateFingerprint($row);
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
                foreach ($this->existingStudentFingerprints($target, $rows) as $fingerprint => $_present) {
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
                    if ($registration === '') { $counts['skipped']++; continue; }
                    $regKey = mb_strtolower($registration, 'UTF-8');
                    if (isset($presentRegistrations[$regKey])) { $counts['already_present']++; continue; }
                    $fingerprint = \App\Services\StudentService::duplicateFingerprint($row);
                    if (isset($presentFingerprints[$fingerprint])) { $counts['already_present']++; continue; }
                    if (!$this->studentReferencesMatch($row, $referenceMaps)) { $counts['skipped']++; continue; }
                    $rollKey = $this->rollKey($row);
                    if ($rollKey !== null && isset($presentRolls[$rollKey])) { $counts['skipped']++; continue; }
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
                        if (($e->errorInfo[1] ?? 0) === 1062) { $counts['skipped']++; continue; }
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
            return ['done' => count($rows) < $batchSize, 'next_after_id' => $lastId, 'processed' => count($rows)] + $counts;
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
                throw new RuntimeException("Student import is paused: the `students.{$column}` column differs between exam and demo.");
            }
        }
    }

    private function columnDefinitions(PDO $pdo, string $schema): array
    {
        $stmt = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?');
        $stmt->execute([$schema]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['TABLE_NAME']][$row['COLUMN_NAME']] = implode('|', [
                $row['COLUMN_TYPE'], $row['IS_NULLABLE'], $row['COLUMN_DEFAULT'] === null ? '<NULL>' : (string) $row['COLUMN_DEFAULT'],
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
            'centers' => $this->lookupIds($source, 'centers', ['institute_name', 'code'], $ids['centers']),
            'institutes' => $this->lookupIds($source, 'institutes', ['name', 'center_id'], $ids['institutes']),
            'divisions' => $this->lookupIds($source, 'divisions', ['name'], $ids['divisions']),
            'districts' => $this->lookupIds($source, 'districts', ['name', 'division_id'], $ids['districts']),
            'upozilas' => $this->lookupIds($source, 'upozilas', ['name', 'district_id'], $ids['upozilas']),
            'unions' => $this->lookupIds($source, 'unions', ['name', 'upozila_id'], $ids['unions']),
        ];
        $targetMaps = [
            'centers' => $this->lookupIds($target, 'centers', ['institute_name', 'code'], $ids['centers']),
            'institutes' => $this->lookupIds($target, 'institutes', ['name', 'center_id'], $ids['institutes']),
            'divisions' => $this->lookupIds($target, 'divisions', ['name'], $ids['divisions']),
            'districts' => $this->lookupIds($target, 'districts', ['name', 'division_id'], $ids['districts']),
            'upozilas' => $this->lookupIds($target, 'upozilas', ['name', 'district_id'], $ids['upozilas']),
            'unions' => $this->lookupIds($target, 'unions', ['name', 'upozila_id'], $ids['unions']),
        ];
        return ['source' => $sourceMaps, 'target' => $targetMaps];
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

    private function studentReferencesMatch(array $row, array $maps): bool
    {
        $relations = [
            'center_id' => ['centers', ['institute_name', 'code']],
            'institute_id' => ['institutes', ['name', 'center_id']],
            'division_id' => ['divisions', ['name']],
            'district_id' => ['districts', ['name', 'division_id']],
            'upazila_id' => ['upozilas', ['name', 'district_id']],
            'union_id' => ['unions', ['name', 'upozila_id']],
        ];
        foreach ($relations as $field => [$table, $columns]) {
            $id = (int) ($row[$field] ?? 0);
            if ($id === 0 && $field === 'union_id') { continue; }
            $source = $maps['source'][$table][$id] ?? null;
            $target = $maps['target'][$table][$id] ?? null;
            if ($source === null || $target === null) { return false; }
            foreach ($columns as $column) {
                if ((string) ($source[$column] ?? '') !== (string) ($target[$column] ?? '')) { return false; }
            }
        }
        $centerId = (int) ($row['center_id'] ?? 0);
        $instituteId = (int) ($row['institute_id'] ?? 0);
        return (int) ($maps['source']['institutes'][$instituteId]['center_id'] ?? 0) === $centerId
            && (int) ($maps['target']['institutes'][$instituteId]['center_id'] ?? 0) === $centerId;
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
        return $this->connect($this->targetSettings());
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
