<?php

namespace App\Core;

use App\Core\DB;
use BadMethodCallException;
use Exception;
use stdClass;

abstract class Model
{
    protected static string $table;
    protected static string $primary = 'id';
    protected static ?string $alias = null;

    protected static array $map = [];
    protected static array $hidden = [];
    protected static array $uppers = [];
    protected static array $files = [];
    protected static array $nulls = [];
    protected static array $fillable = [];
    protected static array $appends = [];
    protected static array $required = [];
    protected static array $optional = [];
    protected static ?string $mediaPath = null;

    protected array $original = [];

    protected stdClass $dataModel;
    protected bool $hydrated = false;

    public function __construct(array|object $attributes = [])
    {

        if (!isset($this->dataModel)) {
            $this->dataModel = new stdClass();
        }

        foreach ((array) $attributes as $key => $value) {
            // ignora propriedades internas do PHP
            if (str_starts_with($key, "\0")) {
                continue;
            }

            $this->dataModel->$key = $value;
        }
    }

    public function __isset($name): bool
    {
        return $this->getAttribute((string)$name) !== null;
    }

    /* ------------------------------------------------------------ */
    /* QUERY BRIDGE                                                 */
    /* ------------------------------------------------------------ */

    // Retorna classe do Model
    protected static function query(): DB
    {
        $query = DB::table(
            static::$table,
            static::$alias
        )->fetch('class', static::class, \PDO::FETCH_PROPS_LATE);

        if (method_exists(static::class, 'applySoftDeleteScope')) {
            $query = static::applySoftDeleteScope($query);
        }

        return $query;
    }

    // Retorna um array de objetos
    // protected static function query(): DB
    // {
    //     $query = DB::table(
    //         static::$table,
    //         static::$alias
    //     )->fetch('obj');

    //     if (method_exists(static::class, 'applySoftDeleteScope')) {
    //         $query = static::applySoftDeleteScope($query);
    //     }

    //     return $query;
    // }

    public static function __callStatic($method, $arguments)
    {
        // se o método existe no Model, usa ele
        if (method_exists(static::class, $method)) {
            return static::$method(...$arguments);
        }

        // senão, delega para o QueryBuilder
        $query = static::query();

        if (!method_exists($query, $method)) {
            throw new BadMethodCallException("Método {$method} não existe");
        }

        return $query->$method(...$arguments);
    }

    public function __call($method, $args)
    {
        throw new \LogicException(
            "Método {$method} não existe no Model. QueryBuilder ≠ ActiveRecord."
        );
    }

    /* ------------------------------------------------------------ */
    /* DATA ACCESS                                                  */
    /* ------------------------------------------------------------ */

    /**
     * Obtém o valor de uma propriedade da classe.
     *
     * Este método mágica é chamado quando uma propriedade não acessível é acessada.
     * Ele retorna o valor armazenado na propriedade `$data` correspondente ao nome fornecido.
     * Se a propriedade não existir, retorna null.
     *
     * @param string $name  O nome da propriedade a ser obtida.
     * @return mixed|null   O valor da propriedade, ou null se não existir.
     */
    public function __get($name)
    {
        return $this->getAttribute($name);
    }

    public function getAttribute(string $key): mixed
    {
        // 1) Dado carregado do banco / join
        if (isset($this->dataModel) && property_exists($this->dataModel, $key)) {
            return $this->dataModel->$key;
        }

        // 2) Accessor: getXxxAttribute()
        $method = 'get' . str_replace(
            ' ',
            '',
            ucwords(str_replace(['-', '_'], ' ', $key))
        ) . 'Attribute';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        // 3) Propriedade real do objeto (caso exista)
        if (property_exists($this, $key)) {
            return $this->$key;
        }

        return null;
    }


    /**
     * Define um valor para uma propriedade da classe.
     *
     * Este método mágica é chamado quando uma propriedade não acessível é atribuída.
     * Ele armazena o valor fornecido na propriedade `$data` da classe.
     *
     * @param string $name  O nome da propriedade a ser definida.
     * @param mixed $value  O valor a ser atribuído à propriedade.
     */
    public function __set($name, $value)
    {
        if (!isset($this->dataModel)) {
            $this->dataModel = new \stdClass();
        }

        $this->dataModel->$name = $value;
    }

    public static function getTable(): string
    {
        return static::$table;
    }

    public static function getPrimary(): string
    {
        return static::$primary;
    }

    public static function getMap(): array
    {
        return static::$map ?? [];
    }

    public static function getHidden(): array
    {
        return static::$hidden ?? [];
    }

    public static function getNulls(): array
    {
        return static::$nulls ?? [];
    }

    public static function getFiles()
    {
        return static::$files ?? [];
    }

    public static function getRequired()
    {
        return static::$required ?? [];
    }

    public static function getFillable()
    {
        return static::$fillable ?? [];
    }

    public static function getOptional()
    {
        return static::$optional ?? [];
    }

