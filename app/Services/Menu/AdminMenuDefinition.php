<?php

namespace App\Services\Menu;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Router;

class AdminMenuDefinition extends AbstractMenuDefinition
{
    public function __construct(
        private Router $router,
        private Auth $auth
    ) {
    }

    public function items(): array
    {
        $useRamo = (bool) Config::get("modulos.cliente.use_ramo", false);

        $clienteItems = array_values(array_filter([
            $this->link("clientes-clientes", "Grupo de Clientes", "admin.cliente.index", [
                "permission" => "cliente_gerenciar",
            ]),
            $this->link("clientes-empresas", "Empresas", "admin.empresa.index", [
                "permission" => "empresa_gerenciar",
            ]),
            $this->link("clientes-situacoes", "Situações", "admin.cliente.situacao.index", [
                "permission" => "cliente_situacao_gerenciar",
            ]),
            $useRamo ? $this->link("clientes-ramos", "Ramos", "admin.cliente.ramo.index", [
                "permission" => "cliente_ramo_gerenciar",
            ]) : null,
        ]));

        $cadastrosItems = array_values(array_filter([
            $this->link("cadastros-bancos", "Bancos", "admin.banco.index", [
                "permission" => "banco_gerenciar",
            ]),
            $this->link("cadastros-cartao-bandeira", "Bandeiras de Cartão", "admin.cartao.bandeira.index", [
                "permission" => "cartao_bandeira_gerenciar",
            ]),
            $this->link("cadastros-canal-venda", "Canais de Venda", "admin.canal.venda.index", [
                "permission" => "canal_venda_gerenciar",
            ]),
            $this->link("cadastros-indice-financeiro", "Índices Financeiros", "admin.indice.financeiro.index", [
                "permission" => "indice_financeiro_gerenciar",
            ]),
        ]));

        return array_values(array_filter([
            $this->link("painel", "Painel", "admin.home", [
                "icon" => "uil-dashboard",
            ]),
            $this->drop("projetos", "Projetos", [
                $this->link("projetos-todos", "Todos os Projetos", "admin.projeto.index"),
            ], [
                "icon" => "uil uil-chart-growth",
                "permission" => "projeto_gerenciar",
            ]),
            $this->drop("clientes", "Clientes", $clienteItems, [
                "icon" => "uil uil-users-alt",
            ]),
            !empty($cadastrosItems) ? $this->title("Cadastros") : null,
            !empty($cadastrosItems) ? $this->drop("cadastros", "Cadastros", $cadastrosItems, [
                "icon" => "uil uil-folder",
            ]) : null,
            $this->title("Configurações"),
            $this->drop("configuracoes", "Configurações", [
                $this->link("configuracoes-dre-tipo", "Tipos de Demonstrativo", "admin.dre.tipo.index", [
                    "permission" => "tipo_demonstrativo_gerenciar",
                ]),
                $this->link("configuracoes-plano-contas", "Plano de Contas", "admin.dre.conta.index", [
                    "permission" => "plano_contas_gerenciar",
                ]),
                $this->link("configuracoes-dre-estrutura", "Modelo de Demonstrativo", "admin.modelo.demonstrativo.index", [
                    "permission" => "modelo_demonstrativo_gerenciar",
                ]),
                $this->link("configuracoes-planilha", "Planilha Modelo", "admin.configuracao.planilha", [
                    "permission" => "config_planilha_gerenciar",
                ]),
                $this->drop("configuracoes-indices", "Índices", [
                    $this->link("configuracoes-indices-ipca", "IPCA", "admin.configuracao.ipca", [
                        "permission" => "configuracao_ipca",
                    ]),
                    $this->link("configuracoes-indices-selic", "SELIC", "admin.configuracao.selic", [
                        "permission" => "configuracao_selic",
                    ]),
                    $this->link("configuracoes-indices-igpm", "IGP-M", "admin.configuracao.igpm", [
                        "permission" => "configuracao_igpm",
                    ]),
                ]),
            ], [
                "icon" => "uil uil-setting",
            ]),
            $this->title("Usuários"),
            $this->drop("usuarios", "Usuários", [
                $this->link("usuarios-usuarios", "Usuários", "admin.usuario.index", [
                    "permission" => "usuario_gerenciar",
                ]),
                $this->link("usuarios-perfis", "Perfis de Acesso", "admin.perfil.index", [
                    "permission" => "usuario_permissoes",
                ]),
                $this->link("usuarios-senha", "Alterar Senha", "admin.pass", [
                    "horizontal_only" => true,
                ]),
            ], [
                "icon" => "uil-user",
            ]),
            $this->link("senha", "Alterar Senha", "admin.pass", [
                "icon" => "uil-lock-alt",
                "vertical_only" => true,
            ]),
            $this->link("logout", "Sair do Sistema", "admin.logout", [
                "icon" => "uil uil-signout",
                "extra" => fn () => 'data-logoff="' . $this->router->route($this->auth->getRouteLogout()) . '"',
            ]),
        ]));
    }
}
