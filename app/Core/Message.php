<?php
namespace App\Core;

use App\Core\Config;
use App\Core\Session;

class Message
{
    private $message;
    private array $config;

    public function __construct()
    {
        $this->config = Config::get("message") ?? [];
    }

    public function get($id)
    {
        $messages = [
             1 => ['class' => 'danger',  'icon' => 'exclamation-circle',   'alter' => 'Erro',     'text' => 'Usuario/Senha invalidos'],
             2 => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'alter' => 'Atencao',  'text' => 'Usuario nao ativo no sistema'],
             3 => ['class' => 'danger',  'icon' => 'exclamation-circle',   'alter' => 'Erro',     'text' => 'Usuario/Senha invalidos'],
             4 => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'alter' => 'Atencao',  'text' => 'Digite seu usuario e senha'],
             5 => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'alter' => 'Atencao',  'text' => 'Voce nao esta conectado ao sistema'],
             6 => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'alter' => 'Atencao',  'text' => 'Voce foi desconectado do sistema'],
             7 => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'alter' => 'Atencao',  'text' => 'Voce nao tem permissao pra visualizar esta pagina'],
             8 => ['class' => 'success', 'icon' => 'check-circle',         'alter' => 'Sucesso',  'text' => 'Registro salvo com sucesso'],
             9 => ['class' => 'danger',  'icon' => 'exclamation-circle',   'alter' => 'Erro',     'text' => 'Houve um erro ao salvar registro'],
            10 => ['class' => 'success', 'icon' => 'check-circle',         'alter' => 'Sucesso',  'text' => 'Registro alterado com sucesso'],
            11 => ['class' => 'danger',  'icon' => 'exclamation-circle',   'alter' => 'Erro',     'text' => 'Houve um erro ao alterar registro'],
            12 => ['class' => 'success', 'icon' => 'check-circle',         'alter' => 'Sucesso',  'text' => 'Registro(s) excluido(s) com sucesso'],
            13 => ['class' => 'danger',  'icon' => 'exclamation-circle',   'alter' => 'Erro',     'text' => 'Houve um erro ao excluir o(s) registro(s)'],
            14 => ['class' => 'success', 'icon' => 'check-circle',         'alter' => 'Sucesso',  'text' => 'Status alterado(s) com sucesso'],
            15 => ['class' => 'danger',  'icon' => 'exclamation-circle',   'alter' => 'Erro',     'text' => 'Nao e possivel alterar o status'],
            16 => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'alter' => 'Atencao',  'text' => 'Nao foi possivel alterar o status de %1. %2 com sucesso'],
            17 => ['class' => 'warning', 'icon' => 'exclamation-triangle', 'alter' => 'Atencao',  'text' => 'Nao foi possivel excluir %1. %2 com sucesso'],
            18 => ['class' => 'danger',  'icon' => 'exclamation-circle',   'alter' => 'Erro',     'text' => 'Arquivo com extensao invalida'],
            19 => ['class' => 'danger',  'icon' => 'exclamation-circle',   'alter' => 'Erro',     'text' => 'Houve um erro ao subir o arquivo'],
            20 => ['class' => 'success', 'icon' => 'check-circle',         'alter' => 'Sucesso',  'text' => 'E-mail enviado com sucesso'],
            21 => ['class' => 'danger',  'icon' => 'exclamation-circle',   'alter' => 'Erro',     'text' => 'Houve um erro ao enviar o email'],
            22 => ['class' => 'danger',  'icon' => 'exclamation-triangle', 'alter' => 'Erro',     'text' => 'Usuario bloqueado no sistema. Entre em contato com o administrador.'],
        ];

        $this->message = $messages[$id] ?? null;
        return $this;
    }

    public function class()
    {
        return $this->message["class"] ?? null;
    }

    public function icon()
    {
        return $this->message["icon"] ?? null;
    }

    public function alter()
    {
        return $this->message["alter"] ?? null;
    }

    public function text()
    {
        return $this->message["text"] ?? null;
    }

    public function flash($index = null, $type = null, $icon = null, $renderer = null)
    {
        $session = new Session();

        if ($index) {
            $flashList = $session->has("flash") ? $session->flash : [];
            $flashList = json_decode(json_encode($flashList), true);

            if (!is_array($flashList)) {
                $flashList = [$flashList];
            }

            $flashList[] = [
                "index" => $index,
                "class" => $type,
                "icon" => $icon,
                "renderer" => $this->normalizeRenderer($renderer),
            ];

            $session->set("flash", $flashList);
            return null;
        }

        if ($session->has("flash")) {
            $flashList = $session->flash;
            $flashList = json_decode(json_encode($flashList), true);
            $session->unset("flash");

            if (isset($flashList["index"])) {
                return $this->renderFlash($flashList);
            }

            $html = '';

            foreach ($flashList as $flash) {
                $html .= $this->renderFlash($flash);
            }

            return $html;
        }

        return null;
    }

    private function renderFlash(array $flash): string
    {
        $payload = $this->resolvePayload(
            $flash["index"] ?? null,
            $flash["class"] ?? null,
            $flash["icon"] ?? null
        );

        $renderer = $this->normalizeRenderer($flash["renderer"] ?? null);
        $html = '';

        if (in_array($renderer, ["alert", "both"], true)) {
            $html .= $this->alertHtml($payload);
        }

        if (in_array($renderer, ["notify", "both"], true)) {
            $html .= $this->notifyScript($payload);
        }

        if ($renderer === "toastr") {
            $html .= $this->toastrScript($payload);
        }

        return $html;
    }

    private function resolvePayload($index, $class = null, $icon = null, $alter = null): array
    {
        if (is_int($index)) {
            $this->get($index);

            return [
                "text" => $this->text(),
                "class" => $this->class(),
                "icon" => $this->resolveIconValue($this->icon()),
                "alter" => $this->alter(),
            ];
        }

        return [
            "text" => $index,
            "class" => $class,
            "icon" => $this->resolveIconValue($icon, $class),
            "alter" => $alter,
        ];
    }

    private function alertHtml(array $payload): string
    {
        $text  = $payload["text"] ?? "";
        $class = $payload["class"] ?? "info";
        $icon  = $payload["icon"] ?? null;
        $alter = $payload["alter"] ?? null;

        $html = '<div class="alert alert-' . $class . ' alert-dismissible fade show" role="alert">';
        if (!empty($icon)) {
            $html .= '<span class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></span>';
        }
        if (!is_null($alter)) {
            $html .= '<span class="sr-only">' . $alter . ':</span>';
        }
        $html .= ' ' . $text;
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';

        return $html;
    }

    private function notifyScript(array $payload): string
    {
        $notify = $this->config["flash"]["notify"] ?? [];

        $data = [
            "message" => (string)($payload["text"] ?? ""),
            "type" => (string)($payload["class"] ?? "info"),
            "duration" => (int)($notify["duration"] ?? 5),
            "x" => (string)($notify["x"] ?? "right"),
            "y" => (string)($notify["y"] ?? "bottom"),
            "close" => (bool)($notify["close"] ?? true),
        ];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return '<script>(window.appFlashQueue=window.appFlashQueue||[]).push(' . $json . ');</script>';
    }

    private function toastrScript(array $payload): string
    {
        $toastr = $this->config["flash"]["toastr"] ?? [];

        $data = [
            "message" => (string)($payload["text"] ?? ""),
            "type" => (string)($payload["class"] ?? "info"),
            "title" => (string)($toastr["title"] ?? ""),
            "side" => (string)($toastr["side"] ?? "top right"),
            "duration" => (int)($toastr["duration"] ?? 5000),
            "extras" => $toastr["extras"] ?? [],
        ];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return '<script>(window.appFlashToastrQueue=window.appFlashToastrQueue||[]).push(' . $json . ');</script>';
    }

    private function normalizeRenderer(?string $renderer = null): string
    {
        $renderer = strtolower(trim((string)($renderer ?: ($this->config["flash"]["renderer"] ?? "alert"))));

        if (!in_array($renderer, ["alert", "notify", "toastr", "both"], true)) {
            return "alert";
        }

        return $renderer;
    }

    public function success($text, $icon = "check-circle", $alter = "Sucesso", $renderer = null)
    {
        return $this->flash($text, "success", $icon, $renderer);
    }

    public function warning($text, $icon = "exclamation-triangle", $alter = "Atencao", $renderer = null)
    {
        return $this->flash($text, "warning", $icon, $renderer);
    }

    public function danger($text, $icon = "exclamation-circle", $alter = "Erro", $renderer = null)
    {
        return $this->flash($text, "danger", $icon, $renderer);
    }

    public function info($text, $icon = "info-circle", $alter = "Informacao", $renderer = null)
    {
        return $this->flash($text, "info", $icon, $renderer);
    }

    public function primary($text, $icon = null, $alter = null, $renderer = null)
    {
        return $this->flash($text, "primary", $icon, $renderer);
    }

    public function secondary($text, $icon = null, $alter = null, $renderer = null)
    {
        return $this->flash($text, "secondary", $icon, $renderer);
    }

    public function light($text, $icon = null, $alter = null, $renderer = null)
    {
        return $this->flash($text, "light", $icon, $renderer);
    }

    public function dark($text, $icon = null, $alter = null, $renderer = null)
    {
        return $this->flash($text, "dark", $icon, $renderer);
    }

    public function error($text, $icon = "exclamation-circle", $alter = "Erro", $renderer = null)
    {
        return $this->danger($text, $icon, $alter, $renderer);
    }

    protected function icons($type)
    {
        $icons = [
            "success" => "check-circle",
            "warning" => "exclamation-triangle",
            "danger" => "exclamation-circle",
            "info" => "info-circle",
        ];

        return $icons[$type] ?? null;
    }

    private function resolveIconValue($icon, ?string $class = null): ?string
    {
        if ($icon === null || $icon === false || $icon === '') {
            return null;
        }

        if ($icon === true) {
            $icon = $class ? $this->icons($class) : null;
        }

        if (!$icon || !is_string($icon)) {
            return null;
        }

        $icon = trim($icon);

        if ($icon === '') {
            return null;
        }

        if (preg_match('/\s/', $icon)) {
            return $icon;
        }

        if (str_starts_with($icon, 'uil-') || str_starts_with($icon, 'uis-')) {
            return 'uil ' . $icon;
        }

        if (str_starts_with($icon, 'fa-')) {
            return 'fa fa-fw ' . $icon;
        }

        if (
            str_starts_with($icon, 'uil') ||
            str_starts_with($icon, 'uis') ||
            str_starts_with($icon, 'fas') ||
            str_starts_with($icon, 'far') ||
            str_starts_with($icon, 'fab') ||
            str_starts_with($icon, 'fal') ||
            str_starts_with($icon, 'fad') ||
            str_starts_with($icon, 'fat')
        ) {
            return $icon;
        }

        return 'fa fa-fw fa-' . $icon;
    }
}
