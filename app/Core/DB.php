<?php

namespace App\Core;

use App\Core\Connection;

class DB
{
    protected string $table;
    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $joins = [];
    protected array $bindings = [];
    protected array $groups = [];
    protected array $havings = [];
    protected array $orders = [];
    protected ?int $limit  = null;
    protected ?int $offset = null;
    protected bool $distinct = false;
    protected array $distinctColumns = [];

    protected ?string $connection = null;
    protected int $fetchStyle = \PDO::FETCH_OBJ;
    protected ?string $fetchClass = null;

    protected static $executor;

    /* ===================================================== */
    /* CORE                                                  */
    /* ===================================================== */

    public static function setExecutor(callable $executor): void
    {
        static::$executor = $executor;
    }

    public static function run(string $sql, array $bindings = [], array $context = [])
    {
        $executor = self::$executor;

        $isSelect = str_starts_with(ltrim($sql), 'SELECT');

        if ($isSelect) {
            $context['fetch'] ??= \PDO::FETCH_ASSOC;
        } else {
            $context['fetch'] = null;
        }

        $result = $executor(
            $sql,
            $bindings,
            $context
        );

        if ($isSelect && is_array($result)) {
            foreach ($result as $row) {
                if ($row instanceof \App\Core\Model) {
                    $row->syncOriginal();
                }
            }
        }

        return $result;
    }

    /* ===================================================== */
    /* CONSTRUCTOR                                           */
    /* ===================================================== */

    public static function table(string $table, ?string $alias = null): self
    {
        return new DB($table, $alias);
        // return new self($table, $alias);
    }

    public function __construct(string $table, ?string $alias = null)
    {
        $this->table = $this->renameTable($table, $alias);
    }

    public function connection(string $name): self
    {
        $this->connection = $name;
        return $this;
    }

    public function alias(string $alias): self
    {
        // remove alias anterior, se houver
        if (preg_match('/^`(.+?)`\s+AS\s+.+$/i', $this->table, $m)) {
            $this->table = "`{$m[1]}` AS {$alias}";
        } else {
            $this->table = "{$this->table} AS {$alias}";
        }

        return $this;
    }

    public function as(string $alias): self
    {
        return $this->alias($alias);
    }

    /* ===================================================== */
    /* SAÍDAS                                                */
    /* ===================================================== */

    public function toSql(): string
    {
        [$sql] = $this->builder();
        return $sql;
    }

