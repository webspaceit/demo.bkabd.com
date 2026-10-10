<?php

namespace App\Core;

/**
 * Lightweight model: table mapping + query shortcuts + mass assignment.
 *
 *   class Student extends Model { protected string $table = 'students'; }
 *   Student::where('session', 2025)->orderBy('id')->paginate(20);
 */
abstract class Model
{
    protected static string $table = '';
    /** @var string[] */
    protected static array $fillable = [];
    /** Casts applied to fetched rows: col => 'int'|'float'|'bool' */
    protected static array $casts = [];

    /** Insert a row (fillable-filtered). Returns new id. */
    public static function create(array $data): int
    {
        return Database::insert(static::$table, static::filter($data));
    }

    /** Insert and return all casts applied. */
    public static function createAndGet(array $data): array
    {
        $id = static::create($data);
        return static::find($id) ?? [];
    }

    public static function find(int|string $id): ?array
    {
        return static::query()->where('id', (int) $id)->first();
    }

    public static function findOrFail(int|string $id): array
    {
        $row = static::find($id);
        if ($row === null) {
            throw new HttpException(404, 'Record not found');
        }
        return $row;
    }

    public static function updateRow(int|string $id, array $data): int
    {
        return static::query()->where('id', (int) $id)->update(static::filter($data));
    }

    public static function deleteRow(int|string $id): int
    {
        return static::query()->where('id', (int) $id)->delete();
    }

    public static function query(): Query
    {
        return new Query(static::$table);
    }

    /** Shorthand: static::where(...)->get() */
    public static function where(string $column, mixed $op = null, mixed $value = null): Query
    {
        return static::query()->where($column, $op, $value);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return static::query()->get();
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    /** Apply $casts to a raw row. */
    public static function cast(array $row): array
    {
        foreach (static::$casts as $col => $type) {
            if (!array_key_exists($col, $row) || $row[$col] === null) {
                continue;
            }
            $row[$col] = match ($type) {
                'int' => (int) $row[$col],
                'float' => (float) $row[$col],
                'bool' => (bool) $row[$col],
                default => $row[$col],
            };
        }
        return $row;
    }

    public static function table(): string
    {
        return static::$table;
    }

    protected static function filter(array $data): array
    {
        if (static::$fillable === []) {
            return $data;
        }
        return array_intersect_key($data, array_flip(static::$fillable));
    }
}
