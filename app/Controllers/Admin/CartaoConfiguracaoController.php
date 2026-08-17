<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Request;
use App\Core\Data;
use App\Core\Redirect;
use App\Models\Configuracao;

class CartaoConfiguracaoController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Configurações do Cartão",
            "active_menu" => "cartao-configuracoes",
            "page"        => [
                "title" => "Configurações do Cartão",
                "desc"  => "Parâmetros gerais do sistema de cartões",
            ],
        ]);
    }

    public function index(): void
    {
        if (!$this->auth->allow("configuracao_cartao") && !$this->auth->allow("configuracao_saldo")) {
            $this->router->redirect("admin.home");
            return;
        }

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões"   => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Configurações" => ["url" => false, "current" => true],
            ],
        ]);

        $config = Configuracao::allIndexed();

        $permissao = [
            "cartao" => $this->auth->allow("configuracao_cartao"),
            "saldo"  => $this->auth->allow("configuracao_saldo"),
        ];

        echo $this->view->render("admin/cartao/configuracao/index", [
            "config"     => $config,
            "permissao"  => $permissao,
            "csrf"       => $this->csrf->generate(),
            "url_action" => $this->router->route("admin.cartao.config.update"),
        ]);
    }

    public function update(Request $request): void
    {
        if (!$this->auth->allow("configuracao_cartao") && !$this->auth->allow("configuracao_saldo")) {
            $this->router->redirect("admin.home");
            return;
        }

        $data = new Data($request->all());
        $uid  = $this->user->uid;

        if ($this->auth->allow("configuracao_cartao")) {
            Configuracao::setValue(
                "cartao_cliente_obrigatorio",
                $data->cartao_cliente_obrigatorio ?? "0",
                $uid
            );
        }

        if ($this->auth->allow("configuracao_saldo")) {
            Configuracao::setValue("recarga_minima", $this->parseMoney($data->recarga_minima), $uid);
            Configuracao::setValue("recarga_expira_dias", $this->parseDias($data->recarga_expira_dias), $uid);
            Configuracao::setValue("cashback_expira_dias", $this->parseDias($data->cashback_expira_dias), $uid);
            Configuracao::setValue("fidelidade_expira_dias", $this->parseDias($data->fidelidade_expira_dias), $uid);
        }

        $this->message->success("Configurações salvas com sucesso");
        Redirect::referer();
    }

    private function parseDias(mixed $value): ?string
    {
        $v = (int) ($value ?? 0);
        return $v > 0 ? (string) $v : null;
    }

    private function parseMoney(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace(',', '.', str_replace('.', '', $raw));
        $amount = round((float) $normalized, 2);

        return $amount > 0 ? number_format($amount, 2, '.', '') : null;
    }
}