    public function toRawSql(): string
    {
        [$sql, $bindings] = $this->builder();

        foreach ($bindings as $binding) {
            if ($binding === null) {
                $value = 'NULL';
            } elseif (is_bool($binding)) {
                $value = $binding ? '1' : '0';
            } elseif (is_numeric($binding)) {
                $value = $binding;
            } else {
                $value = "'" . str_replace("'", "''", (string) $binding) . "'";
            }

            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }


    /* ===================================================== */
    /* FETCH                                                */
    /* ===================================================== */

    public function fetch(string|int $style, ?string $class = null, int $flags = 0): self
    {
        // 👉 CASO 1: FETCH NATIVO PDO (INT)
        if (is_int($style)) {
            $this->fetchStyle = $style | $flags;
            $this->fetchClass = $class;
            return $this;
        }

        // 👉 CASO 2: FETCH SEMÂNTICO (STRING)
        $style = strtolower($style);

        $baseFetch = [
            'obj'     => \PDO::FETCH_OBJ,
            'object'  => \PDO::FETCH_OBJ,
            'array'   => \PDO::FETCH_ASSOC,
            'assoc'   => \PDO::FETCH_ASSOC,
            'num'     => \PDO::FETCH_NUM,
            'named'   => \PDO::FETCH_NAMED,
            'both'    => \PDO::FETCH_BOTH,
            'lazy'    => \PDO::FETCH_LAZY,
            'class'   => \PDO::FETCH_CLASS,
            'keypair' => \PDO::FETCH_KEY_PAIR,
        ];

        $modifiers = [
            'groupby' => \PDO::FETCH_GROUP,
            'unique'  => \PDO::FETCH_UNIQUE,
        ];

        // 👉 modifier sozinho → FETCH_ASSOC + modifier
        if (isset($modifiers[$style])) {
            $this->fetchStyle = \PDO::FETCH_ASSOC | $modifiers[$style];
            $this->fetchClass = null;
            return $this;
        }

        if (!isset($baseFetch[$style])) {
            throw new \InvalidArgumentException("Fetch inválido: {$style}");
        }

        $this->fetchStyle = $baseFetch[$style] | $flags;
        $this->fetchClass = $class;

        return $this;
    }



    /* ===================================================== */
    /* SELECT                                               */
    /* ===================================================== */

    public function select(string|array ...$columns): self
    {
        if (!$columns) return $this;

        if (count($columns) === 1 && is_array($columns[0])) {
            $this->columns = $columns[0];
        } else {
            $this->columns = $columns;
        }

        return $this;
    }

    public function distinct(string ...$columns): self
    {
        $this->distinct = true;

        if ($columns) {
            $this->distinctColumns = $columns;
        }

        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* JOINS                                                              */
    /* ------------------------------------------------------------------ */

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->validateOperator($operator);

        $this->joins[] = [
            'type'     => strtoupper($type),
            'table'    => $this->renameTable($table),
            'first'    => $first,
            'operator' => $operator,
            'second'   => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function crossJoin(string $table): self
    {
        $this->joins[] = [
            'type'  => 'CROSS',
            'table' => $this->renameTable($table),
        ];

        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* EXECUÇÃO                                                           */
    /* ------------------------------------------------------------------ */

    public function get(): array
    {
        [$sql, $bindings] = $this->builder();

        return self::run($sql, $bindings, [
            'connection' => $this->connection,
            'fetch'      => $this->fetchStyle,
            'class'      => $this->fetchClass,
        ]);
    }

    public function first(): mixed
    {
        [$sql, $bindings] = $this->builder(1);

        $result = self::run($sql, $bindings, [
            'connection' => $this->connection,
            'fetch'      => $this->fetchStyle,
            'class'      => $this->fetchClass,
        ]);

        return $result[0] ?? null;
    }

    public function last(string $column = 'id'): mixed
    {
        return $this->orderBy($column, 'DESC')->first();
    }

    public function paginate(int $perPage, ?int $page = null): object
    {
        $page ??= max(1, (int) ($_GET['page'] ?? 1));

        // clone para não sujar a query original
        $countQuery = clone $this;

        $total = $countQuery->count();

        $this->limit(
            $perPage,
            ($page - 1) * $perPage
        );

        $data = $this->get();

        return (object) [
            'data'        => $data,
            'total'       => $total,
            'per_page'    => $perPage,
            'current'     => $page,
            'last_page'   => (int) ceil($total / $perPage),
            'has_more'    => $page * $perPage < $total,
        ];
    }

    public function pluck(string $column): array
    {
        // força selecionar apenas a coluna desejada
        $this->columns = [$column];

        [$sql, $bindings] = $this->builder();

        return self::run($sql, $bindings, [
            'connection' => $this->connection,
            'fetch'      => \PDO::FETCH_COLUMN,
        ]);
    }

    public function count(): int
    {
        // força COUNT(*)
        $this->columns = ['COUNT(*) AS aggregate'];

        [$sql, $bindings] = $this->builder();

        $result = self::run($sql, $bindings, [
            'connection' => $this->connection,
            'fetch'      => \PDO::FETCH_ASSOC,
        ]);

        return (int) ($result[0]['aggregate'] ?? 0);
    }

    protected function aggregate(string $function, string $column)
    {
        // salva estado
        $originalColumns = $this->columns;
        $originalOrders  = $this->orders;
        $originalLimit   = $this->limit;
        $originalOffset  = $this->offset;

        $this->columns = ["{$function}({$column}) AS aggregate"];
        $this->orders  = [];
        $this->limit   = null;
        $this->offset  = null;

        [$sql, $bindings] = $this->builder(1);

        $result = self::run($sql, $bindings, [
            'connection' => $this->connection,
            'fetch'      => \PDO::FETCH_ASSOC,
        ]);

        // restaura estado
        $this->columns = $originalColumns;
        $this->orders  = $originalOrders;
        $this->limit   = $originalLimit;
        $this->offset  = $originalOffset;

        return $result[0]['aggregate'] ?? null;
    }

    public function max(string $column)
    {
        return $this->aggregate('MAX', $column);
    }

    public function min(string $column)
    {
        return $this->aggregate('MIN', $column);
    }

    public function avg(string $column)
    {
        return (float) $this->aggregate('AVG', $column);
    }

    public function sum(string $column)
    {
        return $this->aggregate('SUM', $column);
    }

    public function increment(string $column, int|float $value = 1): bool
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Increment value must be numeric.");
        }

        $whereSql = $this->compileWheres();

        $sql = "UPDATE {$this->table}
                SET {$column} = {$column} + ?";

        if ($whereSql !== '') {
            $sql .= " WHERE {$whereSql}";
        }

        // bindings do UPDATE vêm antes
        $bindings = array_merge([$value], $this->bindings);

        self::run($sql, $bindings, [
            'connection' => $this->connection,
            // sem fetch → executor retorna bool
        ]);

        return true;
    }

    public function decrement(string $column, int|float $value = 1): bool
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Decrement value must be numeric.");
        }

        $whereSql = $this->compileWheres();

        $sql = "UPDATE {$this->table}
                SET {$column} = {$column} - ?";

        if ($whereSql !== '') {
            $sql .= " WHERE {$whereSql}";
        }

        $bindings = array_merge([$value], $this->bindings);

        self::run($sql, $bindings, [
            'connection' => $this->connection,
        ]);

        return true;
    }

    /* ===================================================== */
    /* WHERE                                                */
    /* ===================================================== */

