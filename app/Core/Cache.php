<?php
namespace App\Core;

use App\Core\Config;

class Cache
{
    private string $engine; // auto | file | apcu
    private string $path;
    private string $prefix;
    private int $timeout;
    private string $ext;
    private string $format; // serialize | json
    private bool $apcuAvailable = false;

    public function __construct()
    {
        $config = Config::get("cache");

        // PATH
        $path = $config["path"] ?? "storage/cache/";
        $this->path = rtrim($path, "/") . "/";

        // TIMEOUT, EXT, ENGINE, PREFIX, FORMAT
        $this->prefix  = $config["prefix"] ?? "";
        $this->timeout = $config["timeout"] ?? 525600;
        $this->ext     = $config["ext"]     ?? ".txt";
        $this->format  = $config["format"]  ?? "serialize";

        // valida e define engine
        $this->setEngine($config["engine"] ?? "auto");

        // APCu disponível?
        $this->apcuAvailable =
            function_exists("apcu_fetch") &&
            ini_get("apc.enabled") &&
            !ini_get("apc.enable_cli");

        // cria pasta se não existir
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }

    // ======================================================
    // ENGINE (auto | file | apcu)
    // ======================================================

    public function setEngine(string $engine): self
    {
        $allowed = ["auto", "file", "apcu"];
        $engine = strtolower($engine);

        if (!in_array($engine, $allowed)) {
            $engine = "auto";
        }

        $this->engine = $engine;
        return $this;
    }

    private function useAPCu(): bool
    {
        if ($this->engine === "apcu") {
            return $this->apcuAvailable;
        }

        if ($this->engine === "file") {
            return false;
        }

        // auto
        return $this->apcuAvailable;
    }

    private function apcuKey(string $id): string
    {
        return "cache_" . $this->prefix . $id;
    }

    private function fileName(string $id): string
    {
        return $this->path . $this->prefix . $id . $this->ext;
    }

    // public function has(string $id): bool
    // {
    //     // APCu
    //     if ($this->useAPCu()) {
    //         apcu_fetch($this->apcuKey($id), $ok);
    //         return $ok;
    //     }

    //     // Arquivo
    //     $file = $this->fileName($id);
    //     return !$this->isExpired($file);
    // }

    public function has(string $id): bool
    {
        // APCu
        if ($this->useAPCu()) {
            apcu_fetch($this->apcuKey($id), $ok);
            return $ok;
        }

        // Arquivo
        $file = $this->fileName($id);
        if (!file_exists($file)) return false;

        $raw = @file_get_contents($file);
        if ($raw === false) return false;

        $decoded = $this->decode($raw);

        // TTL por item (setWithTtl)
        if (is_array($decoded) && array_key_exists('__expires_at', $decoded)) {
            if ((int)$decoded['__expires_at'] <= time()) {
                @unlink($file);
                return false;
            }
            return true;
        }

        // fallback: timeout global
        return !$this->isExpired($file);
    }


    // ======================================================
    // FORMAT (serialize | json)
    // ======================================================

    private function encode($data): string
    {
        return match ($this->format) {
            "json"      => json_encode($data),
            "serialize" => serialize($data),
            default     => serialize($data),
        };
    }

    private function decode(string $raw)
    {
        return match ($this->format) {
            "json"      => json_decode($raw, true),
            "serialize" => unserialize($raw),
            default     => unserialize($raw),
        };
    }

    // ======================================================
    // GET
    // ======================================================

    // public function get(string $id)
    // {
    //     if ($this->useAPCu()) {
    //         $value = apcu_fetch($this->apcuKey($id), $ok);
    //         return $ok ? $value : null;
    //     }

    //     $file = $this->fileName($id);
    //     if ($this->isExpired($file)) return null;

    //     $raw = @file_get_contents($file);
    //     return $raw !== false ? $this->decode($raw) : null;
    // }

