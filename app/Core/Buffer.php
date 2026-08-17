<?php
namespace App\Core;

final class Buffer
{
    private int $padBytes;

    public function __construct(int $padBytes = 65536)
    {
        $this->padBytes = $padBytes;

        // CLI não precisa disso
        if (PHP_SAPI === 'cli') {
            return;
        }

        // Headers anti-buffer (tem que ser antes de qualquer output)
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-Accel-Buffering: no'); // Nginx
        }

        // PHP settings
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');

        // ✅ Mata QUALQUER buffer aberto por Router/View
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        @ob_implicit_flush(true);
    }

    public function out(string $msg): void
    {
        echo $msg;

        // empurra browser/proxy a renderizar (1KB às vezes não basta)
        if (PHP_SAPI !== 'cli' && $this->padBytes > 0) {
            echo str_repeat(' ', $this->padBytes);
        }

        @ob_flush();
        @flush();
    }

    public function line(string $msg): void
    {
        $this->out($msg . (PHP_SAPI === 'cli' ? PHP_EOL : "<br>\n"));
    }
}
