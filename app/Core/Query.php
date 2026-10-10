<?php

namespace App\Core;

/**
 * Fluent SQL query builder (enough surface for this application).
 *
 *   (new Query('students'))
 *       ->select('id','name')
 *       ->join('institutes', 'institutes.id', '=', 'students.institute_id')
 *       ->where('session', 2025)
 *       ->whereIn('class', [1,2,3])
 *       ->orderBy('id','desc')
 *       ->paginate(20);
 */
final class Query
{
    private string $table;
    /** @var string[] */
    private array $columns = ['*'];
    /** @var string[] */
    private array $joins = [];
    /**
     * Each: ['and'|'or', ['raw', sql, params] | [col, op, val]]
     * @var array<int,array{0:string,1:array}>
     */
    private array $wheres = [];
    /** @var string[] */
    private array $groups = [];
    /** @var string[] */
    private array $orders = [];
    private ?string $having = null;
    private ?int $limit = null;
    private ?int $offset = null;
    /** Named params collected while compiling. */
    private array $paramsBag = [];

    public function __construct(string $table)
    {
        // Table names are code-level constants; strip stray identifier chars
        // defensively so nothing can break out of the backtick-quoted position.
        $this->table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    }

    // ---------------------------------------------------------------
    // Clauses
    // ---------------------------------------------------------------

    public function select(string ...$columns): self
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    public function selectRaw(string $expr): self
    {
        $this->columns[] = $expr;
        return $this;
    }

    public function distinct(): self
    {
        array_unshift($this->columns, 'DISTINCT');
        return $this;
    }

    public function join(string $table, string $left, string $op, string $right, string $type = 'INNER'): self
    {
        $this->joins[] = "$type JOIN `$table` ON $left $op $right";
        return $this;
    }

    public function leftJoin(string $table, string $left, string $op, string $right): self
    {
        return $this->join($table, $left, $op, $right, 'LEFT');
    }