    public function get(string $id)
    {
        if ($this->useAPCu()) {
            $value = apcu_fetch($this->apcuKey($id), $ok);
            return $ok ? $value : null;
        }

        $file = $this->fileName($id);
        if (!file_exists($file)) return null;

        $raw = @file_get_contents($file);
        if ($raw === false) return null;

        $decoded = $this->decode($raw);

        // TTL por item (setWithTtl)
        if (is_array($decoded) && array_key_exists('__expires_at', $decoded)) {
            if ((int)$decoded['__expires_at'] <= time()) {
                @unlink($file);
                return null;
            }
            return $decoded['__value'] ?? null;
        }

        // fallback: timeout global
        if ($this->isExpired($file)) return null;

        return $decoded;
    }

    // ======================================================
    // SET
    // ======================================================

    public function set(string $id, $data): void
    {
        if ($this->useAPCu()) {
            apcu_store($this->apcuKey($id), $data, $this->timeout * 60);
            return;
        }

        $this->writeFile($this->fileName($id), $data);
    }

    // ======================================================
    // REMEMBER
    // ======================================================

    public function remember(string $id, $data)
    {
        $value = $this->get($id);

        if ($value !== null) {
            return $value === '__CACHE_NULL__' ? null : $value;
        }

        if (is_callable($data)) {
            $data = $data();
        }

        $this->set($id, $data === null ? '__CACHE_NULL__' : $data);
        return $data;
    }

    // ======================================================
    // CLEAR
    // ======================================================

    public function clear(?string $id = null): void
    {
        // APCu
        if ($this->apcuAvailable) {
            if ($id === null) {
                apcu_clear_cache();
            } else {
                apcu_delete($this->apcuKey($id));
            }
        }

        // Arquivos
        if ($id === null) {
            foreach (glob($this->path . "*") as $file) {
                @unlink($file);
            }
        } else {
            $file = $this->fileName($id);
            if (file_exists($file)) unlink($file);
        }
    }

    public function clearGroup(string $prefix): void
    {
        // APCu
        if ($this->apcuAvailable) {
            $info = apcu_cache_info();
            if (!empty($info['cache_list'])) {
                foreach ($info['cache_list'] as $item) {
                    $key = $item['info'] ?? '';
                    if (str_starts_with($key, "cache_" . $this->prefix . $prefix)) {
                        apcu_delete($key);
                    }
                }
            }
        }

        // Arquivos
        foreach (glob($this->path . $this->prefix . $prefix . "*".$this->ext) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    // ======================================================
    // FILE WRITE WITH flock() + atomic rename
    // ======================================================

    private function writeFile(string $file, $data): void
    {
        $temp = $file . '.' . uniqid("tmp_", true);
        $fp = fopen($temp, 'w');

        if (!$fp) return;

        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $this->encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
        }

        fclose($fp);
        rename($temp, $file);
    }

    // ======================================================
    // EXPIRAÇÃO
    // ======================================================

    private function isExpired(string $file): bool
    {
        if (!file_exists($file)) return true;

        $created = filemtime($file);
        $expires = $created + ($this->timeout * 60);

        if ($expires <= time()) {
            @unlink($file);
            return true;
        }

        return false;
    }

    public function delete(string $id): void
    {
        $this->clear($id);
    }

    /**
     * Salva um item com TTL específico (em segundos), sem depender do timeout global.
     * Funciona em APCu e File.
     */
    public function setWithTtl(string $id, $data, int $ttlSec): void
    {
        $ttlSec = max(1, (int)$ttlSec);

        // APCu tem TTL nativo
        if ($this->useAPCu()) {
            apcu_store($this->apcuKey($id), $data, $ttlSec);
            return;
        }

        // File: embute metadados de expiração no conteúdo
        $payload = [
            '__expires_at' => time() + $ttlSec,
            '__value'      => $data,
        ];

        $this->writeFile($this->fileName($id), $payload);
    }

}
