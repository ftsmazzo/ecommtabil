<?php

namespace App\Services\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Aplica SQL de storage/migrations na subida do container.
 * Não exige terminal na VPS. Idempotente via tabela schema_migrations.
 */
class Migrator
{
    public function __construct(
        private PDO $pdo,
        private string $dir
    ) {
    }

    public function run(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `filename` VARCHAR(255) NOT NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`filename`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->baselineLegado();

        $aplicados = $this->aplicados();
        $arquivos = $this->listarSql();

        foreach ($arquivos as $file) {
            $nome = basename($file);
            if (isset($aplicados[$nome])) {
                fwrite(STDOUT, "[migrate] ok (já aplicada) {$nome}\n");
                continue;
            }
            fwrite(STDOUT, "[migrate] aplicando {$nome}\n");
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException("Não foi possível ler {$nome}");
            }
            $this->executarArquivo($sql);
            $stmt = $this->pdo->prepare("INSERT INTO `schema_migrations` (`filename`) VALUES (?)");
            $stmt->execute([$nome]);
            fwrite(STDOUT, "[migrate] concluída {$nome}\n");
        }

        $this->limparImportacaoUmaVez();
    }

    /**
     * Zera lançamentos e de-paras de teste. Roda uma vez por banco.
     */
    public function limparTabelasImportacao(): void
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach (["projeto_lancamento", "projeto_mapeamento_coluna", "projeto_origem_perfil"] as $tabela) {
            if (!$this->tabelaExiste($tabela)) {
                continue;
            }
            $this->pdo->exec("TRUNCATE TABLE `{$tabela}`");
            fwrite(STDOUT, "[migrate] limpou {$tabela}\n");
        }
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    private function limparImportacaoUmaVez(): void
    {
        $chave = "__reset_importacao_v1";
        $aplicados = $this->aplicados();
        if (isset($aplicados[$chave])) {
            return;
        }
        $this->limparTabelasImportacao();
        $stmt = $this->pdo->prepare("INSERT INTO `schema_migrations` (`filename`) VALUES (?)");
        $stmt->execute([$chave]);
        fwrite(STDOUT, "[migrate] reset de importação registrado ({$chave})\n");
    }

    /**
     * Banco já existente (FTP/EasyPanel) sem tracker: não reexecuta ALTER antigo.
     */
    private function baselineLegado(): void
    {
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM `schema_migrations`")->fetchColumn();
        if ($count > 0) {
            return;
        }
        if (!$this->tabelaExiste("projeto") && !$this->tabelaExiste("usuario")) {
            return;
        }
        foreach ($this->listarSql() as $file) {
            $nome = basename($file);
            $ver = $this->versao($nome);
            if ($ver === null || version_compare($ver, "0.1.5", ">=")) {
                continue;
            }
            $stmt = $this->pdo->prepare("INSERT INTO `schema_migrations` (`filename`) VALUES (?)");
            $stmt->execute([$nome]);
            fwrite(STDOUT, "[migrate] baseline legado {$nome}\n");
        }
    }

    /**
     * @return array<string,true>
     */
    private function aplicados(): array
    {
        $rows = $this->pdo->query("SELECT `filename` FROM `schema_migrations`")->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($rows as $n) {
            $out[(string) $n] = true;
        }
        return $out;
    }

    /**
     * @return array<int,string>
     */
    private function listarSql(): array
    {
        $files = glob($this->dir . "/*.sql") ?: [];
        $files = array_values(array_filter($files, static function (string $path): bool {
            $base = basename($path);
            if (strcasecmp($base, "ecommtabil.sql") === 0) {
                return false;
            }
            return (bool) preg_match('/^\d/', $base);
        }));
        natsort($files);
        return array_values($files);
    }

    private function versao(string $filename): ?string
    {
        if (preg_match('/^(\d+\.\d+(?:\.\d+)?)/', $filename, $m)) {
            return $m[1];
        }
        return null;
    }

    private function tabelaExiste(string $tabela): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute([$tabela]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function executarArquivo(string $sql): void
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', "", $sql) ?? $sql;
        $linhas = [];
        foreach (preg_split('/\r\n|\n|\r/', $sql) ?: [] as $linha) {
            $trim = ltrim($linha);
            if (str_starts_with($trim, "--")) {
                continue;
            }
            $linhas[] = $linha;
        }
        $sql = implode("\n", $linhas);
        $partes = preg_split('/;\s*/', $sql) ?: [];
        foreach ($partes as $parte) {
            $parte = trim($parte);
            if ($parte === "") {
                continue;
            }
            $this->pdo->exec($parte);
        }
    }
}
