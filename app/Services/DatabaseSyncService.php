<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;
use RuntimeException;

/** Read-only exam-schema comparison and explicitly allow-listed demo migrations. */
final class DatabaseSyncService
{
    /** @return array<string,mixed> */
    public function readiness(): array
    {
        $source = $this->sourceSettings();
        $targetHost = trim((string) Config::get('SYNC_TARGET_HOST', ''));
        $targetDatabase = trim((string) Config::get('SYNC_TARGET_DATABASE', ''));
        $appHost = trim((string) Config::get('DB_HOST', 'localhost'));
        $appDatabase = trim((string) Config::get('DB_DATABASE', 'oop_exam'));
        $missing = [];
        foreach (['SYNC_SOURCE_HOST', 'SYNC_SOURCE_DATABASE', 'SYNC_SOURCE_USERNAME', 'SYNC_SOURCE_PASSWORD', 'SYNC_TARGET_HOST', 'SYNC_TARGET_DATABASE'] as $key) {
            if (trim((string) Config::get($key, '')) === '') {
                $missing[] = $key;
            }
        }
        if (!Config::getBool('DB_SYNC_ENABLED', false)) {
            $missing[] = 'DB_SYNC_ENABLED must be explicitly enabled on the demo server';
        }
        if ($targetHost !== '' && strcasecmp($targetHost, $appHost) !== 0) {
            $missing[] = 'DB_HOST must match SYNC_TARGET_HOST';
        }
        if ($targetDatabase !== '' && strcasecmp($targetDatabase, $appDatabase) !== 0) {
            $missing[] = 'DB_DATABASE must match SYNC_TARGET_DATABASE';
        }
        if ($source['database'] !== '' && strcasecmp($source['database'], $targetDatabase) === 0
            && strcasecmp($source['host'], $targetHost) === 0
            && $source['port'] === Config::getInt('DB_PORT', 3306)) {
            $missing[] = 'exam source and demo target point to the same database';
        }

        $migrationNames = $this->migrationNames();
        $pendingMigrations = $migrationNames;
        if ($missing === [] && $migrationNames !== []) {
            $pdo = Database::pdo();
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
            'target' => ['label' => 'Demo destination', 'host' => $targetHost, 'database' => $targetDatabase, 'access' => 'Current app connection'],
            'pending_migrations' => $pendingMigrations,
        ];
    }

    /** Compare only metadata; never query application records on the source. */
    public function compare(): array
    {
        $this->assertReady();
        $source = $this->connectSource();
        $target = Database::pdo();
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
        return [
            'source' => $sourceSchema,
            'target' => $targetSchema,
            'differences' => $differences,
            'note' => 'Comparison is read-only and reports exam-side additions or differences. It does not create SQL or change either database.',
        ];
    }

    /** Apply only explicitly reviewed files from database/sync_migrations to current app DB. */
    public function applyPending(): array
    {
        $this->assertReady();
        $files = $this->migrationFiles();
        if ($files === []) {
            return ['applied' => [], 'message' => 'There are no reviewed demo schema migration files to apply.'];
        }
        $pdo = Database::pdo();
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
                $pdo->exec($sql);
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
        $s = $this->sourceSettings();
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $s['host'], $s['port'], $s['database'], $s['charset']);
        $pdo = new PDO($dsn, $s['username'], $s['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        return $pdo;
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