    public static function getMediaPath(): string
    {
        return rtrim((string) static::$mediaPath, '/') . '/';
    }

    public static function getMediaFullPath(string $file = ''): string
    {
        return rtrim(static::$mediaPath ?? '', '/') . '/' . ltrim($file, '/');
    }

    public static function getUppers(): array
    {
        return static::$uppers ?? [];
    }

    public function getData(): object
    {
        return $this->dataModel;
    }

    public function toArray(): array
    {
        $arr = (array) $this->dataModel;

        if (!empty(static::$appends)) {
            foreach (static::$appends as $key) {
                $arr[$key] = $this->getAttribute($key);
            }
        }

        return $arr;
    }

    public static function getOrder(): int
    {
        $order = static::query()->max('ordem');

        return ($order ?? 0) + 1;
    }

    /* ------------------------------------------------------------ */
    /* FIND / LOAD                                                  */
    /* ------------------------------------------------------------ */

    public static function get(): array
    {
        $rows = static::query()->get();

        return array_map(
            fn ($row) => static::fromArray($row),
            $rows
        );
    }

    public static function first(): ?static
    {
        $row = static::query()->first();

        return $row ? static::fromArray($row) : null;
    }

    public static function find(mixed $id, ?string $column = null): ?static
    {
        $column ??= static::$primary;

        $field = (static::$alias ? static::$alias . '.' : '') . $column;

        $row = static::query()
            ->where($field, '=', $id)
            ->first();

        return $row ? static::fromArray($row) : null;
    }

    public static function findByMd5(string $hash): ?static
    {
        $col = (static::$alias ? static::$alias . '.' : '') . static::$primary;

        $row = static::query()
            ->whereRaw('MD5(' . $col . ') = ?', [$hash])
            ->first();

        return $row ? static::fromArray($row) : null;
    }

    public static function findBySha1(string $hash): ?static
    {
        $col = (static::$alias ? static::$alias . '.' : '') . static::$primary;

        $row = static::query()
            ->whereRaw('SHA1(' . $col . ') = ?', [$hash])
            ->first();

        return $row ? static::fromArray($row) : null;
    }

    public static function findBy(string $column, mixed $value): ?static
    {
        $row = static::query()
            ->where($column, '=', $value)
            ->first();

        return $row ? static::fromArray($row) : null;
    }

    public static function firstWhere(string $column, mixed $value): ?static
    {
        return static::findBy($column, $value);
    }

    public static function all(): array
    {
        $rows = static::query()->select()->get();

        return array_map(function ($row) {
            // Já é model → só garante sync
            if ($row instanceof static) {
                return $row;
            }

            // Veio como array / stdClass
            return static::fromArray((array) $row);
        }, $rows);
    }

    public function syncOriginal(): void
    {
        $this->original = (array) $this->dataModel;
        $this->hydrated = true;
    }

    // protected static function fromArray(array|object $row): static
    // {
    //     $model = new static((array) $row);
    //     $model->syncOriginal();
    //     return $model;
    // }

    protected static function fromArray(array|object $row): static
    {
        // 👉 JÁ É MODEL → só sincroniza
        if ($row instanceof static) {
            $row->syncOriginal();
            return $row;
        }

        // 👉 stdClass ou array
        $model = new static((array) $row);
        $model->syncOriginal();

        return $model;
    }

    /* ------------------------------------------------------------ */
    /* SAVE / DELETE                                                */
    /* ------------------------------------------------------------ */

    public static function create(array $fields): object
    {
        $fields = self::toUpper($fields);

        if (!empty(static::$fillable)) {
            $fields = array_intersect_key($fields, array_flip(static::$fillable));
        }

        $result = static::query()->insert($fields);

        if (!$result || !$result->result) {
            throw new Exception('Falha ao inserir registro');
        }

        return $result;
    }

    public static function clone(int $id, ?string $column = null): static
    {
        $column ??= static::$primary;

        $row = static::query()
            ->where($column, '=', $id)
            ->first();

        if (!$row) {
            throw new \RuntimeException('Registro não encontrado.');
        }

        // $data = (array) $row;
        $data = $row->toArray();

        unset($data[$column]);

        $data = self::toUpper($data);

        $result = static::query()->insert($data);

        if (!$result || !$result->result) {
            throw new \RuntimeException('Falha ao clonar registro.');
        }

        return static::find($result->id);
    }

    public static function updateBy(mixed $id, array $fields, ?string $column = null, string $operator = '='): bool
    {
        if (!$fields) {
            return false;
        }

        $column ??= static::$primary;
        $fields = self::toUpper($fields);

        $query = static::query()->where($column, $operator, $id);

        return $query->update($fields) > 0;
    }

    /***
     * ALIAS PARA O updateBy
     * APAGAR DEPOIS DE REFATORADO!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
     */
    public static function record(array $fields, mixed $id, ?string $column = null, string $operator = '='): bool
    {
        return static::updateBy($id, $fields, $column, $operator);
    }

