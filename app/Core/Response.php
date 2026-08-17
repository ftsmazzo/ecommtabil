<?php

namespace App\Core;

class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected mixed $body = null;

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function json(array $data): void
    {
        $this->header("Content-Type", "application/json");
        $this->body = json_encode($data);
        $this->send();
    }

    public function text(string $text): void
    {
        $this->header("Content-Type", "text/plain; charset=utf-8");
        $this->body = $text;
        $this->send();
    }

    public function html(string $html): void
    {
        $this->header("Content-Type", "text/html; charset=utf-8");
        $this->body = $html;
        $this->send();
    }

    public function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header("Location: {$url}");
        exit;
    }

    public function download(string $filePath, ?string $filename = null): void
    {
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "File not found.";
            exit;
        }

        $filename = $filename ?: basename($filePath);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $k => $v) {
            header("{$k}: {$v}");
        }

        echo $this->body;
        exit;
    }
}
