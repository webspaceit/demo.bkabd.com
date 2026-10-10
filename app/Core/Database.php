<?php

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * PDO singleton with prepared statements + soft reconnect.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static int $queryCount = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            Config::get('DB_HOST', 'localhost'),
            Config::getInt('DB_PORT', 3306),
            Config::get('DB_DATABASE', 'oop_exam'),
            Config::get('DB_CHARSET', 'utf8mb4')
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];
        if (Config::getBool('DB_PERSISTENT', false)) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }
        self::$pdo = new PDO(
            $dsn,
            Config::get('DB_USERNAME', 'root'),
            Config::get('DB_PASSWORD', ''),
            $options
        );
        self::$pdo->exec("SET time_zone = '+06:00'"); // Asia/Dhaka in DB NOW() comparisons
        return self::$pdo;
    }

    private static function run(string $sql, array $params): PDOStatement
    {
        self::$queryCount++;
        try {
            $stmt = self::pdo()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            // One retry on lost connection ("server has gone away" with persistent conns).
            if (in_array($e->errorInfo[1] ?? 0, [2006, 2013], true)) {
                self::$pdo = null;
                $stmt = self::pdo()->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            }
            error_log('[db] ' . self::redact($sql) . ' :: ' . self::redact($e->getMessage()));
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function select(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Single scalar value (first column of first row). */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function statement(string $sql, array $params = []): PDOStatement
    {
        return self::run($sql, $params);
    }

    public static function insert(string $table, array $data): int
    {
        $table = self::quoteTable($table);
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', array_map(fn($c) => ':' . $c, $cols))
        );
        $params = [];
        foreach ($data as $k => $v) {
            $params[':' . $k] = $v;
        }
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $table = self::quoteTable($table);
        $sets = implode(',', array_map(fn($c) => "`$c` = :set_$c", array_keys($data)));
        $params = [];
        foreach ($data as $k => $v) {
            $params[':set_' . $k] = $v;
        }
        $stmt = self::run("UPDATE `$table` SET $sets WHERE $where", $params + $whereParams);
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        $table = self::quoteTable($table);
        return self::run("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    /**
     * Table names are code-level constants in this app (never user input), but
     * quote them defensively so a stray backtick / quote can't break out of the
     * identifier position — cheap belt-and-braces (CWE-89).
     */
    private static function quoteTable(string $table): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', $table);
    }

    /**
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function queryCount(): int
    {
        return self::$queryCount;
    }

    /**
     * Mask quoted literals so PII (emails, names, phone numbers) can never
     * land in the error log, and cap the length. Used for every logged SQL /
     * driver message.
     */
    public static function redact(string $text): string
    {
        $text = (string) preg_replace("/'[^']*'/", "'***'", $text);
        $text = (string) preg_replace('/"[^"]*"/', '"***"', $text);
        return strlen($text) > 500 ? substr($text, 0, 500) . '…' : $text;
    }
}