    public function where(string $column, string $operator, mixed $value): self
    {
        $this->validateOperator($operator);

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => 'AND',
        ];

        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        $this->validateOperator($operator);

        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => 'OR',
        ];

        return $this;
    }

    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type'     => 'raw',
            'sql'      => $sql,
            'bindings' => $bindings,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            throw new \InvalidArgumentException('whereIn não aceita array vazio.');
        }

        $this->wheres[] = [
            'type'    => 'in',
            'column'  => $column,
            'values'  => array_values($values),
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function orWhereIn(string $column, array $values): self
    {
        if ($values === []) {
            throw new \InvalidArgumentException('orWhereIn não aceita array vazio.');
        }

        $this->wheres[] = [
            'type'    => 'in',
            'column'  => $column,
            'values'  => array_values($values),
            'boolean' => 'OR',
        ];

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        if ($values === []) {
            throw new \InvalidArgumentException('whereNotIn não aceita array vazio.');
        }

        $this->wheres[] = [
            'type'    => 'not_in',
            'column'  => $column,
            'values'  => array_values($values),
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function orWhereNotIn(string $column, array $values): self
    {
        if ($values === []) {
            throw new \InvalidArgumentException('orWhereNotIn não aceita array vazio.');
        }

        $this->wheres[] = [
            'type'    => 'not_in',
            'column'  => $column,
            'values'  => array_values($values),
            'boolean' => 'OR',
        ];

        return $this;
    }

    public function whereEmpty(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type'    => 'empty',
            'column'  => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereEmpty(string $column): self
    {
        return $this->whereEmpty($column, 'OR');
    }

    public function whereNotEmpty(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type'    => 'not_empty',
            'column'  => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereNotEmpty(string $column): self
    {
        return $this->whereNotEmpty($column, 'OR');
    }

    public function whereExists(string $subquery): self
    {
        $this->wheres[] = [
            'type'    => 'exists',
            'query'   => $subquery,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function orWhereExists(string $subquery): self
    {
        $this->wheres[] = [
            'type'    => 'exists',
            'query'   => $subquery,
            'boolean' => 'OR',
        ];

        return $this;
    }

    public function whereNotExists(string $subquery): self
    {
        $this->wheres[] = [
            'type'    => 'not_exists',
            'query'   => $subquery,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function orWhereNotExists(string $subquery): self
    {
        $this->wheres[] = [
            'type'    => 'not_exists',
            'query'   => $subquery,
            'boolean' => 'OR',
        ];

        return $this;
    }

    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type'    => 'null',
            'column'  => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'OR');
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type'    => 'not_null',
            'column'  => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function orWhereNotNull(string $column): self
    {
        return $this->whereNotNull($column, 'OR');
    }

    public function whereBetween(string $column, $start, $end, string $boolean = 'AND'): self {
        $this->wheres[] = [
            'type'    => 'between',
            'column'  => $column,
            'values'  => [$start, $end],
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotBetween(string $column, $start, $end, string $boolean = 'AND'): self {
        $this->wheres[] = [
            'type'    => 'not_between',
            'column'  => $column,
            'values'  => [$start, $end],
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereLike(string $column, string $value, string $boolean = 'AND'): self {
        return $this->whereRaw(
            "{$column} LIKE ?",
            ["%{$value}%"],
            $boolean
        );
    }

    public function whereStartLike(string $column, string $value, string $boolean = 'AND'): self {
        return $this->whereRaw(
            "{$column} LIKE ?",
            ["{$value}%"],
            $boolean
        );
    }

    public function whereEndLike(string $column, string $value, string $boolean = 'AND'): self {
        return $this->whereRaw(
            "{$column} LIKE ?",
            ["%{$value}"],
            $boolean
        );
    }

    public function whereReplaceLike(string $column, string $value, string $search = ' ', string $replace = '%', string $boolean = 'AND'): self {
        return $this->whereRaw(
            "{$column} LIKE REPLACE(?, ?, ?)",
            [$value, $search, $replace],
            $boolean
        );
    }

    public function whereRlike(string $column, string $pattern, string $boolean = 'AND'): self {
        return $this->whereRaw(
            "{$column} RLIKE ?",
            [$pattern],
            $boolean
        );
    }

    public function whereManyLike(string $column, string $value, string $booleanWords = 'AND', string $boolean = 'AND'): self {
        $words = array_values(array_filter(explode(' ', $value)));

        if (!$words) {
            return $this;
        }

        $sql = '(';
        $bindings = [];

        foreach ($words as $i => $word) {
            if ($i > 0) {
                $sql .= " {$booleanWords} ";
            }
            $sql .= "{$column} LIKE ?";
            $bindings[] = "%{$word}%";
        }

        $sql .= ')';

        return $this->whereRaw($sql, $bindings, $boolean);
    }

    public function whereDate(string $column, string $date, string $operator = '=', string $boolean = 'AND'): self {
        $this->validateOperator($operator);

        return $this->whereRaw(
            "DATE({$column}) {$operator} ?",
            [$date],
            $boolean
        );
    }

    public function whereTime(string $column, string $time, string $operator = '=', string $boolean = 'AND'): self {
        $this->validateOperator($operator);

        return $this->whereRaw(
            "TIME({$column}) {$operator} ?",
            [$time],
            $boolean
        );
    }

    public function whereDay(string $column, int $day, string $operator = '=', string $boolean = 'AND'): self {
        $this->validateOperator($operator);

        return $this->whereRaw(
            "DAY({$column}) {$operator} ?",
            [$day],
            $boolean
        );
    }

    public function whereMonth(string $column, int $month, string $operator = '=', string $boolean = 'AND'): self {
        $this->validateOperator($operator);

        return $this->whereRaw(
            "MONTH({$column}) {$operator} ?",
            [$month],
            $boolean
        );
    }

    public function whereYear(string $column, int $year, string $operator = '=', string $boolean = 'AND'): self {
        $this->validateOperator($operator);

        return $this->whereRaw(
            "YEAR({$column}) {$operator} ?",
            [$year],
            $boolean
        );
    }

    public function whereMatchAgainst(string|array $columns, string $value, string $mode = 'natural', string $boolean = 'AND'): self {
        $modes = [
            'natural' => 'IN NATURAL LANGUAGE MODE',
            'boolean' => 'IN BOOLEAN MODE',
        ];

        if (!isset($modes[$mode])) {
            throw new \InvalidArgumentException("Modo MATCH inválido: {$mode}");
        }

        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        }

        $sql = "MATCH ({$columns}) AGAINST (? {$modes[$mode]})";

        return $this->whereRaw($sql, [$value], $boolean);
    }

    public function whereRegex(string $column, string $pattern, string $boolean = 'AND'): self {
        return $this->whereRaw(
            "{$column} REGEXP ?",
            [$pattern],
            $boolean
        );
    }

    /**
     * $query
        *->where('status', '=', 1)
        *->whereGroup(function ($q) {
        *   $q->where('name', 'LIKE', '%john%')
        *     ->orWhere('email', 'LIKE', '%john%');
        *})
        *->where('active', '=', 1)
    *
    */
    public function whereGroup(callable $callback, string $boolean = 'AND'): self
    {
        $query = new self($this->table);

        $callback($query);

        if (empty($query->wheres)) {
            return $this;
        }

        $this->wheres[] = [
            'type'    => 'group',
            'query'   => $query,
            'boolean' => $boolean,
        ];

        return $this;
    }


    public function orWhereGroup(callable $callback): self
    {
        return $this->whereGroup($callback, 'OR');
    }

    public function whereSubquery(string $column, self $subquery, string $operator = 'IN', string $boolean = 'AND'): self {

        $this->validateOperator($operator, ['IN', 'NOT IN']);

        $this->wheres[] = [
            'type'     => 'subquery',
            'column'   => $column,
            'operator' => $operator,
            'query'    => $subquery,
            'boolean'  => $boolean,
        ];

        return $this;
    }

    public function orWhereSubquery(string $column, self $subquery, string $operator = 'IN'): self {
        return $this->whereSubquery($column, $subquery, $operator, 'OR');
    }


    /* ===================================================== */
    /* GROUP / HAVING                                        */
    /* ===================================================== */

    public function groupBy(string|array ...$columns): self
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }

        foreach ($columns as $column) {
            $this->groups[] = $column;
        }

        return $this;
    }

    public function having(string $column, string $operator, $value): self
    {
        $this->validateOperator($operator);

        $this->havings[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => strtoupper($operator),
            'boolean'  => 'AND',
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /* ===================================================== */
    /* ORDER BY                                              */
    /* ===================================================== */

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Direção inválida");
        }

        $this->orders[] = "{$column} {$direction}";
        return $this;
    }

    public function orderByField(
        string $column,
        array $values,
        string $direction = 'ASC'
    ): self {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Direção inválida: {$direction}");
        }

        if (empty($values)) {
            return $this;
        }

        // cria placeholders (?, ?, ?)
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $this->orders[] = "FIELD({$column}, {$placeholders}) {$direction}";

        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function orderByRaw(string $expression): self
    {
        if (trim($expression) === '') {
            return $this;
        }

        $this->orders[] = $expression;

        return $this;
    }

    public function orderByRandom(): self
    {
        return match (Connection::driver()) {
            'pgsql', 'sqlite' => $this->orderByRaw('RANDOM()'),
            default           => $this->orderByRaw('RAND()'),
        };
    }

    /* ===================================================== */
    /* LIMITS                                                  */
    /* ===================================================== */

    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit  = $limit;
        $this->offset = $offset;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /* ===================================================== */
    /* CRUD                                                  */
    /* ===================================================== */

    public function insert(array $data)
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Tabela não definida.');
        }

        $table = self::stripAlias($this->table);

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO {$table}
            (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

        $bindings = array_values($data);

        self::run($sql, $bindings, [
            'connection' => $this->connection,
        ]);

        return (object) [
            'id'     => Connection::lastInsertId($this->connection),
            'result' => true
        ];
    }

    public function insertIfNotExists(array $data, self $whereQuery): bool
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Tabela não definida.');
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $whereSql = $whereQuery->compileWheres();

        $sql = "INSERT INTO {$this->table}
            (" . implode(', ', $columns) . ")
            SELECT " . implode(', ', $placeholders) . "
            WHERE NOT EXISTS (
                SELECT 1 FROM {$this->table}
                WHERE {$whereSql}
            )";

        $bindings = array_merge(
            array_values($data),
            $whereQuery->bindings
        );

        self::run($sql, $bindings, [
            'connection' => $this->connection,
        ]);

        return true;
    }

    public function insertMany(array $columns, array $rows, int $batchSize = 500, bool $rowsIndexed = false): int
    {
        if (!$rows) return 0;

        if (empty($this->table)) {
            throw new \RuntimeException('Tabela não definida.');
        }

        $colCount = count($columns);
        if ($colCount === 0) return 0;

        // Limite seguro de placeholders (MySQL costuma aguentar bem isso; você já usa 20000)
        $maxPlaceholders = 20000;
        $batchSize = min($batchSize, (int) floor($maxPlaceholders / $colCount));
        if ($batchSize < 1) $batchSize = 1;

        $totalInserted = 0;

        try {

            $table = self::stripAlias($this->table);

            $placeRow = '(' . implode(',', array_fill(0, $colCount, '?')) . ')';
            $colsSql  = '`' . implode('`,`', $columns) . '`';

            foreach (array_chunk($rows, $batchSize) as $chunk) {

                $valuesSql = implode(',', array_fill(0, count($chunk), $placeRow));
                $sql = "INSERT INTO {$table} ({$colsSql}) VALUES {$valuesSql}";

                $bindings = [];

                if ($rowsIndexed) {
                    // 🚀 modo rápido: cada row já é array numérico na ordem de $columns
                    foreach ($chunk as $r) {
                        // garante tamanho certo sem explode
                        // se vier menor, completa com null; se vier maior, corta
                        $r = array_values((array)$r);
                        $len = count($r);

                        if ($len < $colCount) {
                            $r = array_merge($r, array_fill(0, $colCount - $len, null));
                        } elseif ($len > $colCount) {
                            $r = array_slice($r, 0, $colCount);
                        }

                        foreach ($r as $v) $bindings[] = $v;
                    }
                } else {
                    // ✅ modo compatível (atual): row associativo por nome da coluna
                    foreach ($chunk as $r) {
                        foreach ($columns as $c) {
                            $bindings[] = $r[$c] ?? null;
                        }
                    }
                }

                $affected = (int) self::run($sql, $bindings, [
                    'connection' => $this->connection,
                    'fetch'      => null,
                    'return'     => 'count',
                ]);

                $totalInserted += $affected;
            }

            return $totalInserted;

        } catch (\Throwable $e) {
            throw $e;
        }
    }




    // public function update(array $data): bool
    // {
    //     if (empty($this->wheres)) {
    //         throw new \RuntimeException('Update sem WHERE não é permitido.');
    //     }

    //     $set = [];
    //     $bindings = [];

    //     foreach ($data as $column => $value) {
    //         $set[] = "{$column} = ?";
    //         $bindings[] = $value; // bindings do SET
    //     }

    //     $sql = "UPDATE {$this->table}
    //             SET " . implode(', ', $set) . "
    //             WHERE " . $this->compileWheres();

    //     // ⬇️ agora SIM: bindings do WHERE
    //     $bindings = array_merge(
    //         $bindings,
    //         $this->extractWhereBindings()
    //     );

    //     self::run($sql, $bindings, [
    //         'connection' => $this->connection,
    //     ]);

    //     return true;
    // }

    public function update(array $values): bool
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('Update sem WHERE não é permitido.');
        }

        // Monta a parte SET
        $set = [];
        foreach ($values as $column => $value) {
            $set[] = "{$column} = ?";
            $this->bindings[] = $value;  // Adiciona as bindings
        }

        $setSql = implode(", ", $set);  // Cria a string para a parte SET

        // Cria o SQL com WHERE usando compileWheres()
        $sql = "UPDATE {$this->table} SET {$setSql} WHERE " . $this->compileWheres();

        // Executa a consulta com as bindings
        self::run($sql, $this->bindings, [
            'connection' => $this->connection,
        ]);

        return true;
    }

    public function delete(): bool
    {
        return $this->deleteRows() > 0;
    }

    public function deleteRows(): int
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('Delete sem WHERE não é permitido.');
        }

        $table = $this->stripAlias($this->table);

        $sql = "DELETE FROM {$table} WHERE " . $this->compileWheres();

        return (int) self::run($sql, $this->bindings, [
            'connection' => $this->connection,
            'return'     => 'count',
        ]);
    }


    /* ===================================================== */
    /* RAW EXECUTE                                          */
    /* ===================================================== */
    public static function execute(string $sql, array $values = [], bool $throw = true)
    {
        self::validateQuery($sql, $throw);

        $processed = self::processInClauses($sql, $values);

        // Detecta tipo de comando
        $command = strtoupper(strtok(trim($sql), ' '));

        return self::run(
            $processed['sql'],
            $processed['values'],
            [
                // SELECT retorna dados
                'fetch' => $command === 'SELECT'
                    ? \PDO::FETCH_OBJ
                    : null
            ]
        );
    }

    /* ===================================================== */
    /* COMPILERS                                            */
    /* ===================================================== */

    protected function builder(?int $forceLimit = null): array
    {
        // reset bindings
        $this->bindings = [];

        $sql = $this->compileSelect();

        // JOINS
        $sql .= $this->compileJoins();

        // WHERES
        if ($this->wheres) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        // GROUP BY
        $sql .= $this->compileGroupBy();

        // HAVING
        if ($this->havings) {
            foreach ($this->havings as $having) {
                $this->bindings[] = $having['value'];
            }
            $sql .= $this->compileHaving();
        }

        // ORDER BY
        $sql .= $this->compileOrders();

        // LIMIT / OFFSET
        if ($forceLimit !== null) {
            $sql .= " LIMIT {$forceLimit}";
        } else {
            $sql .= $this->compileLimit();
        }

        return [$sql, $this->bindings];
    }

    protected function compileSelect(): string
    {

        $sql = 'SELECT ';

        // DISTINCT
        if ($this->distinct) {
            if (!empty($this->distinctColumns)) {
                $sql .= 'DISTINCT ' . implode(', ', $this->distinctColumns);
            } else {
                $sql .= 'DISTINCT ';
            }
        }

        // COLUNAS
        $sql .= implode(', ', $this->columns);

        // FROM
        $sql .= " FROM {$this->table}";

        return $sql;
    }

    protected function compileJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';

        foreach ($this->joins as $join) {

            // CROSS JOIN
            if ($join['type'] === 'CROSS') {
                $sql .= " CROSS JOIN {$join['table']}";
                continue;
            }

            // INNER / LEFT / RIGHT
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        return $sql;
    }

    protected function compileWheres(): string
    {

        $sql = '';

        foreach ($this->wheres as $index => $where) {

            $prefix = $index === 0
                ? ''
                : ' ' . $where['boolean'] . ' ';

            switch ($where['type']) {

                // coluna OP ?
                case 'basic':
                    $sql .= $prefix . "{$where['column']} {$where['operator']} ?";
                    $this->bindings[] = $where['value'];
                    break;

                // EXISTS (subquery string)
                case 'exists':
                    $sql .= $prefix . "EXISTS ({$where['query']})";
                    break;

                case 'not_exists':
                    $sql .= $prefix . "NOT EXISTS ({$where['query']})";
                    break;

                // IS NULL
                case 'null':
                    $sql .= $prefix . "{$where['column']} IS NULL";
                    break;

                case 'not_null':
                    $sql .= $prefix . "{$where['column']} IS NOT NULL";
                    break;

                // IN (...)
                case 'in':
                    $placeholders = implode(
                        ', ',
                        array_fill(0, count($where['values']), '?')
                    );

                    $sql .= $prefix . "{$where['column']} IN ({$placeholders})";

                    foreach ($where['values'] as $value) {
                        $this->bindings[] = $value;
                    }
                    break;

                // NOT IN (...)
                case 'not_in':
                    $placeholders = implode(
                        ', ',
                        array_fill(0, count($where['values']), '?')
                    );

                    $sql .= $prefix . "{$where['column']} NOT IN ({$placeholders})";

                    foreach ($where['values'] as $value) {
                        $this->bindings[] = $value;
                    }
                    break;

                // coluna = ''
                case 'empty':
                    $sql .= $prefix . "{$where['column']} = ''";
                    break;

                // coluna <> ''
                case 'not_empty':
                    $sql .= $prefix . "{$where['column']} <> ''";
                    break;

                // BETWEEN ? AND ?
                case 'between':
                    $sql .= $prefix . "{$where['column']} BETWEEN ? AND ?";
                    $this->bindings[] = $where['values'][0];
                    $this->bindings[] = $where['values'][1];
                    break;

                case 'not_between':
                    $sql .= $prefix . "{$where['column']} NOT BETWEEN ? AND ?";
                    $this->bindings[] = $where['values'][0];
                    $this->bindings[] = $where['values'][1];
                    break;

                // (grupo)
                case 'group':
                    $nestedSql = $where['query']->compileWheres();
                    $sql .= $prefix . "({$nestedSql})";

                    // propaga bindings do grupo
                    $this->bindings = array_merge(
                        $this->bindings,
                        $where['query']->bindings
                    );
                    break;

                // coluna OP (subquery builder)
                case 'subquery':
                    $subSql = $where['query']->toSql();
                    $sql .= $prefix . "{$where['column']} {$where['operator']} ({$subSql})";

                    $this->bindings = array_merge(
                        $this->bindings,
                        $where['query']->bindings
                    );
                    break;

                // SQL livre
                case 'raw':
                    $sql .= $prefix . $where['sql'];

                    if (!empty($where['bindings'])) {
                        $this->bindings = array_merge(
                            $this->bindings,
                            $where['bindings']
                        );
                    }
                    break;

                default:
                    throw new \RuntimeException(
                        "Tipo de WHERE não suportado: {$where['type']}"
                    );
            }
        }

        return $sql;
    }

    protected function compileWhereOnly(): array
    {
        if (!$this->wheres) {
            return ['', []];
        }

        $this->bindings = [];

        $sql = $this->compileWheres();
        $bindings = $this->bindings;

        return [$sql, $bindings];
    }

    protected function compileOrders(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', $this->orders);
    }

    protected function compileGroupBy(): string
    {
        if (empty($this->groups)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->groups);
    }

    protected function compileHaving(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $sql = '';

        foreach ($this->havings as $index => $having) {
            $prefix = $index === 0
                ? ''
                : ' ' . $having['boolean'] . ' ';

            switch ($having['type']) {
                case 'basic':
                    $sql .= $prefix . "{$having['column']} {$having['operator']} ?";
                    break;

                default:
                    throw new \RuntimeException(
                        "Tipo de HAVING não suportado"
                    );
            }
        }

        return ' HAVING ' . $sql;
    }
    protected function compileLimit(): string
    {
        if ($this->limit === null) {
            return '';
        }

        // MySQL / SQLite
        if (Connection::driver() !== 'pgsql') {
            if ($this->offset) {
                return " LIMIT {$this->offset}, {$this->limit}";
            }

            return " LIMIT {$this->limit}";
        }

        // PostgreSQL
        $sql = " LIMIT {$this->limit}";

        if ($this->offset) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }


    /* ===================================================== */
    /* HELPERS                                              */
    /* ===================================================== */
    protected function renameTable(string $table, ?string $alias = null): string
    {
        if ($alias) {
            return "`{$table}` AS {$alias}";
        }

        // detecta alias inline
        if (preg_match('/^(.+)\s+as\s+(.+)$/i', $table, $m)) {
            return "`{$m[1]}` AS {$m[2]}";
        }

        return "`{$table}`";
    }

    protected static function stripAlias(string $table): string
    {
        // normaliza espaços (tabs, múltiplos espaços etc.)
        $table = trim(preg_replace('/\s+/', ' ', $table));

        if ($table === '') return $table;

        // Caso: "tabela AS alias" (alias pode estar com ou sem backticks)
        $posAs = stripos($table, ' as ');
        if ($posAs !== false) {
            return trim(substr($table, 0, $posAs));
        }

        // Caso: "tabela alias" (sem AS) -> pega só antes do primeiro espaço
        // Preserva schema.tabela e `schema`.`tabela` porque não têm espaço.
        $parts = explode(' ', $table, 2);
        return trim($parts[0]);
    }


    /* ===================================================== */
    /* VALIDATORS                                            */
    /* ===================================================== */

    protected function validateOperator(string $operator, ?array $allowed = null): void
    {
        $operator = strtoupper($operator);

        // Caso especial: lista explícita (ex: subquery)
        if ($allowed !== null) {
            if (!in_array($operator, $allowed, true)) {
                throw new \InvalidArgumentException(
                    "Operador inválido: {$operator}"
                );
            }
            return;
        }

        $allowed = [
            '=', '!=', '<>', '<', '>', '<=', '>=',
            'LIKE', 'NOT LIKE',
            'IN', 'NOT IN',
            'BETWEEN', 'NOT BETWEEN',
        ];

        if (!in_array($operator, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Operador inválido: {$operator}"
            );
        }
    }

    private static function validateQuery($query, $throw = true)
    {
        // Seu código de validação exatamente como você postou.
        // Nenhuma alteração é necessária aqui.
        if (empty($query) || !is_string($query)) {
            $msg = "Consulta SQL inválida ou vazia.";
            return $throw
                ? throw new Exception($msg)
                : ["error" => true, "message" => $msg];
        }

        $trimmed = trim($query);
        $upperStmt = strtoupper($trimmed);
        $command = strtok($upperStmt, " ");

        $requiredParts = [
            'SELECT'   => ['FROM'],
            'UPDATE'   => ['SET'],
            'DELETE'   => ['FROM'],
            'INSERT'   => ['INTO'],
            'CREATE'   => ['TABLE', 'DATABASE'],
            'DROP'     => ['TABLE', 'DATABASE'],
            'ALTER'    => ['TABLE'],
            'TRUNCATE' => ['TABLE'],
        ];

        if (isset($requiredParts[$command])) {
            $required = $requiredParts[$command];

            if (in_array($command, ['CREATE', 'DROP'])) {
                $hasAny = false;
                foreach ($required as $part) {
                    if (strpos($upperStmt, $part) !== false) {
                        $hasAny = true;
                        break;
                    }
                }
                if (!$hasAny) {
                    $msg = "A instrução $command deve conter TABLE ou DATABASE.";
                    return $throw
                        ? throw new Exception($msg)
                        : ["error" => true, "message" => $msg];
                }
            } else {
                foreach ($required as $part) {
                    if (strpos($upperStmt, $part) === false) {
                        $msg = "A instrução $command deve conter a cláusula obrigatória: $part.";
                        return $throw ? throw new Exception($msg) : ["error" => true, "message" => $msg];
                    }
                }
            }
        }

        return ["error" => false];
    }


    private static function processInClauses(string $sql, array $values): array
    {
        // Se não há valores, não há nada a processar. Retorna a query original.
        if (empty($values)) {
            return ['sql' => $sql, 'values' => []];
        }

        $newValues = [];
        $sqlParts = explode('?', $sql);

        // Validação: O número de '?' deve corresponder ao número de valores.
        // Se não corresponder, algo está errado na chamada do método.
        if (count($sqlParts) - 1 !== count($values)) {
            // Você pode lançar uma exceção aqui se preferir um erro mais explícito.
            // Por exemplo: throw new \\InvalidArgumentException("O número de placeholders '?' não corresponde ao número de valores passados.");
            // Por enquanto, vamos apenas retornar sem processar para evitar o warning.
            return ['sql' => $sql, 'values' => $values];
        }

        $newSql = '';

        foreach ($values as $i => $value) {
            $newSql .= $sqlParts[$i];

            if (is_array($value)) {
                if (empty($value)) {
                    $newSql .= '0';
                } else {
                    $placeholders = implode(',', array_fill(0, count($value), '?'));
                    $newSql .= $placeholders;
                    $newValues = [...$newValues, ...$value];
                }
            } else {
                $newSql .= '?';
                $newValues[] = $value;
            }
        }

        // Adiciona a parte final da string SQL, que vem depois do último '?'
        $newSql .= $sqlParts[count($values)];

        return ['sql' => $newSql, 'values' => $newValues];
    }

    /* ===================================================== */
    /* SINTAX SUGAR                                          */
    /* ===================================================== */

    public function tap(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    public function ddSql(bool $raw = true): never
    {
        if ($raw) {
            dd($this->toRawSql());
        }

        [$sql, $bindings] = $this->builder();
        dd($sql, $bindings);
    }

    public function when(mixed $condition, callable $callback, ?callable $default = null): self
    {
        if ($condition) {
            // passa o builder e a condição
            $callback($this, $condition);
        } elseif ($default) {
            $default($this);
        }

        return $this;
    }

    public function unless(mixed $condition, callable $callback, ?callable $default = null): self
    {
        if (!$condition) {
            $callback($this, $condition);
        } elseif ($default) {
            $default($this);
        }

        return $this;
    }

    /* ===================================================== */
    /* PROCESS                                               */
    /* ===================================================== */

    public function chunk(int $count, callable $callback): bool
    {
        if ($count <= 0) {
            throw new \InvalidArgumentException('Chunk size deve ser maior que zero');
        }

        $page = 0;

        while (true) {

            $clone = clone $this;

            $results = $clone
                ->limit($count)
                ->offset($page * $count)
                ->get();

            if (empty($results)) {
                break;
            }

            // se callback retornar false, interrompe
            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        }

        return true;
    }

    public function chunkById(int $count, callable $callback, string $column = 'id' ): bool
    {
        if ($count <= 0) {
            throw new \InvalidArgumentException('Chunk size inválido');
        }

        $lastId = null;

        while (true) {

            $clone = clone $this;

            if ($lastId !== null) {
                $clone->where($column, '>', $lastId);
            }

            $rows = $clone
                ->orderBy($column)
                ->limit($count)
                ->get();

            if (empty($rows)) {
                break;
            }

            if ($callback($rows) === false) {
                return false;
            }

            $last = end($rows);
            $lastId = $last->{$column};
        }

        return true;
    }


    public function cursorQuery(string $sql, array $bindings = []): \Generator
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($bindings);

        while ($row = $stmt->fetch($this->fetchStyle)) {
            yield $row;
        }
    }

    public function cursor(): \Generator
    {
        [$sql, $bindings] = $this->builder();

        return DB::cursorQuery($sql, $bindings);
    }

    public function each(callable $callback): bool
    {
        foreach ($this->cursor() as $row) {
            if ($callback($row) === false) {
                return false;
            }
        }

        return true;
    }

    public function lazy(): \Generator
    {
        return $this->cursor();
    }

    /* ===================================================== */
    /* TRANSACTIONS                                         */
    /* ===================================================== */

    public static function beginTransaction(?string $connection = null): void
    {
        Connection::beginTransaction($connection);
    }

    public static function commit(?string $connection = null): void
    {
        Connection::commit($connection);
    }

    public static function rollback(?string $connection = null): void
    {
        Connection::rollBack($connection);
    }

    public static function transaction(callable $callback, ?string $connection = null)
    {
        self::beginTransaction($connection);

        try {
            $result = $callback();

            self::commit($connection);

            return $result;
        } catch (\Throwable $e) {
            self::rollback($connection);
            throw $e;
        }
    }

}