    public function save(): mixed
    {
        $fields = $this->toPersistArray();

        // UPDATE?
        $id = $this->getAttribute(static::$primary);

        if ($id !== null) {
            // não tenta atualizar a PK
            unset($fields[static::$primary]);

            $fields = self::toUpper($fields);
            $dirty  = $this->getDirty($fields);

            if (!$dirty) {
                return $id;
            }

            static::query()
                ->where(static::$primary, '=', $id)
                ->update($dirty);

            $this->syncOriginal();
            return $id;
        }

        // INSERT
        $fields = self::toUpper($fields);

        $result = static::query()->insert($fields);

        if ($result?->result) {
            $this->dataModel->{static::$primary} = $result->id;
            $this->syncOriginal();
            return $result->id;
        }

        return false;
    }

    public function toPersistArray(): array
    {
        $data = (array) $this->dataModel;

        // remove atributos virtuais (appends)
        if (!empty(static::$appends)) {
            foreach (static::$appends as $k) {
                unset($data[$k]);
            }
        }

        // UPDATE
        if ($this->hydrated) {
            if (!empty(static::$fillable)) {
                return array_intersect_key($data, array_flip(static::$fillable));
            }

            return array_intersect_key($data, $this->original);
        }

        // INSERT
        if (!empty(static::$fillable)) {
            return array_intersect_key($data, array_flip(static::$fillable));
        }

        return $data;
    }


    public function delete(): bool
    {
        if (!isset($this->dataModel->{static::$primary})) {
            throw new RuntimeException('Model não persistido');
        }

        return (bool) static::query()
            ->where(static::$primary, '=', $this->dataModel->{static::$primary})
            ->delete();
    }

    public static function deleteById(mixed $id): bool
    {
        return (bool) static::query()
            ->where(static::$primary, '=', $id)
            ->delete();
    }

    public function refresh(): void
    {
        if (!isset($this->dataModel->{static::$primary})) {
            throw new RuntimeException('Model não persistido');
        }

        $fresh = static::find($this->dataModel->{static::$primary});

        if ($fresh) {
            $this->dataModel = $fresh->getData();
        }
    }

    /* ------------------------------------------------------------ */
    /* STATIC HELPERS                                               */
    /* ------------------------------------------------------------ */

    protected static function extractId(array &$fields): mixed
    {
        if (isset($fields[static::$primary])) {
            $id = $fields[static::$primary];
            unset($fields[static::$primary]);
            return $id;
        }
        return null;
    }

    protected static function toUpper(array $fields): array
    {
        if (!is_array(static::$uppers) || static::$uppers === []) {
            return $fields;
        }

        if (static::$uppers[0] === '*') {
            foreach ($fields as $k => $v) {
                if (is_string($v)) {
                    $v = trim($v);
                    if ($v !== '') {
                        $fields[$k] = mb_strtoupper($v, 'UTF-8');
                    }
                }
            }
            return $fields;
        }

        foreach (static::$uppers as $field) {
            if (isset($fields[$field]) && is_string($fields[$field])) {
                $v = trim($fields[$field]);
                if ($v !== '') {
                    $fields[$field] = mb_strtoupper($v, 'UTF-8');
                }
            }
        }

        return $fields;
    }

    public static function mapping(): ?array
    {
        if (empty(static::$map)) {
            return null;
        }

        $maps = [];

        foreach (static::$map as $column => $alias) {

            if (!empty(static::$hidden) && in_array($column, static::$hidden, true)) {
                continue;
            }

            $maps[] =
                (static::$alias ? static::$alias . '.' : '') .
                $column . ' AS ' . $alias;
        }

        return $maps;
    }

    public function fill(array $attributes): static
    {
        if (!isset($this->dataModel)) {
            $this->dataModel = new \stdClass();
        }

        foreach ($attributes as $key => $value) {

            // ignora lixo interno do PHP
            if (str_starts_with($key, "\0")) {
                continue;
            }

            // respeita fillable, se existir
            if (!empty(static::$fillable) && !in_array($key, static::$fillable, true)) {
                continue;
            }

            $this->dataModel->$key = $value;
        }

        return $this;
    }

    public function isDirty(): bool
    {
        return (array) $this->dataModel !== $this->original;
    }

    protected function getDirty(?array $fields = null): array
    {
        $current = $fields ?? (array) $this->dataModel;

        $dirty = [];

        foreach ($current as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                continue;
            }

            if ($this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    protected static function boot(): void
    {
        if (empty(static::$table)) {
            throw new \RuntimeException(
                static::class . ' não definiu a propriedade static::$table'
            );
        }
    }

    public function hash(string $algo = 'md5'): ?string
    {
        $id = $this->getAttribute(static::$primary);

        if ($id === null) {
            return null;
        }

        return match (strtolower($algo)) {
            'md5'  => md5((string) $id),
            'sha1' => sha1((string) $id),
            default => throw new \InvalidArgumentException(
                "Algoritmo de hash inválido: {$algo}"
            ),
        };
    }


}
