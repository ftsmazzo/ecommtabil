<?php
/**
 * Classe de Conexão com o Banco de Dados
 */

namespace App\Core;

use PDO;
use Exception;

class Connection
{
    protected static array $config = [];
    protected static ?string $current = null;
    protected static array $connections = [];
    protected static array $transactions = [];

    /* -------------------------------------------------- */
    /* CONFIG                                             */
    /* -------------------------------------------------- */

    public static function loadConfig(array $config): void
    {
        self::$config = $config;
    }

    /* -------------------------------------------------- */
    /* CONNECTION                                         */
    /* -------------------------------------------------- */

    public static function get(?string $name = null): PDO
    {
        $name = $name ?? self::$config['default'] ?? null;

        if (!$name) {
            throw new Exception('Nenhuma conexão padrão definida.');
        }

        if (isset(self::$connections[$name])) {
            self::$current = $name;
            return self::$connections[$name];
        }

        $cfg = self::$config['connections'][$name] ?? null;

        if (!$cfg) {
            throw new Exception("Conexão '{$name}' não encontrada.");
        }

        switch ($cfg['driver']) {
            case 'mysql':
                $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
                break;

            case 'pgsql':
                $dsn = "pgsql:host={$cfg['host']};dbname={$cfg['database']}";
                break;

            case 'sqlite':
                $dsn = "sqlite:{$cfg['database']}";
                break;

            default:
                throw new Exception("Driver não suportado: {$cfg['driver']}");
        }

        $pdo = new PDO(
            $dsn,
            $cfg['username'] ?? null,
            $cfg['password'] ?? null,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        self::$connections[$name] = $pdo;
        self::$current = $name;

        return $pdo;
    }

    /* -------------------------------------------------- */
    /* DRIVER                                             */
    /* -------------------------------------------------- */

    public static function driver(?string $connection = null): string
    {
        $name = $connection ?? self::$current ?? self::$config['default'] ?? null;

        if (!$name) {
            return 'mysql';
        }

        return self::$config['connections'][$name]['driver'] ?? 'mysql';
    }

    /* -------------------------------------------------- */
    /* EXECUTOR (ORM)                                     */
    /* -------------------------------------------------- */

    public static function run(
        string $sql,
        array $bindings = [],
        ?string $connection = null,
        ?int $fetchStyle = PDO::FETCH_ASSOC,
        ?string $fetchClass = null,
        string $returnMode = 'bool'
    ): mixed {
        $pdo = self::get($connection);
        $stmt = $pdo->prepare($sql);

        foreach ($bindings as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }

        $stmt->execute();

        // ✅ prioridade: modo count
        if ($returnMode === 'count') {
            // $row = $stmt->fetch(PDO::FETCH_NUM);
            // return (int)($row[0] ?? 0);
            return (int)$stmt->rowCount();
        }

        // 👇 NÃO é SELECT
        if ($fetchStyle === null) {
            return true;
        }

        if (($fetchStyle & PDO::FETCH_CLASS) === PDO::FETCH_CLASS && $fetchClass) {
            return $stmt->fetchAll($fetchStyle, $fetchClass);
        }

        return $stmt->fetchAll($fetchStyle);
    }

    /* -------------------------------------------------- */
    /* EXECUTE (INSERT / UPDATE / DELETE)                 */
    /* -------------------------------------------------- */

    public static function execute(
        string $sql,
        array $bindings = [],
        ?string $connection = null
    ): bool {
        $pdo = self::get($connection);
        $stmt = $pdo->prepare($sql);

        foreach ($bindings as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }

        return $stmt->execute();
    }

    /* -------------------------------------------------- */
    /* HELPERS                                           */
    /* -------------------------------------------------- */

    public static function lastInsertId(?string $connection = null): string|false
    {
        return self::get($connection)->lastInsertId();
    }

    /* -------------------------------------------------- */
    /* TRANSACTIONS                                       */
    /* -------------------------------------------------- */

    public static function beginTransaction(?string $connection = null): void
    {
        $name = $connection ?? self::$current ?? self::$config['default'] ?? null;

        if (!$name) {
            throw new Exception('Nenhuma conexão definida para iniciar transação.');
        }

        $pdo = self::get($name);

        if (!isset(self::$transactions[$name])) {
            self::$transactions[$name] = 0;
        }

        // primeira transação → abre no PDO
        if (self::$transactions[$name] === 0) {
            $pdo->beginTransaction();
        }

        self::$transactions[$name]++;
    }

    public static function commit(?string $connection = null): void
    {
        $name = $connection ?? self::$current ?? self::$config['default'] ?? null;

        if (!$name || empty(self::$transactions[$name])) {
            return;
        }

        self::$transactions[$name]--;

        // só commita quando sair da última transação
        if (self::$transactions[$name] === 0) {
            self::get($name)->commit();
        }
    }

    public static function rollBack(?string $connection = null): void
    {
        $name = $connection ?? self::$current ?? self::$config['default'] ?? null;

        if (!$name || empty(self::$transactions[$name])) {
            return;
        }

        // rollback sempre desfaz tudo
        self::$transactions[$name] = 0;

        self::get($name)->rollBack();
    }

    public static function inTransaction(?string $connection = null): bool
    {
        $name = $connection ?? self::$current ?? self::$config['default'] ?? null;

        return isset(self::$transactions[$name]) && self::$transactions[$name] > 0;
    }

    public static function getConfig(?string $name = null): array
    {
        $name = $name ?? self::$config['default'] ?? null;

        if (!$name) {
            throw new \Exception('Nenhuma conexão padrão definida.');
        }

        $cfg = self::$config['connections'][$name] ?? null;

        if (!$cfg) {
            throw new \Exception("Conexão '{$name}' não encontrada.");
        }

        return $cfg;
    }

}
