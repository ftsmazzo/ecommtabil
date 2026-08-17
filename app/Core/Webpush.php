<?php
namespace App\Core;

use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush as WebPushLib;
use Minishlink\WebPush\Subscription;

/**
 * Webpush
 * --------------------------------------------
 * Classe responsável por montar payloads (dados
 * enviados para o Service Worker) e disparar
 * notificações Web Push para usuários inscritos.
 *
 * Usa a tabela:
 *  - webpush_subscriptions
 *
 * Cada registro contém:
 *  - endpoint
 *  - p256dh
 *  - auth
 *  - user_token
 *
 * O envio é feito usando Minishlink/WebPush.
 */
class Webpush
{
    /** @var array Configurações VAPID vindas do arquivo config/push.php */
    protected static $config;

    /**
     * @var array Payload padrão enviado ao navegador via push
     *
     * title     -> Título da notificação
     * body      -> Mensagem em texto
     * icon      -> Ícone exibido na notificação
     * badge     -> Mini-ícone monocromático (Android)
     * url       -> URL aberta ao clicar na notificação
     * data      -> Qualquer dado extra que você quiser enviar
     * tag       -> Identificador para agrupar notificações iguais
     * renotify  -> true = re-emite a notificação se mesma tag existir
     *
     * vibrate   -> Padrão de vibração (Android)
     *              Exemplo: [100, 50, 100]
     *              (vibra 100ms, pausa 50ms, vibra 100ms)
     *
     * actions   -> Botões da notificação
     */
    protected array $payload = [
        "title"    => null,
        "body"     => null,
        "icon"     => "/storage/layout/webpush-icon-192.png",
        "badge"    => "/storage/layout/webpush-badge-72.png",
        "url"      => null,
        "data"     => null,
        "tag"      => null,
        "renotify" => false,
        "vibrate"  => [100, 50, 100],
        "actions"  => []
    ];

    /** @var array Configuração VAPID formatada para Minishlink/WebPush */
    protected array $vapid;

    public function __construct()
    {
        self::$config = Config::get("push");

        $this->vapid = [
            'VAPID' => [
                'subject'    => self::$config['subject'],
                'publicKey'  => self::$config['publicKey'],
                'privateKey' => self::$config['privateKey'],
            ]
        ];
    }

    /**
     * Gerar as chaves pública e privada para o VAPID
     *
     * @return void
     */
    public function generateKeys()
    {
        $keys = VAPID::createVapidKeys();

        echo json_encode([
            "publicKey"  => $keys['publicKey'],
            "privateKey" => $keys['privateKey'],
        ]);
    }

    /* ============================================================
        SETTERS DO PAYLOAD
    ============================================================ */

    /** Define o título da notificação */
    public function setTitle(string $title): self
    {
        $this->payload["title"] = $title;
        return $this;
    }

    /** Define a mensagem da notificação */
    public function setBody(string $body): self
    {
        $this->payload["body"] = $body;
        return $this;
    }

    /**
     * Define a URL aberta ao clicar no corpo da notificação.
     * Passe null para não abrir nada.
     */
    public function setUrl(?string $url): self
    {
        $this->payload["url"] = $url;
        return $this;
    }

    /** Ícone grande (mostrado na notificação) */
    public function setIcon(string $icon): self
    {
        $this->payload["icon"] = $icon;
        return $this;
    }

    /** Badge: ícone monocromático pequeno (Android) */
    public function setBadge(string $badge): self
    {
        $this->payload["badge"] = $badge;
        return $this;
    }

    /** DADOS EXTRAS enviados para o SW (qualquer coisa) */
    public function setData($data): self
    {
        $this->payload["data"] = $data;
        return $this;
    }

    /**
     * Tag: agrupa notificações iguais
     * -> permite substituir uma pela outra
     */
    public function setTag(string $tag): self
    {
        $this->payload["tag"] = $tag;
        return $this;
    }

    /**
     * Renotify:
     * false → se já existir uma notificação com mesma tag, ela é atualizada silenciosamente
     * true  → notifica de novo mesmo com tag igual
     */
    public function setRenotify(bool $value): self
    {
        $this->payload["renotify"] = $value;
        return $this;
    }

    /**
     * Padrão de vibração da notificação
     * Somente Android suporta.
     */
    public function setVibrate(array $pattern): self
    {
        $this->payload["vibrate"] = $pattern;
        return $this;
    }

    /* ============================================================
        AÇÕES (BOTÕES DE NOTIFICAÇÃO)
    ============================================================ */

    /**
     * Botão "Fechar notificação"
     * O SW não abre nada e não faz nada.
     */
    public function addButtonClose(string $title = "Fechar", ?string $icon = null): self
    {
        $this->payload["actions"][] = [
            "action" => "close",
            "title"  => $title,
            "icon"   => $icon
        ];
        return $this;
    }

    /**
     * Botão que abre uma URL específica
     */
    public function addButtonOpenUrl(string $url, string $title, ?string $icon = null): self
    {
        $this->payload["url"] = $url;

        $this->payload["actions"][] = [
            "action" => "open-url",
            "title"  => $title,
            "icon"   => $icon
        ];
        return $this;
    }

    /**
     * Botão "abrir app" (abre /)
     */
    public function addButtonOpenApp(string $title = "Abrir App", ?string $icon = null): self
    {
        $this->payload["actions"][] = [
            "action" => "open-app",
            "title"  => $title,
            "icon"   => $icon
        ];
        return $this;
    }

    /**
     * Botão de confirmação.
     * O SW recebe action = confirm-ID
     */
    public function addButtonConfirm(string $id, string $title = "Confirmar", ?string $icon = null): self
    {
        $this->payload["actions"][] = [
            "action" => "confirm-" . $id,
            "title"  => $title,
            "icon"   => $icon
        ];
        return $this;
    }

    /**
     * Botão de rejeição.
     * O SW recebe action = reject-ID
     */
    public function addButtonReject(string $id, string $title = "Rejeitar", ?string $icon = null): self
    {
        $this->payload["actions"][] = [
            "action" => "reject-" . $id,
            "title"  => $title,
            "icon"   => $icon
        ];
        return $this;
    }


    /* ============================================================
        ENVIO
    ============================================================ */

    /**
     * Envia notificação para todos os dispositivos que possuem
     * o user_token = $token registrado.
     */
    public function send(string $token): array
    {
        // Recupera todas as assinaturas do usuário
        $subs = DB::table("webpush_subscriptions")
            ->where("user_token", "=", $token)
            ->get();

        if (!$subs) {
            return ["status" => "empty"];
        }

        $webPush = new WebPushLib($this->vapid);
        $jsonPayload = json_encode($this->payload);

        // Fila de envios
        foreach ($subs as $s) {
            $subscription = Subscription::create([
                "endpoint" => $s->endpoint,
                "keys"     => [
                    "p256dh" => $s->p256dh,
                    "auth"   => $s->auth
                ]
            ]);

            $webPush->queueNotification($subscription, $jsonPayload);
        }

        // Executa envios
        $results = [];

        foreach ($webPush->flush() as $report) {
            $results[] = [
                "endpoint" => $report->getRequest()->getUri()->__toString(),
                "success"  => $report->isSuccess(),
                "error"    => $report->isSuccess() ? null : $report->getReason()
            ];
        }

        return [
            "status"  => "done",
            "results" => $results
        ];
    }
}