    /** where('a', $b) => a = b;  where('a', '>', $b) => a > b */
    public function where(string $column, mixed $op = null, mixed $value = null): self
    {
        $operators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];
        if ($value === null && !(is_string($op) && in_array(strtoupper($op), $operators, true))) {
            $value = $op;
            $op = '=';
        }
        $this->wheres[] = ['and', ['atom', $column, (string) $op, $value]];
        return $this;
    }

    public function orWhere(string $column, mixed $op = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $op;
            $op = '=';
        }
        $this->wheres[] = ['or', ['atom', $column, $op, $value]];
        return $this;
    }

    /** Group several conditions with AND: whereGroup(fn($q) => $q->where(...)->orWhere(...)) */
    public function whereGroup(\Closure $fn): self
    {
        $nested = new self($this->table);
        $fn($nested);
        $sql = $nested->buildWhere();
        if ($sql !== '') {
            $this->wheres[] = ['and', ['raw', "($sql)", $nested->paramsBag]];
        }
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->wheres[] = ['and', ['raw', '0=1', []]];
            return $this;
        }
        $this->wheres[] = ['and', ['atom', $column, 'IN', array_values($values)]];
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['and', ['atom', $column, 'IS NULL', null]];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['and', ['atom', $column, 'IS NOT NULL', null]];
        return $this;
    }

    public function whereRaw(string $sql, array $params = []): self
    {
        $this->wheres[] = ['and', ['raw', $sql, $params]];
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function having(string $sql): self
    {
        $this->having = $sql;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orders[] = self::qi($column) . " $direction";
        return $this;
    }

    public function orderByRaw(string $expr): self
    {
        $this->orders[] = $expr;
        return $this;
    }

    public function latest(string $column = 'id'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function limit(int $n): self
    {
        $this->limit = $n;
        return $this;
    }

    public function offset(int $n): self
    {
        $this->offset = $n;
        return $this;
    }

    // ---------------------------------------------------------------
    // Compile
    // ---------------------------------------------------------------

    /** Quote a column reference; `table.column` becomes `table`.`column`. */
    private static function qi(string $col): string
    {
        return implode('.', array_map(fn(string $p): string => '`' . str_replace('`', '', $p) . '`', explode('.', $col)));
    }

    private function compileAtom(string $col, string $op, mixed $val): string
    {
        $col = self::qi($col);
        switch ($op) {
            case 'IS NULL':
            case 'IS NOT NULL':
                return "$col $op";
            case 'IN':
            case 'NOT IN':
                $marks = [];
                foreach (array_values((array) $val) as $v) {
                    $key = ':w' . count($this->paramsBag);
                    $this->paramsBag[$key] = $v;
                    $marks[] = $key;
                }
                return "$col $op (" . implode(',', $marks) . ')';
            default:
                if (is_int($val) || is_float($val)) {
                    // Safe numeric literal.
                    return "$col $op " . (string) $val;
                }
                $key = ':w' . count($this->paramsBag);
                $this->paramsBag[$key] = $val;
                return "$col $op $key";
        }
    }

    /** Builds WHERE string; collects params into $this->paramsBag (reset first). */
    private function buildWhere(): string
    {
        $this->paramsBag = [];
        $parts = [];
        foreach ($this->wheres as $i => [$bool, $node]) {
            if ($node[0] === 'raw') {
                [, $sql, $params] = $node;
                $frag = $sql;
                foreach ((array) $params as $k => $v) {
                    $key = is_int($k) ? ':r' . count($this->paramsBag) : $k;
                    $this->paramsBag[$key] = $v;
                    if (is_int($k)) {
                        $frag = preg_replace('/\?/', $key, $frag, 1);
                    }
                }
            } else {
                [, $col, $op, $val] = $node;
                $frag = $this->compileAtom($col, $op, $val);
            }
            $connector = $i === 0 ? '' : strtoupper($bool) . ' ';
            $parts[] = $connector . $frag;
        }
        return trim(implode(' ', $parts));
    }

    private function compileSelectSql(): string
    {
        $sql = 'SELECT ' . implode(',', $this->columns) . " FROM `$this->table`";
        foreach ($this->joins as $j) {
            $sql .= ' ' . $j;
        }
        $where = $this->buildWhere();
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        if ($this->groups) {
            $sql .= ' GROUP BY ' . implode(',', $this->groups);
        }
        if ($this->having !== null) {
            $sql .= ' HAVING ' . $this->having;
        }
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(',', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    // ---------------------------------------------------------------
    // Execute
    // ---------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function get(): array
    {
        $sql = $this->compileSelectSql();
        $params = $this->paramsBag;
        return Database::select($sql, $params);
    }

    public function first(): ?array
    {
        $old = $this->limit;
        $this->limit = 1;
        $sql = $this->compileSelectSql();
        $params = $this->paramsBag;
        $this->limit = $old;
        return Database::selectOne($sql, $params);
    }

    public function count(): int
    {
        $cols = $this->columns;
        $orders = $this->orders;
        $limit = $this->limit;
        $offset = $this->offset;
        $this->columns = ['COUNT(*) AS c'];
        $this->orders = [];
        $this->limit = null;
        $this->offset = null;
        $sql = $this->compileSelectSql();
        $params = $this->paramsBag;
        $this->columns = $cols;
        $this->orders = $orders;
        $this->limit = $limit;
        $this->offset = $offset;
        $row = Database::selectOne($sql, $params);
        return (int) ($row['c'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();
        return $row[$column] ?? null;
    }

    /** @return array<int,mixed> */
    public function pluck(string $column): array
    {
        return array_column($this->select($column)->get(), $column);
    }

    public function sum(string $column): float
    {
        return (float) ($this->select("COALESCE(SUM(`$column`),0) AS s")->first()['s'] ?? 0);
    }

    public function update(array $data): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            if (is_int($val) || is_float($val)) {
                $sets[] = self::qi($col) . ' = ' . $val;
            } else {
                $key = ':u' . count($params);
                $sets[] = self::qi($col) . " = $key";
                $params[$key] = $val;
            }
        }
        $sql = 'UPDATE `' . $this->table . '` SET ' . implode(',', $sets);
        $where = $this->buildWhere();
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $params += $this->paramsBag;
        return Database::statement($sql, $params)->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM `' . $this->table . '`';
        $where = $this->buildWhere();
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        return Database::statement($sql, $this->paramsBag)->rowCount();
    }

    /**
     * Laravel-shaped paginator JSON:
     * current_page, data, first_page_url, from, last_page, last_page_url,
     * links, next_page_url, path, per_page, prev_page_url, to, total
     */
    public function paginate(int $perPage = 20, ?int $page = null, string $path = ''): array
    {
        $req = Request::capture();
        $page = max(1, $page ?? (int) $req->query('page', 1));
        $perPage = max(1, min(200, $perPage));

        $total = (clone $this)->count();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $data = $this->get();

        $path = $path !== '' ? $path : $req->path();
        $query = $req->query;
        unset($query['page']);

        $url = function (int $p) use ($path, $query): string {
            $qs = http_build_query($query + ($p > 1 ? ['page' => $p] : []));
            return $qs ? $path . '?' . $qs : $path;
        };

        // Windowed page list, like Laravel's default UI: 1 ... p-1 p p+1 ... last
        $pageNumbers = $lastPage <= 9
            ? range(1, $lastPage)
            : array_values(array_unique(array_filter(
                [1, 2, $page - 1, $page, $page + 1, $lastPage - 1, $lastPage],
                fn(int $p): bool => $p >= 1 && $p <= $lastPage
            )));
        sort($pageNumbers);

        $links = [['url' => $page > 1 ? $url($page - 1) : null, 'label' => '« Previous', 'active' => false]];
        $prev = 0;
        foreach ($pageNumbers as $p) {
            if ($prev && $p > $prev + 1) {
                $links[] = ['url' => null, 'label' => '...', 'active' => false];
            }
            $links[] = ['url' => $url($p), 'label' => (string) $p, 'active' => $p === $page];
            $prev = $p;
        }
        $links[] = ['url' => $page < $lastPage ? $url($page + 1) : null, 'label' => 'Next »', 'active' => false];

        return [
            'current_page' => $page,
            'data' => $data,
            'first_page_url' => $url(1),
            'from' => $total === 0 ? null : ($page - 1) * $perPage + 1,
            'last_page' => $lastPage,
            'last_page_url' => $url($lastPage),
            'links' => $links,
            'next_page_url' => $page < $lastPage ? $url($page + 1) : null,
            'path' => $path,
            'per_page' => $perPage,
            'prev_page_url' => $page > 1 ? $url($page - 1) : null,
            'to' => $total === 0 ? null : min($page * $perPage, $total),
            'total' => $total,
        ];
    }
}
