<?php

/**
 * Roda na subida do container (docker-entrypoint).
 * Usa as mesmas variáveis DB_* do EasyPanel / .env.
 */

declare(strict_types=1);

use App\Core\Env;
use App\Services\Database\Migrator;

$root = dirname(__DIR__, 2);
require $root . "/vendor/autoload.php";

Env::load($root . "/.env");

$host = (string) env("DB_HOST", "localhost");
$db   = (string) env("DB_DATABASE", "sistema");
$user = (string) env("DB_USERNAME", "root");
$pass = (string) env("DB_PASSWORD", "");
$port = (string) env("DB_PORT", "3306");
$charset = (string) env("DB_CHARSET", "utf8mb4");
if ($port === "") {
    $port = "3306";
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$pdo = null;
$ultimo = "";

for ($i = 1; $i <= 30; $i++) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        ]);
        break;
    } catch (PDOException $e) {
        $ultimo = $e->getMessage();
        fwrite(STDERR, "[migrate] MySQL indisponível (tentativa {$i}/30)\n");
        sleep(2);
    }
}

if (!$pdo) {
    fwrite(STDERR, "[migrate] falha ao conectar: {$ultimo}\n");
    exit(1);
}

try {
    (new Migrator($pdo, $root . "/storage/migrations"))->run();
    fwrite(STDOUT, "[migrate] concluído\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[migrate] erro: " . $e->getMessage() . "\n");
    exit(1);
}
