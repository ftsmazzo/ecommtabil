<?php

namespace App\Controllers\Servicos;

use App\Core\Data;
use App\Core\DB;
use App\Core\Json;
use App\Core\ControllerServico;
use App\Core\Webpush;
use App\Models\WebpushSubscription;

class WebpushController extends ControllerServico
{

    public function generate()
    {
        (new Webpush())->generateKeys();
    }

    /**
     * Salva OU atualiza uma inscrição de push no banco
     */
    public function save()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $json = (new Data())->fromJson();

            if (!$json) {
                http_response_code(400);
                echo Json::encode(["success" => false, "message" => "Nenhum dado recebido"]);
                exit;
            }

            $endpoint = trim($json["endpoint"]);

            // se veio "s://", corrige
            if (str_starts_with($endpoint, "s://")) {
                $endpoint = "https://" . substr($endpoint, 4);
            }

            // se não começa com http/https, rejeita
            if (!preg_match('#^https?://#i', $endpoint)) {
                http_response_code(422);
                echo Json::encode(["success" => false, "message" => "Endpoint inválido", "endpoint" => $endpoint]);
                exit;
            }

            $token    = $json["userToken"] ?? null;
            $endpoint = $endpoint;
            $keys     = $json["keys"]      ?? null;

            if (!$token || !$endpoint || !$keys || empty($keys["p256dh"]) || empty($keys["auth"])) {
                http_response_code(422);
                echo Json::encode(["success" => false, "message" => "Dados incompletos"]);
                exit;
            }

            WebpushSubscription::saveOrUpdate(
                $token,
                $endpoint,
                $keys["p256dh"],
                $keys["auth"]
            );

            echo Json::encode(["success" => true]);
            exit;

        } catch (\Throwable $e) {
            http_response_code(500);
            echo Json::encode([
                "success" => false,
                "message" => "Erro interno ao salvar subscription",
                "error"   => $e->getMessage(),
            ]);
            exit;
        }
    }


    public function delete()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $json = (new Data())->fromJson();

            if (!$json) {
                http_response_code(400);
                echo Json::encode(["success" => false, "message" => "Nenhum dado recebido"]);
                exit;
            }

            $endpoint = $json["endpoint"] ?? null;

            if (!$endpoint) {
                http_response_code(422);
                echo Json::encode(["success" => false, "message" => "Endpoint não informado"]);
                exit;
            }

            $endpoint = trim($endpoint);

            // Corrige bug "s://"
            if (str_starts_with($endpoint, 's://')) {
                $endpoint = 'https://' . substr($endpoint, 4);
            }

            // (opcional) se vier sem esquema, tenta completar
            if (!preg_match('#^https?://#i', $endpoint)) {
                http_response_code(422);
                echo Json::encode(["success" => false, "message" => "Endpoint inválido", "endpoint" => $endpoint]);
                exit;
            }

            $deleted = WebpushSubscription::deleteByEndpoint($endpoint);

            echo Json::encode(["success" => true, "deleted" => $deleted]);
            exit;

        } catch (\Throwable $e) {
            http_response_code(500);
            echo Json::encode([
                "success" => false,
                "message" => "Erro interno ao apagar subscription",
                "error"   => $e->getMessage(),
            ]);
            exit;
        }
    }


    public function send()
    {

        $token = "44fe2c66c38c19f45cb249ba0e247cc9e5e31abfd1776474dee591da64fead8f";

        $push = new Webpush;

        $push->setTitle("Novo pedido!")
            ->setBody("Pedido #55 foi criado.")
            ->addButtonOpenUrl("/pedido/55", "Ver Pedido")
            ->addButtonClose()
            ->send($token);



    }




}
